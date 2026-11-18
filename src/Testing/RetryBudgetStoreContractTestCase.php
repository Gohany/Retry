<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\RetryBudgetStoreInterface;
use PHPUnit\Framework\TestCase;

/**
 * SPEC §5.16: an atomic clamped add on one key.
 */
abstract class RetryBudgetStoreContractTestCase extends TestCase
{
    protected const TTL_MS = 60_000;

    abstract protected function createStore(): RetryBudgetStoreInterface;

    public function testAnAbsentKeyStartsAtTheMaximum(): void
    {
        self::assertSame(9.0, $this->createStore()->add('k', -1, 10, self::TTL_MS));
    }

    public function testClampsToZeroAndTheMaximum(): void
    {
        $store = $this->createStore();
        self::assertSame(10.0, $store->add('k', 5, 10, self::TTL_MS));
        self::assertSame(0.0, $store->add('k', -100, 10, self::TTL_MS));
        self::assertSame(0.0, $store->add('k', -1, 10, self::TTL_MS));
        self::assertEqualsWithDelta(0.25, $store->add('k', 0.25, 10, self::TTL_MS), 1e-9);
    }

    public function testAddingZeroReadsTheValue(): void
    {
        $store = $this->createStore();
        $store->add('k', -3, 10, self::TTL_MS);
        self::assertSame(7.0, $store->add('k', 0, 10, self::TTL_MS));
    }

    public function testKeysAreIndependent(): void
    {
        $store = $this->createStore();
        $store->add('a', -4, 10, self::TTL_MS);
        self::assertSame(10.0, $store->add('b', 0, 10, self::TTL_MS));
    }
}
