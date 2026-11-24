<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Graph;

use M365Calendar\Tests\Support\KeyPair;
use M365Graph\Certificate\CertificateLoader;
use M365Graph\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Certificate and key validation, as used by the Options screen.
 *
 * PHPUnit is configured with failOnWarning, so these tests also prove that
 * unparsable files do not make OpenSSL emit PHP warnings.
 */
final class CertificateLoaderTest extends TestCase
{
    /** @var array{dir: string, cert: string, key: string, der: string} */
    private array $keys;

    private CertificateLoader $loader;

    protected function setUp(): void
    {
        $this->keys   = KeyPair::create();
        $this->loader = new CertificateLoader(JPATH_ROOT);
    }

    private function reason(callable $call): string
    {
        try {
            $call();
        } catch (ConfigurationException $e) {
            return $e->getReason();
        }

        return 'no exception';
    }

    public function testKeyFileIsNotACertificate(): void
    {
        $this->assertSame('cert_invalid', $this->reason(fn () => $this->loader->loadCertificateDer($this->keys['key'])));
    }

    public function testGarbageIsNotACertificate(): void
    {
        file_put_contents($this->keys['dir'] . '/garbage.cer', random_bytes(300));
        file_put_contents($this->keys['dir'] . '/garbage.pem', "-----BEGIN CERTIFICATE-----\nnot base64 at all\n-----END CERTIFICATE-----\n");

        $this->assertSame('cert_invalid', $this->reason(fn () => $this->loader->loadCertificateDer($this->keys['dir'] . '/garbage.cer')));
        $this->assertSame('cert_invalid', $this->reason(fn () => $this->loader->loadCertificateDer($this->keys['dir'] . '/garbage.pem')));
    }

    public function testCertificateIsNotAPrivateKey(): void
    {
        $this->assertSame('key_invalid', $this->reason(fn () => $this->loader->loadPrivateKey($this->keys['cert'])));
    }

    public function testWeakKeyIsRejected(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        file_put_contents($this->keys['dir'] . '/weak.key', $pem);

        $this->assertSame('key_invalid', $this->reason(fn () => $this->loader->loadPrivateKey($this->keys['dir'] . '/weak.key')));
    }

    public function testKeyOfAnotherCertificateDoesNotMatch(): void
    {
        $other = KeyPair::create();

        $this->assertTrue($this->loader->keyMatchesCertificate($this->keys['cert'], $this->keys['key']));
        $this->assertFalse($this->loader->keyMatchesCertificate($this->keys['cert'], $other['key']));
    }

    public function testFailedParsingLeavesNoOpenSslErrorsBehind(): void
    {
        $this->reason(fn () => $this->loader->loadCertificateDer($this->keys['key']));

        $this->assertFalse(openssl_error_string());
    }
}
