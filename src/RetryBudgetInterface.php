<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * A retry budget per key (usually one downstream service): retries are allowed only while
 * the service mostly succeeds. Token bucket as in gRPC retry throttling: a key starts with
 * maxTokens; each retry wanted costs 1; each success earns tokenRatio; a retry is allowed
 * only while the tokens left are above maxTokens / 2.
 */
interface RetryBudgetInterface
{
    /**
     * A retryable failure happened: spend 1 token (never below 0) and say whether a retry is
     * allowed, i.e. whether the tokens left after spending are > maxTokens / 2.
     */
    public function tryAcquire(string $key): bool;

    /** An attempt succeeded: add tokenRatio tokens, never above maxTokens. */
    public function recordSuccess(string $key): void;

    /** Tokens currently left for $key (maxTokens for a key never seen or expired). */
    public function tokens(string $key): float;
}
