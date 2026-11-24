<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph;

use M365Graph\Auth\TokenProvider;
use M365Graph\Certificate\CertificateLoader;
use M365Graph\Exception\AccessDeniedException;
use M365Graph\Exception\AuthenticationException;
use M365Graph\Exception\GraphException;
use M365Graph\Exception\NotFoundException;
use M365Graph\Exception\ThrottledException;
use M365Graph\Http\HttpResponse;
use M365Graph\Http\HttpTransportInterface;
use M365Graph\Http\JoomlaHttpTransport;
use M365Graph\Jwt\ClientAssertionBuilder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Microsoft Graph client (app-only, certificate credentials, read-only use).
 *
 * Features: @odata.nextLink paging, Retry-After handling for 429/503/504
 * with a bounded number of retries, and mapping of HTTP errors to typed
 * exceptions that carry only the status code, request-id and error code.
 *
 * @since  1.0.0
 */
final class Client implements GraphClientInterface
{
    /**
     * Graph base URL.
     *
     * @var    string
     * @since  1.0.0
     */
    public const BASE_URL = 'https://graph.microsoft.com/v1.0';

    /**
     * Longest single wait honoured from Retry-After, in seconds.
     *
     * @var    integer
     * @since  1.0.0
     */
    public const MAX_RETRY_AFTER = 60;

    /**
     * Safety limit for pages followed in one collection.
     *
     * @var    integer
     * @since  1.0.0
     */
    public const MAX_PAGES = 500;

    /**
     * Sleep function (seconds).
     *
     * @var    \Closure(int): void
     * @since  1.0.0
     */
    private \Closure $sleeper;

    /**
     * Constructor.
     *
     * @param   TokenProvider               $tokens      Token provider.
     * @param   HttpTransportInterface      $transport   HTTP transport.
     * @param   integer                     $maxRetries  Retries for throttled requests.
     * @param   (\Closure(int): void)|null  $sleeper     Sleep function for tests.
     *
     * @since   1.0.0
     */
    public function __construct(
        private readonly TokenProvider $tokens,
        private readonly HttpTransportInterface $transport,
        private readonly int $maxRetries = 3,
        ?\Closure $sleeper = null
    ) {
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * Build a client from connection settings using Joomla's HTTP client.
     *
     * @param   ConnectionSettings  $settings  Settings (validated by the caller).
     * @param   string              $webRoot   Web root the key files must be outside of.
     *
     * @return  self
     *
     * @throws  Exception\ConfigurationException
     *
     * @since   1.0.0
     */
    public static function create(ConnectionSettings $settings, string $webRoot): self
    {
        $settings->validate($webRoot);

        $transport = new JoomlaHttpTransport();
        $tokens    = new TokenProvider($settings, new ClientAssertionBuilder(new CertificateLoader($webRoot)), $transport);

        return new self($tokens, $transport);
    }

    /**
     * {@inheritDoc}
     *
     * @since   1.0.0
     */
    public function authenticate(): void
    {
        $this->tokens->getAccessToken();
    }

    /**
     * {@inheritDoc}
     *
     * @since   1.0.0
     */
    public function get(string $path, array $query = [], array $headers = []): array
    {
        return $this->send(self::buildUrl($path, $query), $headers)->json();
    }

    /**
     * {@inheritDoc}
     *
     * @since   1.0.0
     */
    public function getCollection(string $path, array $query = [], array $headers = []): \Generator
    {
        $url   = self::buildUrl($path, $query);
        $pages = 0;

        while ($url !== null) {
            if (++$pages > self::MAX_PAGES) {
                throw new GraphException('Too many pages.', 0, '', 'too_many_pages');
            }

            $data = $this->send($url, $headers)->json();

            foreach ((array) ($data['value'] ?? []) as $item) {
                if (\is_array($item)) {
                    yield $item;
                }
            }

            $next = $data['@odata.nextLink'] ?? null;
            $url  = null;

            if (\is_string($next) && $next !== '') {
                // Never send the bearer token to another host.
                if (!str_starts_with($next, self::BASE_URL . '/')) {
                    throw new GraphException('Unexpected nextLink host.', 0, '', 'invalid_next_link');
                }

                $url = $next;
            }
        }
    }

    /**
     * Build an absolute Graph URL.
     *
     * @param   string                 $path   Path, already URL-encoded per segment.
     * @param   array<string, scalar>  $query  Query parameters.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function buildUrl(string $path, array $query = []): string
    {
        $url = self::BASE_URL . '/' . ltrim($path, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * Send a GET request with retries and error mapping.
     *
     * @param   string                 $url      Absolute URL.
     * @param   array<string, string>  $headers  Extra headers.
     *
     * @return  HttpResponse
     *
     * @throws  GraphException
     *
     * @since   1.0.0
     */
    private function send(string $url, array $headers): HttpResponse
    {
        $attempt         = 0;
        $authRetried     = false;

        while (true) {
            $requestHeaders = $headers + [
                'Authorization'     => 'Bearer ' . $this->tokens->getAccessToken(),
                'Accept'            => 'application/json',
                'client-request-id' => Jwt\ClientAssertionBuilder::uuidV4(),
            ];

            try {
                $response = $this->transport->request('GET', $url, $requestHeaders);
            } catch (\RuntimeException $e) {
                throw new GraphException('Graph not reachable.', 0, '', 'transport', $e);
            } finally {
                unset($requestHeaders);
            }

            $status = $response->status;

            if ($status >= 200 && $status < 300) {
                return $response;
            }

            if (\in_array($status, [429, 503, 504], true) && $attempt < $this->maxRetries) {
                $attempt++;
                ($this->sleeper)(self::retryDelay($response->header('Retry-After'), $attempt));
                continue;
            }

            // An expired token is renewed once.
            if ($status === 401 && !$authRetried) {
                $authRetried = true;
                $this->tokens->clear();
                continue;
            }

            throw self::mapError($response);
        }
    }

    /**
     * Seconds to wait before a retry.
     *
     * @param   string   $retryAfter  Retry-After header (seconds or HTTP date).
     * @param   integer  $attempt     Attempt number (1-based).
     *
     * @return  integer
     *
     * @since   1.0.0
     */
    public static function retryDelay(string $retryAfter, int $attempt): int
    {
        $retryAfter = trim($retryAfter);

        if ($retryAfter !== '' && ctype_digit($retryAfter)) {
            $delay = (int) $retryAfter;
        } elseif ($retryAfter !== '' && ($time = strtotime($retryAfter)) !== false) {
            $delay = $time - time();
        } else {
            // Exponential backoff when no header is sent: 2, 4, 8 ...
            $delay = 2 ** $attempt;
        }

        return max(1, min(self::MAX_RETRY_AFTER, $delay));
    }

    /**
     * Map an error response to an exception.
     *
     * @param   HttpResponse  $response  Response.
     *
     * @return  GraphException
     *
     * @since   1.0.0
     */
    public static function mapError(HttpResponse $response): GraphException
    {
        $data      = $response->json();
        $code      = \is_string($data['error']['code'] ?? null) ? $data['error']['code'] : '';
        $requestId = $response->header('request-id') ?: $response->header('client-request-id');
        $status    = $response->status;
        $message   = 'Graph request failed with HTTP ' . $status . '.';

        return match (true) {
            $status === 401                          => new AuthenticationException($message, $status, $requestId, $code),
            $status === 403                          => new AccessDeniedException($message, $status, $requestId, $code),
            $status === 404                          => new NotFoundException($message, $status, $requestId, $code),
            \in_array($status, [429, 503, 504], true) => new ThrottledException($message, $status, $requestId, $code),
            default                                  => new GraphException($message, $status, $requestId, $code),
        };
    }
}
