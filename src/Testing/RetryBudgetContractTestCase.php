<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\RetryBudgetInterface;
use PHPUnit\Framework\TestCase;

/**
 * SPEC §5.15: token bucket per key; refuses at or below half.
 */
abstract class RetryBudgetContractTestCase extends TestCase
{
    abstract protected function createBudget(float $maxTokens, float $tokenRatio): RetryBudgetInterface;

    public function testANewKeyStartsFull(): void
    {
        self::assertSame(10.0, $this->createBudget(10, 0.1)->tokens('svc'));
    }

    public function testRefusesOnceTokensFallToHalf(): void
    {
        $budget = $this->createBudget(10, 0.1);

        $allowed = [];
        for ($i = 0; $i < 6; $i++) {
            $allowed[] = $budget->tryAcquire('svc');
        }

        // 9, 8, 7, 6 are above 5; 5 and 4 are not.
        self::assertSame([true, true, true, true, false, false], $allowed);
        self::assertEqualsWithDelta(4.0, $budget->tokens('svc'), 1e-9);
    }

    public function testNeverGoesBelowZero(): void
    {
        $budget = $this->createBudget(2, 0.5);
        for ($i = 0; $i < 5; $i++) {
            $budget->tryAcquire('svc');
        }
        self::assertSame(0.0, $budget->tokens('svc'));
    }

    public function testSuccessesRefillUpToTheMaximum(): void
    {
        $budget = $this->createBudget(10, 0.5);
        for ($i = 0; $i < 5; $i++) {
            $budget->tryAcquire('svc');
        }
        self::assertFalse($budget->tryAcquire('svc')); // 4 left

        for ($i = 0; $i < 4; $i++) {
            $budget->recordSuccess('svc'); // 6
        }
        self::assertEqualsWithDelta(6.0, $budget->tokens('svc'), 1e-9);
        self::assertFalse($budget->tryAcquire('svc')); // 5 is not above 5

        for ($i = 0; $i < 4; $i++) {
            $budget->recordSuccess('svc'); // 7
        }
        self::assertTrue($budget->tryAcquire('svc')); // 6

        for ($i = 0; $i < 100; $i++) {
            $budget->recordSuccess('svc');
        }
        self::assertSame(10.0, $budget->tokens('svc'));
    }

    public function testKeysAreIndependent(): void
    {
        $budget = $this->createBudget(4, 0.1);
        $budget->tryAcquire('a');
        $budget->tryAcquire('a');
        self::assertFalse($budget->tryAcquire('a'));
        self::assertTrue($budget->tryAcquire('b'));
        self::assertSame(3.0, $budget->tokens('b'));
    }
}
