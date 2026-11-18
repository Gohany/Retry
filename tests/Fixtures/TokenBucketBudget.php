<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\RetryBudgetInterface;
use Gohany\Retry\RetryBudgetStoreInterface;

final class TokenBucketBudget implements RetryBudgetInterface
{
    public function __construct(
        private readonly RetryBudgetStoreInterface $store,
        private readonly float $maxTokens,
        private readonly float $tokenRatio,
        private readonly int $ttlMs = 600_000,
    ) {
    }

    public function tryAcquire(string $key): bool
    {
        return $this->store->add($key, -1.0, $this->maxTokens, $this->ttlMs) > $this->maxTokens / 2;
    }

    public function recordSuccess(string $key): void
    {
        $this->store->add($key, $this->tokenRatio, $this->maxTokens, $this->ttlMs);
    }

    public function tokens(string $key): float
    {
        return $this->store->add($key, 0.0, $this->maxTokens, $this->ttlMs);
    }
}
