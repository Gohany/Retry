<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\CircuitBreakerStoreInterface;
use Gohany\Retry\VersionedValue;

/** Single process; ignores the TTL. */
final class InMemoryBreakerStore implements CircuitBreakerStoreInterface
{
    /** @var array<string,array{int,string}> */
    private array $rows = [];

    public function load(string $key): ?VersionedValue
    {
        return isset($this->rows[$key]) ? new VersionedValue($this->rows[$key][0], $this->rows[$key][1]) : null;
    }

    public function compareAndSet(string $key, ?int $expectedVersion, string $value, int $ttlMs): bool
    {
        $current = $this->rows[$key][0] ?? null;
        if ($current !== $expectedVersion) {
            return false;
        }
        $this->rows[$key] = [($current ?? 0) + 1, $value];
        return true;
    }
}
