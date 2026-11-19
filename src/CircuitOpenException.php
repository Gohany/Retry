<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Thrown (or reported as lastError()) when a circuit breaker refused the first attempt, so
 * there is no operation error to rethrow.
 *
 * The message is the same for every key and never contains it: breaker keys can carry tenant
 * or customer ids, and callers log exception messages. Read the key from key() and put it in
 * log context.
 */
final class CircuitOpenException extends \RuntimeException
{
    public function __construct(
        private readonly string $key,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Circuit open', 0, $previous);
    }

    public function key(): string
    {
        return $this->key;
    }
}
