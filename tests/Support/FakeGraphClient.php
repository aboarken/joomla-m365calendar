<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Support;

use M365Graph\Exception\GraphException;
use M365Graph\GraphClientInterface;

/**
 * Graph client double: returns items per path or throws per path.
 */
final class FakeGraphClient implements GraphClientInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $collections = [];

    /** @var array<string, GraphException> */
    public array $failures = [];

    /** @var array<int, array{path: string, query: array<string, scalar>, headers: array<string, string>}> */
    public array $calls = [];

    public ?GraphException $authFailure = null;

    public function authenticate(): void
    {
        if ($this->authFailure !== null) {
            throw $this->authFailure;
        }
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        $this->calls[] = ['path' => $path, 'query' => $query, 'headers' => $headers];

        if (isset($this->failures[$path])) {
            throw $this->failures[$path];
        }

        return ['id' => 'x'];
    }

    public function getCollection(string $path, array $query = [], array $headers = []): \Generator
    {
        $this->calls[] = ['path' => $path, 'query' => $query, 'headers' => $headers];

        foreach ($this->collections[$path] ?? [] as $item) {
            yield $item;

            // Fail after the first page to prove partial data is never stored.
            if (isset($this->failures[$path])) {
                throw $this->failures[$path];
            }
        }

        if (isset($this->failures[$path])) {
            throw $this->failures[$path];
        }
    }
}
