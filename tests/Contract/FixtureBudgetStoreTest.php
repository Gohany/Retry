<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\RetryBudgetStoreInterface;
use Gohany\Retry\Testing\RetryBudgetStoreContractTestCase;
use Gohany\Retry\Tests\Fixtures\InMemoryBudgetStore;

final class FixtureBudgetStoreTest extends RetryBudgetStoreContractTestCase
{
    protected function createStore(): RetryBudgetStoreInterface
    {
        return new InMemoryBudgetStore();
    }
}
