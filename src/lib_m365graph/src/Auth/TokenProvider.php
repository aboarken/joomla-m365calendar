<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph\Auth;

use M365Graph\ConnectionSettings;
use M365Graph\Exception\AuthenticationException;
use M365Graph\Http\HttpTransportInterface;
use M365Graph\Jwt\ClientAssertionBuilder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Obtains app-only access tokens with a certificate client assertion.
 *
 * The token is only kept in memory on this object, for the lifetime of the
 * object (one sync run). It is never persisted, logged or exposed.
 *
 * @since  0.1.0
 */
final class TokenProvider
{
    /**
     * Graph scope for app-only access.
     *
     * @var    string
     * @since  0.1.0
     */
    public const SCOPE = 'https://graph.microsoft.com/.default';

    /**
     * In-memory access token.
     *
     * @var    string|null
     * @since  0.1.0
     */
    private ?string $accessToken = null;

    /**
     * Expiry (UNIX time) of the in-memory token.
     *
     * @var    integer
     * @since  0.1.0
     */
    private int $expiresAt = 0;

    /**
     * Constructor.
     *
     * @param   ConnectionSettings      $settings   Connection settings.
     * @param   ClientAssertionBuilder  $assertion  JWT builder.
     * @param   HttpTransportInterface  $transport  HTTP transport.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly ConnectionSettings $settings,
        private readonly ClientAssertionBuilder $assertion,
        private readonly HttpTransportInterface $transport
    ) {
    }

    /**
     * Get a valid access token, requesting a new one when needed.
     *
     * @return  string
     *
     * @throws  AuthenticationException
     *
     * @since   0.1.0
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->expiresAt - 60) {
            return $this->accessToken;
        }

        $body = http_build_query(
            [
                'client_id'             => $this->settings->clientId,
                'scope'                 => self::SCOPE,
                'grant_type'            => 'client_credentials',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion'      => $this->assertion->build($this->settings),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        try {
            $response = $this->transport->request(
                'POST',
                ClientAssertionBuilder::tokenEndpoint($this->settings->tenantId),
                ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'],
                $body
            );
        } catch (\RuntimeException $e) {
            throw new AuthenticationException('Token endpoint not reachable.', 0, '', 'transport', $e);
        }

        $data = $response->json();

        if ($response->status !== 200 || !isset($data['access_token']) || !\is_string($data['access_token'])) {
            $error = \is_string($data['error'] ?? null) ? $data['error'] : '';
            // AADSTS code only, never the full description.
            if (\is_array($data['error_codes'] ?? null) && isset($data['error_codes'][0])) {
                $error .= ($error !== '' ? ' ' : '') . 'AADSTS' . (int) $data['error_codes'][0];
            }

            throw new AuthenticationException(
                'Token request failed.',
                $response->status,
                $response->header('x-ms-request-id') ?: (\is_string($data['trace_id'] ?? null) ? $data['trace_id'] : ''),
                $error
            );
        }

        $this->accessToken = $data['access_token'];
        $this->expiresAt   = time() + max(60, (int) ($data['expires_in'] ?? 3600));

        return $this->accessToken;
    }

    /**
     * Forget the in-memory token.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function clear(): void
    {
        $this->accessToken = null;
        $this->expiresAt   = 0;
    }
}
