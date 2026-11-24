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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read-only Microsoft Graph client.
 *
 * @since  0.1.0
 */
interface GraphClientInterface
{
    /**
     * GET a single resource.
     *
     * @param   string                 $path     Path below /v1.0, e.g. "/users/x/calendars". Must be URL-encoded.
     * @param   array<string, scalar>  $query    Query parameters.
     * @param   array<string, string>  $headers  Extra headers.
     *
     * @return  array<string, mixed>
     *
     * @throws  Exception\GraphException
     *
     * @since   0.1.0
     */
    public function get(string $path, array $query = [], array $headers = []): array;

    /**
     * GET a collection and follow @odata.nextLink, yielding each item.
     *
     * @param   string                 $path     Path below /v1.0. Must be URL-encoded.
     * @param   array<string, scalar>  $query    Query parameters for the first page.
     * @param   array<string, string>  $headers  Extra headers.
     *
     * @return  \Generator<int, array<string, mixed>>
     *
     * @throws  Exception\GraphException
     *
     * @since   0.1.0
     */
    public function getCollection(string $path, array $query = [], array $headers = []): \Generator;

    /**
     * Obtain a token to verify the connection settings.
     *
     * @return  void
     *
     * @throws  Exception\GraphException
     *
     * @since   0.1.0
     */
    public function authenticate(): void;
}
