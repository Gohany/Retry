<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\RetryBudgetStoreInterface;

/** Single process; ignores the TTL. */
final class InMemoryBudgetStore implements RetryBudgetStoreInterface
{
    /** @var array<string,float> */
    private array $values = [];

    public function add(string $key, float $delta, float $max, int $ttlMs): float
    {
        $value = min($max, max(0.0, ($this->values[$key] ?? $max) + $delta));
        return $this->values[$key] = $value;
    }
}
