<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\CircuitBreakerStoreInterface;
use Gohany\Retry\Testing\CircuitBreakerStoreContractTestCase;
use Gohany\Retry\Tests\Fixtures\InMemoryBreakerStore;

final class FixtureBreakerStoreTest extends CircuitBreakerStoreContractTestCase
{
    protected function createStore(): CircuitBreakerStoreInterface
    {
        return new InMemoryBreakerStore();
    }
}
