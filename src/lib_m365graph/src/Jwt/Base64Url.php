<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph\Jwt;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Base64url encoding without padding (RFC 7515, section 2).
 *
 * @since  1.0.0
 */
final class Base64Url
{
    /**
     * Encode binary data.
     *
     * @param   string  $data  Binary data.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode base64url data.
     *
     * @param   string  $data  Encoded data.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function decode(string $data): string
    {
        $remainder = \strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
