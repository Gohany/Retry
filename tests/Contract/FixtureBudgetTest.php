<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\RetryBudgetInterface;
use Gohany\Retry\Testing\RetryBudgetContractTestCase;
use Gohany\Retry\Tests\Fixtures\InMemoryBudgetStore;
use Gohany\Retry\Tests\Fixtures\TokenBucketBudget;

final class FixtureBudgetTest extends RetryBudgetContractTestCase
{
    protected function createBudget(float $maxTokens, float $tokenRatio): RetryBudgetInterface
    {
        return new TokenBucketBudget(new InMemoryBudgetStore(), $maxTokens, $tokenRatio);
    }
}
