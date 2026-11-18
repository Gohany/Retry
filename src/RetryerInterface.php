<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Executes an operation under a retry policy, sleeping between attempts.
 *
 * Two methods, one return type each: try() for callers who want the value or the exception,
 * tryOutcome() for callers who want to know why it gave up.
 */
interface RetryerInterface
{
    /**
     * @template TReturn
     * @param callable(AttemptContextInterface): TReturn $operation Receives the attempt context.
     * @param array<string,mixed> $context Free-form key/value tags, passed to every attempt.
     * @return TReturn The operation's value.
     * @throws \Throwable The last attempt's error when the retryer gives up (the same instance
     *                    tryOutcome() would report as lastError()).
     */
    public function try(callable $operation, RetryPolicyInterface $policy, array $context = []): mixed;

    /**
     * Same run as try(), but never throws the operation's error: it is reported instead.
     *
     * @param callable(AttemptContextInterface): mixed $operation
     * @param array<string,mixed> $context
     */
    public function tryOutcome(callable $operation, RetryPolicyInterface $policy, array $context = []): RetryResultInterface;
}
