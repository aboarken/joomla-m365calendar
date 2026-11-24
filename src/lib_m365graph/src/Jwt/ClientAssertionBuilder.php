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

use M365Graph\Certificate\CertificateLoader;
use M365Graph\ConnectionSettings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the RS256-signed client assertion JWT for the client credentials flow.
 *
 * @link   https://learn.microsoft.com/entra/identity-platform/certificate-credentials
 * @since  1.0.0
 */
final class ClientAssertionBuilder
{
    /**
     * Maximum lifetime of an assertion, in seconds (10 minutes).
     *
     * @var    integer
     * @since  1.0.0
     */
    public const MAX_LIFETIME = 600;

    /**
     * Clock returning a UNIX timestamp.
     *
     * @var    \Closure(): int
     * @since  1.0.0
     */
    private \Closure $clock;

    /**
     * Constructor.
     *
     * @param   CertificateLoader   $loader    Certificate loader.
     * @param   integer             $lifetime  Lifetime in seconds (capped at 600).
     * @param   (\Closure(): int)|null  $clock  Clock for tests.
     *
     * @since   1.0.0
     */
    public function __construct(
        private readonly CertificateLoader $loader,
        private readonly int $lifetime = 300,
        ?\Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Token endpoint for a tenant.
     *
     * @param   string  $tenantId  Tenant ID or domain.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function tokenEndpoint(string $tenantId): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
    }

    /**
     * Build a signed assertion.
     *
     * @param   ConnectionSettings  $settings  Connection settings.
     *
     * @return  string  The compact JWS.
     *
     * @throws  \M365Graph\Exception\ConfigurationException
     * @throws  \RuntimeException
     *
     * @since   1.0.0
     */
    public function build(ConnectionSettings $settings): string
    {
        $now      = ($this->clock)();
        $lifetime = max(60, min(self::MAX_LIFETIME, $this->lifetime));

        $header = [
            'alg'      => 'RS256',
            'typ'      => 'JWT',
            'x5t#S256' => $this->loader->thumbprintS256($settings->certificatePath),
        ];

        $claims = [
            'aud' => self::tokenEndpoint($settings->tenantId),
            'iss' => $settings->clientId,
            'sub' => $settings->clientId,
            'jti' => self::uuidV4(),
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + $lifetime,
        ];

        $signingInput = Base64Url::encode(self::json($header)) . '.' . Base64Url::encode(self::json($claims));
        $key          = $this->loader->loadPrivateKey($settings->privateKeyPath);

        if (!openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Signing the client assertion failed.');
        }

        return $signingInput . '.' . Base64Url::encode($signature);
    }

    /**
     * Random RFC 4122 version 4 UUID.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function uuidV4(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * JSON encode for JWT segments.
     *
     * @param   array<string, mixed>  $data  Data.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
