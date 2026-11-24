<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Support;

use M365Graph\Http\HttpResponse;
use M365Graph\Http\HttpTransportInterface;

/**
 * Transport returning queued responses and recording requests.
 */
final class FakeTransport implements HttpTransportInterface
{
    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @var HttpResponse[] */
    private array $queue = [];

    public function queue(HttpResponse ...$responses): self
    {
        array_push($this->queue, ...$responses);

        return $this;
    }

    public static function json(int $status, array $data, array $headers = []): HttpResponse
    {
        return new HttpResponse($status, $headers, (string) json_encode($data));
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        if ($this->queue === []) {
            throw new \RuntimeException('No response queued for ' . $url);
        }

        return array_shift($this->queue);
    }
}
