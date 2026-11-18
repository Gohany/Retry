<?php declare(strict_types=1);

namespace Gohany\Retry;

/**
 * A circuit breaker per key (usually one downstream service), closed / open / half-open.
 *
 * Closed: allow() is true; the circuit opens when failures in the window reach the
 * implementation's threshold (a count, a rate, or both). Open: allow() is false until the
 * open period ends. Half-open: exactly one caller, across every process sharing the state,
 * gets allow() true (the probe); its recordSuccess() closes the circuit, its recordFailure()
 * opens it again. A probe that never reports is replaced after another open period.
 */
interface CircuitBreakerInterface
{
    /** Whether a call to $key may go ahead now. In half-open, true for the probe only. */
    public function allow(string $key): bool;

    public function recordSuccess(string $key): void;

    public function recordFailure(string $key): void;

    public function state(string $key): CircuitState;
}
