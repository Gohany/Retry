<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\CircuitBreakerStoreInterface;
use PHPUnit\Framework\TestCase;

/**
 * SPEC §5.19: a versioned compare-and-set on one key.
 */
abstract class CircuitBreakerStoreContractTestCase extends TestCase
{
    protected const TTL_MS = 60_000;

    abstract protected function createStore(): CircuitBreakerStoreInterface;

    public function testAnAbsentKeyLoadsNull(): void
    {
        self::assertNull($this->createStore()->load('k'));
    }

    public function testCreateOnlyWhenAbsent(): void
    {
        $store = $this->createStore();
        self::assertTrue($store->compareAndSet('k', null, 'first', self::TTL_MS));
        self::assertFalse($store->compareAndSet('k', null, 'second', self::TTL_MS));

        $loaded = $store->load('k');
        self::assertNotNull($loaded);
        self::assertSame(1, $loaded->version());
        self::assertSame('first', $loaded->value());
    }

    public function testVersionGoesUpByOneOnEachWrite(): void
    {
        $store = $this->createStore();
        $store->compareAndSet('k', null, 'v1', self::TTL_MS);
        self::assertTrue($store->compareAndSet('k', 1, 'v2', self::TTL_MS));
        self::assertTrue($store->compareAndSet('k', 2, 'v3', self::TTL_MS));

        $loaded = $store->load('k');
        self::assertNotNull($loaded);
        self::assertSame(3, $loaded->version());
        self::assertSame('v3', $loaded->value());
    }

    public function testAStaleVersionWritesNothing(): void
    {
        $store = $this->createStore();
        $store->compareAndSet('k', null, 'v1', self::TTL_MS);
        $store->compareAndSet('k', 1, 'v2', self::TTL_MS);

        self::assertFalse($store->compareAndSet('k', 1, 'stale', self::TTL_MS));
        self::assertFalse($store->compareAndSet('k', 5, 'future', self::TTL_MS));

        $loaded = $store->load('k');
        self::assertNotNull($loaded);
        self::assertSame([2, 'v2'], [$loaded->version(), $loaded->value()]);
    }

    public function testAVersionOnAnAbsentKeyFails(): void
    {
        $store = $this->createStore();
        self::assertFalse($store->compareAndSet('k', 1, 'v', self::TTL_MS));
        self::assertNull($store->load('k'));
    }

    public function testValuesAreOpaqueBytes(): void
    {
        $store = $this->createStore();
        $value = "{\"s\":\"open\"}\0\xff";
        $store->compareAndSet('k', null, $value, self::TTL_MS);
        self::assertSame($value, $store->load('k')?->value());
    }

    public function testKeysAreIndependent(): void
    {
        $store = $this->createStore();
        $store->compareAndSet('a', null, 'x', self::TTL_MS);
        self::assertNull($store->load('b'));
        self::assertTrue($store->compareAndSet('b', null, 'y', self::TTL_MS));
    }
}
