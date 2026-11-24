<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Graph;

use M365Calendar\Tests\Support\FakeTransport;
use M365Calendar\Tests\Support\KeyPair;
use M365Graph\Auth\TokenProvider;
use M365Graph\Certificate\CertificateLoader;
use M365Graph\Client;
use M365Graph\ConnectionSettings;
use M365Graph\Exception\AccessDeniedException;
use M365Graph\Exception\AuthenticationException;
use M365Graph\Exception\GraphException;
use M365Graph\Exception\ThrottledException;
use M365Graph\Http\HttpResponse;
use M365Graph\Jwt\ClientAssertionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Graph client: token request, paging, Retry-After and error mapping.
 */
final class ClientTest extends TestCase
{
    private FakeTransport $transport;

    /** @var int[] */
    private array $sleeps = [];

    private function client(int $maxRetries = 3): Client
    {
        $keys     = KeyPair::create();
        $settings = new ConnectionSettings('11111111-2222-3333-4444-555555555555', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $keys['cert'], $keys['key']);

        $this->transport = new FakeTransport();
        $this->transport->queue(FakeTransport::json(200, ['access_token' => 'secret-token', 'expires_in' => 3599]));

        $tokens = new TokenProvider($settings, new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT)), $this->transport);

        return new Client($tokens, $this->transport, $maxRetries, function (int $s): void {
            $this->sleeps[] = $s;
        });
    }

    public function testTokenRequestUsesCertificateAssertion(): void
    {
        $client = $this->client();
        $client->authenticate();

        $request = $this->transport->requests[0];
        parse_str((string) $request['body'], $body);

        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/oauth2/v2.0/token', $request['url']);
        $this->assertSame('client_credentials', $body['grant_type']);
        $this->assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $body['client_assertion_type']);
        $this->assertSame('https://graph.microsoft.com/.default', $body['scope']);
        $this->assertNotEmpty($body['client_assertion']);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    public function testTokenIsReusedInMemory(): void
    {
        $client = $this->client();
        $this->transport->queue(
            FakeTransport::json(200, ['value' => []]),
            FakeTransport::json(200, ['value' => []])
        );

        iterator_to_array($client->getCollection('/users/a%40b.c/calendarView'));
        iterator_to_array($client->getCollection('/users/a%40b.c/calendarView'));

        $this->assertCount(3, $this->transport->requests, 'One token request, two Graph requests.');
        $this->assertSame('Bearer secret-token', $this->transport->requests[1]['headers']['Authorization']);
    }

    public function testFollowsNextLink(): void
    {
        $client = $this->client();
        $next   = 'https://graph.microsoft.com/v1.0/users/x/calendarView?$skiptoken=abc';
        $this->transport->queue(
            FakeTransport::json(200, ['value' => [['id' => '1'], ['id' => '2']], '@odata.nextLink' => $next]),
            FakeTransport::json(200, ['value' => [['id' => '3']]])
        );

        $ids = array_map(static fn (array $i): string => $i['id'], iterator_to_array($client->getCollection('/users/x/calendarView', ['$top' => 2]), false));

        $this->assertSame(['1', '2', '3'], $ids);
        $this->assertSame($next, $this->transport->requests[2]['url']);
    }

    public function testRejectsNextLinkToOtherHost(): void
    {
        $client = $this->client();
        $this->transport->queue(FakeTransport::json(200, ['value' => [], '@odata.nextLink' => 'https://evil.example/steal']));

        $this->expectException(GraphException::class);
        iterator_to_array($client->getCollection('/users/x/calendarView'));
    }

    public function testHonoursRetryAfterThenSucceeds(): void
    {
        $client = $this->client();
        $this->transport->queue(
            new HttpResponse(429, ['Retry-After' => '7'], ''),
            new HttpResponse(503, ['Retry-After' => '2'], ''),
            FakeTransport::json(200, ['value' => [['id' => '1']]])
        );

        $items = iterator_to_array($client->getCollection('/users/x/calendarView'), false);

        $this->assertCount(1, $items);
        $this->assertSame([7, 2], $this->sleeps);
    }

    public function testRetriesAreBounded(): void
    {
        $client = $this->client(2);
        $this->transport->queue(
            new HttpResponse(429, ['Retry-After' => '1'], ''),
            new HttpResponse(429, ['Retry-After' => '1'], ''),
            new HttpResponse(429, ['Retry-After' => '1', 'request-id' => 'req-123'], '')
        );

        try {
            iterator_to_array($client->getCollection('/users/x/calendarView'));
            $this->fail('Expected ThrottledException');
        } catch (ThrottledException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame('req-123', $e->getRequestId());
            $this->assertCount(2, $this->sleeps);
        }
    }

    public function testRetryAfterIsCapped(): void
    {
        $this->assertSame(Client::MAX_RETRY_AFTER, Client::retryDelay('100000', 1));
        $this->assertSame(1, Client::retryDelay('0', 1));
        $this->assertSame(4, Client::retryDelay('', 2));
    }

    public function testForbiddenIsMappedWithoutBody(): void
    {
        $client = $this->client();
        $this->transport->queue(FakeTransport::json(403, ['error' => ['code' => 'ErrorAccessDenied', 'message' => 'Mailbox secret@x']], ['request-id' => 'rid-1']));

        try {
            $client->get('/users/x/calendar');
            $this->fail('Expected AccessDeniedException');
        } catch (AccessDeniedException $e) {
            $this->assertSame('rid-1', $e->getRequestId());
            $this->assertSame('ErrorAccessDenied', $e->getErrorCode());
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    public function testTokenErrorDoesNotLeakDescription(): void
    {
        $client          = $this->client();
        $this->transport = new FakeTransport();
        $keys            = KeyPair::create();
        $settings        = new ConnectionSettings('11111111-2222-3333-4444-555555555555', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $keys['cert'], $keys['key']);
        $tokens          = new TokenProvider($settings, new ClientAssertionBuilder(new CertificateLoader(JPATH_ROOT)), $this->transport);
        $client          = new Client($tokens, $this->transport);

        $this->transport->queue(FakeTransport::json(401, ['error' => 'invalid_client', 'error_description' => 'AADSTS700027: details', 'error_codes' => [700027]]));

        try {
            $client->authenticate();
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('invalid_client AADSTS700027', $e->getErrorCode());
            $this->assertStringNotContainsString('details', $e->getMessage());
        }
    }

    public function testBuildUrlEncodesQuery(): void
    {
        $url = Client::buildUrl('/users/' . rawurlencode('a+b@example.com') . '/calendarView', ['$select' => 'id,subject', 'startDateTime' => '2026-01-01T00:00:00Z']);

        $this->assertSame(
            'https://graph.microsoft.com/v1.0/users/a%2Bb%40example.com/calendarView?%24select=id%2Csubject&startDateTime=2026-01-01T00%3A00%3A00Z',
            $url
        );
    }
}
