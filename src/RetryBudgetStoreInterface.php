<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Storage seam for a shared RetryBudgetInterface: one atomic operation on one key, so a
 * clustered store (e.g. Valkey in cluster mode) needs no multi-key script.
 */
interface RetryBudgetStoreInterface
{
    /**
     * Atomically: read $key (treated as $max when absent or expired), add $delta, clamp the
     * result to [0, $max], store it with a time-to-live of $ttlMs, and return it.
     */
    public function add(string $key, float $delta, float $max, int $ttlMs): float;
}
