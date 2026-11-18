<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\CircuitBreakerInterface;
use Gohany\Retry\CircuitBreakerStoreInterface;
use Gohany\Retry\CircuitState;
use Psr\Clock\ClockInterface;

/**
 * Count-in-window breaker whose whole state is one JSON value behind a versioned CAS.
 */
final class StoreBackedBreaker implements CircuitBreakerInterface
{
    public function __construct(
        private readonly CircuitBreakerStoreInterface $store,
        private readonly ClockInterface $clock,
        private readonly int $failureThreshold,
        private readonly int $windowMs,
        private readonly int $openMs,
    ) {
    }

    public function allow(string $key): bool
    {
        $allowed = false;
        $this->update($key, function (array $s, int $now) use (&$allowed): ?array {
            $allowed = false;
            if ($s['s'] === CircuitState::Closed->value) {
                $allowed = true;
                return null;
            }
            $since = $s['s'] === CircuitState::Open->value ? $s['o'] : $s['p'];
            if ($now - $since < $this->openMs) {
                return null;
            }
            $allowed = true;
            return ['s' => CircuitState::HalfOpen->value, 'f' => [], 'o' => $s['o'], 'p' => $now];
        });
        return $allowed;
    }

    public function recordSuccess(string $key): void
    {
        $this->update($key, static fn (array $s): ?array => $s['s'] === CircuitState::HalfOpen->value
            ? ['s' => CircuitState::Closed->value, 'f' => [], 'o' => 0, 'p' => 0]
            : null);
    }

    public function recordFailure(string $key): void
    {
        $this->update($key, function (array $s, int $now): ?array {
            if ($s['s'] === CircuitState::HalfOpen->value) {
                return ['s' => CircuitState::Open->value, 'f' => [], 'o' => $now, 'p' => 0];
            }
            if ($s['s'] === CircuitState::Open->value) {
                return null;
            }
            $failures = array_values(array_filter($s['f'], fn (int $t): bool => $now - $t <= $this->windowMs));
            $failures[] = $now;
            if (count($failures) >= $this->failureThreshold) {
                return ['s' => CircuitState::Open->value, 'f' => [], 'o' => $now, 'p' => 0];
            }
            return ['s' => CircuitState::Closed->value, 'f' => $failures, 'o' => 0, 'p' => 0];
        });
    }

    public function state(string $key): CircuitState
    {
        return CircuitState::from($this->read($key)[1]['s']);
    }

    /** @return array{?int, array{s:string,f:list<int>,o:int,p:int}} */
    private function read(string $key): array
    {
        $row = $this->store->load($key);
        if ($row === null) {
            return [null, ['s' => CircuitState::Closed->value, 'f' => [], 'o' => 0, 'p' => 0]];
        }
        return [$row->version(), json_decode($row->value(), true, 4, JSON_THROW_ON_ERROR)];
    }

    /** @param callable(array{s:string,f:list<int>,o:int,p:int}, int): ?array $change null = no write */
    private function update(string $key, callable $change): void
    {
        for ($try = 0; $try < 100; $try++) {
            [$version, $state] = $this->read($key);
            $next = $change($state, Scheduler::nowMs($this->clock));
            if ($next === null) {
                return;
            }
            $ttl = $this->windowMs + 2 * $this->openMs;
            if ($this->store->compareAndSet($key, $version, json_encode($next, JSON_THROW_ON_ERROR), $ttl)) {
                return;
            }
        }
        throw new \RuntimeException('Breaker state for ' . $key . ' kept changing underneath');
    }
}
