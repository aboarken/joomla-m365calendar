<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Graph;

use M365Calendar\Tests\Support\KeyPair;
use M365Graph\Certificate\CertificateLoader;
use M365Graph\ConnectionSettings;
use M365Graph\Exception\ConfigurationException;
use M365Graph\Jwt\Base64Url;
use M365Graph\Jwt\ClientAssertionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * JWT client assertion (RS256, x5t#S256).
 */
final class ClientAssertionBuilderTest extends TestCase
{
    private const TENANT = '11111111-2222-3333-4444-555555555555';
    private const CLIENT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    /** @var array{dir: string, cert: string, key: string, der: string} */
    private array $keys;

    protected function setUp(): void
    {
        $this->keys = KeyPair::create();
    }

    private function settings(?string $cert = null, ?string $key = null): ConnectionSettings
    {
        return new ConnectionSettings(self::TENANT, self::CLIENT, $cert ?? $this->keys['cert'], $key ?? $this->keys['key']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string}
     */
    private function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        return [
            json_decode(Base64Url::decode($parts[0]), true),
            json_decode(Base64Url::decode($parts[1]), true),
            $parts[0] . '.' . $parts[1],
            Base64Url::decode($parts[2]),
        ];
    }

    public function testHeaderAndClaims(): void
    {
        $now     = 1_800_000_000;
        $builder = new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT), 300, static fn (): int => $now);

        [$header, $claims] = $this->decode($builder->build($this->settings()));

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
        $this->assertSame(Base64Url::encode(hash('sha256', $this->keys['der'], true)), $header['x5t#S256']);
        $this->assertArrayNotHasKey('x5t', $header, 'Only the SHA-256 thumbprint is sent.');

        $this->assertSame('https://login.microsoftonline.com/' . self::TENANT . '/oauth2/v2.0/token', $claims['aud']);
        $this->assertSame(self::CLIENT, $claims['iss']);
        $this->assertSame(self::CLIENT, $claims['sub']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $claims['jti']);
        $this->assertSame($now, $claims['nbf']);
        $this->assertSame($now + 300, $claims['exp']);
    }

    public function testSignatureVerifiesWithCertificate(): void
    {
        $builder = new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT));

        [, , $signingInput, $signature] = $this->decode($builder->build($this->settings()));

        $publicKey = openssl_pkey_get_public((string) file_get_contents($this->keys['cert']));
        $this->assertSame(1, openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function testLifetimeIsCappedAtTenMinutes(): void
    {
        $builder = new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT), 3600, static fn (): int => 1000);

        [, $claims] = $this->decode($builder->build($this->settings()));

        $this->assertSame(1000 + 600, $claims['exp']);
        $this->assertLessThanOrEqual(600, $claims['exp'] - $claims['nbf']);
    }

    public function testJtiIsUniquePerAssertion(): void
    {
        $builder = new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT));

        [, $a] = $this->decode($builder->build($this->settings()));
        [, $b] = $this->decode($builder->build($this->settings()));

        $this->assertNotSame($a['jti'], $b['jti']);
    }

    public function testDerCertificateGivesSameThumbprint(): void
    {
        $loader = new CertificateLoader(JPATH_ROOT);

        $this->assertSame($loader->thumbprintS256($this->keys['cert']), $loader->thumbprintS256($this->keys['dir'] . '/test.cer'));
        $this->assertTrue($loader->keyMatchesCertificate($this->keys['cert'], $this->keys['key']));
    }

    public function testFilesInsideWebRootAreRejected(): void
    {
        $inside = KeyPair::create(JPATH_ROOT . '/keys-' . bin2hex(random_bytes(4)));

        try {
            (new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT)))->build($this->settings($inside['cert'], $inside['key']));
            $this->fail('Expected a ConfigurationException.');
        } catch (ConfigurationException $e) {
            $this->assertSame('path_in_webroot', $e->getReason());
        }
    }

    public function testMissingFileIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        (new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT)))->build($this->settings($this->keys['dir'] . '/missing.pem'));
    }

    public function testSettingsValidation(): void
    {
        $this->settings()->validate(JPATH_ROOT);
        $this->assertTrue(ConnectionSettings::isValidTenantId('contoso.onmicrosoft.com'));
        $this->assertFalse(ConnectionSettings::isValidTenantId('../../etc'));

        $this->expectException(ConfigurationException::class);
        (new ConnectionSettings(self::TENANT, 'not-a-guid', $this->keys['cert'], $this->keys['key']))->validate(JPATH_ROOT);
    }
}
