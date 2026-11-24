<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that keeps entries in memory.
 */
final class ArrayLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
    public array $logs = [];

    /**
     * @param  mixed                 $level
     * @param  array<string, mixed>  $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logs[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
