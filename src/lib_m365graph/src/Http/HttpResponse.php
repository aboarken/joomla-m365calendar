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
 * Minimal HTTP response value object.
 *
 * @since  1.0.0
 */
final class HttpResponse
{
    /**
     * Lower-cased header names mapped to their (first) value.
     *
     * @var    array<string, string>
     * @since  1.0.0
     */
    private array $headers = [];

    /**
     * Constructor.
     *
     * @param   integer                $status   Status code.
     * @param   array<string, mixed>   $headers  Headers.
     * @param   string                 $body     Body.
     *
     * @since   1.0.0
     */
    public function __construct(public readonly int $status, array $headers, public readonly string $body)
    {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = \is_array($value) ? (string) reset($value) : (string) $value;
        }
    }

    /**
     * Header value (case-insensitive name).
     *
     * @param   string  $name  Header name.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /**
     * Decode a JSON body.
     *
     * @return  array<string, mixed>
     *
     * @since   1.0.0
     */
    public function json(): array
    {
        try {
            $data = json_decode($this->body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($data) ? $data : [];
    }
}
