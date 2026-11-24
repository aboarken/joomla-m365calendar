<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph\Certificate;

use M365Graph\ConnectionSettings;
use M365Graph\Exception\ConfigurationException;
use M365Graph\Jwt\Base64Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Loads the certificate and private key from files outside the web root.
 *
 * @since  0.1.0
 */
final class CertificateLoader
{
    /**
     * Constructor.
     *
     * @param   string  $webRoot  Web root the files must not be inside of.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly string $webRoot)
    {
    }

    /**
     * Load the certificate and return its DER encoding.
     *
     * Accepts PEM ("-----BEGIN CERTIFICATE-----") and binary DER (.cer) files.
     *
     * @param   string  $path  Certificate path.
     *
     * @return  string  DER bytes.
     *
     * @throws  ConfigurationException
     *
     * @since   0.1.0
     */
    public function loadCertificateDer(string $path): string
    {
        ConnectionSettings::assertSafeFile($path, $this->webRoot);

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, '-----BEGIN CERTIFICATE-----')) {
            $cert = self::quietly(static fn () => openssl_x509_read($contents));

            if ($cert === false || !openssl_x509_export($cert, $pem)) {
                throw new ConfigurationException('Certificate cannot be parsed.', 'cert_invalid');
            }

            if (!preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $pem, $m)) {
                throw new ConfigurationException('Certificate cannot be parsed.', 'cert_invalid');
            }

            $der = base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);

            if ($der === false || $der === '') {
                throw new ConfigurationException('Certificate cannot be parsed.', 'cert_invalid');
            }

            return $der;
        }

        // Binary DER: verify it parses.
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($contents), 64, "\n") . "-----END CERTIFICATE-----\n";

        if (self::quietly(static fn () => openssl_x509_read($pem)) === false) {
            throw new ConfigurationException('Certificate cannot be parsed.', 'cert_invalid');
        }

        return $contents;
    }

    /**
     * Base64url SHA-256 thumbprint of the certificate (JWT "x5t#S256" header).
     *
     * @param   string  $path  Certificate path.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function thumbprintS256(string $path): string
    {
        return Base64Url::encode(hash('sha256', $this->loadCertificateDer($path), true));
    }

    /**
     * Load the RSA private key.
     *
     * @param   string  $path  Key path.
     *
     * @return  \OpenSSLAsymmetricKey
     *
     * @throws  ConfigurationException
     *
     * @since   0.1.0
     */
    public function loadPrivateKey(string $path): \OpenSSLAsymmetricKey
    {
        ConnectionSettings::assertSafeFile($path, $this->webRoot);

        $pemKey = (string) file_get_contents($path);
        $key    = self::quietly(static fn () => openssl_pkey_get_private($pemKey));

        if ($key === false) {
            throw new ConfigurationException('Private key cannot be parsed (it must be an unencrypted PEM key).', 'key_invalid');
        }

        $details = openssl_pkey_get_details($key);

        if (!\is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($details['bits'] ?? 0) < 2048) {
            throw new ConfigurationException('Private key must be an RSA key of at least 2048 bits.', 'key_invalid');
        }

        return $key;
    }

    /**
     * Check that the private key belongs to the certificate.
     *
     * @param   string  $certificatePath  Certificate path.
     * @param   string  $privateKeyPath   Key path.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function keyMatchesCertificate(string $certificatePath, string $privateKeyPath): bool
    {
        $der = $this->loadCertificateDer($certificatePath);
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";

        return openssl_x509_check_private_key($pem, $this->loadPrivateKey($privateKeyPath));
    }

    /**
     * Run an OpenSSL call without emitting PHP warnings for unparsable input
     * (the caller turns a false result into a ConfigurationException), and
     * drain OpenSSL's error queue so stale errors do not leak into later calls.
     *
     * @param   \Closure  $call  The OpenSSL call.
     *
     * @return  mixed
     *
     * @since   0.1.2
     */
    private static function quietly(\Closure $call): mixed
    {
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            return $call();
        } finally {
            restore_error_handler();

            while (openssl_error_string() !== false) {
                // Discard.
            }
        }
    }
}
