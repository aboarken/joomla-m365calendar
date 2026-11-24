<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Support;

/**
 * Creates a throw-away RSA key and self-signed certificate.
 */
final class KeyPair
{
    /**
     * Create files in a new temporary directory.
     *
     * @param   string|null  $dir  Directory (default: a new temp dir outside the fake web root).
     *
     * @return  array{dir: string, cert: string, key: string, der: string}
     */
    public static function create(?string $dir = null): array
    {
        $dir ??= sys_get_temp_dir() . '/m365calendar-keys-' . bin2hex(random_bytes(6));

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $key  = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr  = openssl_csr_new(['commonName' => 'm365calendar-test'], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);

        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($key, $keyPem);

        file_put_contents($dir . '/test.pem', $certPem);
        file_put_contents($dir . '/test.key', $keyPem);

        preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certPem, $m);
        $der = (string) base64_decode(preg_replace('/\s+/', '', $m[1]));
        file_put_contents($dir . '/test.cer', $der);

        return ['dir' => $dir, 'cert' => $dir . '/test.pem', 'key' => $dir . '/test.key', 'der' => $der];
    }
}
