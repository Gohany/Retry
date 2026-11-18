<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * What one attempt produced, as a decider and a scheduler see it.
 */
interface AttemptOutcomeInterface
{
    /** True when the operation returned successfully. */
    public function isSuccess(): bool;

    /** Value on success, null otherwise. */
    public function result(): mixed;

    /** Error on failure, or null when successful. */
    public function error(): ?\Throwable;

    /** Optional numeric status (e.g. an HTTP status code) for decider logic. */
    public function statusCode(): ?int;

    /**
     * Adapter tags such as 'ETIMEDOUT' or 'DEADLOCK'. Compared case-insensitively.
     *
     * @return list<string>
     */
    public function tags(): array;

    /**
     * Unix epoch milliseconds before which the next attempt must not start, or null.
     * Set from Retry-After (seconds or HTTP-date) or RateLimit-Reset by whoever builds the outcome.
     */
    public function notBeforeUnixMs(): ?int;
}
