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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Thin transport abstraction so the Graph client can be tested without network.
 *
 * @since  1.0.0
 */
interface HttpTransportInterface
{
    /**
     * Send a request.
     *
     * @param   string                 $method   GET or POST.
     * @param   string                 $url      Absolute https URL.
     * @param   array<string, string>  $headers  Request headers.
     * @param   string|null            $body     Body for POST.
     * @param   integer                $timeout  Timeout in seconds.
     *
     * @return  HttpResponse
     *
     * @throws  \RuntimeException  On transport errors.
     *
     * @since   1.0.0
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): HttpResponse;
}
