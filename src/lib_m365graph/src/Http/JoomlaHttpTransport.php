<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph\Http;

use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Transport using Joomla's HTTP client (respects the site's proxy settings).
 *
 * @since  1.0.0
 */
final class JoomlaHttpTransport implements HttpTransportInterface
{
    /**
     * {@inheritDoc}
     *
     * @since   1.0.0
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): HttpResponse
    {
        if (!str_starts_with($url, 'https://')) {
            throw new \RuntimeException('Only https URLs are allowed.');
        }

        $options = new Registry(['follow_location' => false]);
        $http    = HttpFactory::getHttp($options);

        $response = strtoupper($method) === 'POST'
            ? $http->post($url, (string) $body, $headers, $timeout)
            : $http->get($url, $headers, $timeout);

        return new HttpResponse(
            $response->getStatusCode(),
            array_map(static fn (array $values): string => implode(', ', $values), $response->getHeaders()),
            (string) $response->getBody()
        );
    }
}
