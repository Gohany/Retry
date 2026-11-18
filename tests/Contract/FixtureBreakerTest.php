<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\CircuitBreakerInterface;
use Gohany\Retry\Testing\CircuitBreakerContractTestCase;
use Gohany\Retry\Testing\FrozenClock;
use Gohany\Retry\Tests\Fixtures\InMemoryBreakerStore;
use Gohany\Retry\Tests\Fixtures\StoreBackedBreaker;

final class FixtureBreakerTest extends CircuitBreakerContractTestCase
{
    protected function createBreaker(FrozenClock $clock, int $failureThreshold, int $windowMs, int $openMs): CircuitBreakerInterface
    {
        return new StoreBackedBreaker(new InMemoryBreakerStore(), $clock, $failureThreshold, $windowMs, $openMs);
    }

    protected function createSharedBreakers(FrozenClock $clock, int $failureThreshold, int $windowMs, int $openMs): array
    {
        $store = new InMemoryBreakerStore();
        return [
            new StoreBackedBreaker($store, $clock, $failureThreshold, $windowMs, $openMs),
            new StoreBackedBreaker($store, $clock, $failureThreshold, $windowMs, $openMs),
        ];
    }
}
