<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * A value read from CircuitBreakerStoreInterface with the version to compare-and-set against.
 */
final class VersionedValue
{
    public function __construct(
        private readonly int $version,
        private readonly string $value,
    ) {
    }

    public function version(): int
    {
        return $this->version;
    }

    public function value(): string
    {
        return $this->value;
    }
}
