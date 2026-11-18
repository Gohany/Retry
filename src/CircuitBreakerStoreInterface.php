<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Storage seam for a shared CircuitBreakerInterface: a versioned compare-and-set on one key.
 * The value is opaque to the store; the breaker serialises its own state into it.
 */
interface CircuitBreakerStoreInterface
{
    /** The current value and version, or null when absent or expired. */
    public function load(string $key): ?VersionedValue;

    /**
     * Atomically store $value with a time-to-live of $ttlMs if the key's version is still
     * $expectedVersion (null: the key must be absent). The stored version is 1 on create and
     * goes up by 1 on every successful write. Returns false, writing nothing, otherwise.
     */
    public function compareAndSet(string $key, ?int $expectedVersion, string $value, int $ttlMs): bool;
}
