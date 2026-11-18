<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Psr\Clock\ClockInterface;

/**
 * A PSR-20 clock that only moves when told to. Millisecond resolution.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private int $unixMs = 1_800_000_000_000)
    {
    }

    public function now(): \DateTimeImmutable
    {
        $seconds = intdiv($this->unixMs, 1000);
        $micros = ($this->unixMs % 1000) * 1000;
        return (new \DateTimeImmutable('@' . $seconds))->modify('+' . $micros . ' microseconds');
    }

    public function nowUnixMs(): int
    {
        return $this->unixMs;
    }

    public function advanceMs(int $milliseconds): void
    {
        if ($milliseconds < 0) {
            throw new \InvalidArgumentException('A clock does not go backwards');
        }
        $this->unixMs += $milliseconds;
    }
}
