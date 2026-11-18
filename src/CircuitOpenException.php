<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * Thrown (or reported as lastError()) when a circuit breaker refused the first attempt, so
 * there is no operation error to rethrow.
 */
final class CircuitOpenException extends \RuntimeException
{
    public function __construct(
        private readonly string $key,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Circuit open for ' . $key, 0, $previous);
    }

    public function key(): string
    {
        return $this->key;
    }
}
