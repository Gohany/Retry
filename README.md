# Gohany/Retry — retry interfaces and the `rtry:` policy string

A small set of PHP interfaces for retrying operations, a compact `rtry:` string you can store in
one column and compile at runtime, a scheduling call for retries you store instead of sleep
through, and contracts for a per-key retry budget and circuit breaker that several processes can
share. Implementations live elsewhere; [Gohany/Rtry](https://github.com/Gohany/Rtry) is one.

Requires PHP 8.2 or later. Version 2 breaks version 1: see [CHANGELOG.md](CHANGELOG.md).

## Features

- **Two ways to run**: `try()` returns the operation's value and rethrows its last error;
  `tryOutcome()` returns a `RetryResultInterface` that also says why it gave up.
- **One-column policies**: `rtry:` strings for attempts, backoff, cap, jitter, deadline,
  retryable failures, Retry-After and hedging hints. Policies are immutable, so one instance is
  safe to share across requests in a long-running worker.
- **Jittered by default**: a policy without `j` gets full jitter.
- **Retry-After honoured**: an outcome carries `notBeforeUnixMs()`; the next attempt waits for it.
- **Only retryable failures retry**: by default transport errors, 408, 429 and 5xx.
- **Durable retries**: `RetrySchedulerInterface::next()` returns "retry after N ms" or "give up,
  because …" without sleeping.
- **Load shedding**: `RetryBudgetInterface` (gRPC-style retry throttling) and
  `CircuitBreakerInterface` (closed / open / half-open, one probe), with atomic single-key store
  seams.
- **Testable**: inject a `SleeperInterface`, a PSR-20 clock and a seeded `\Random\Randomizer`;
  run the shipped contract test cases against your implementation.

## Install

```bash
composer require gohany/retry
```

## Quick start

Any implementation of the interfaces works the same way. How a thrown error becomes a status
code and tags (so `on` can match it) is the implementation's job.

```php
use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\RetryerInterface;

/** @var RetryPolicyFactoryInterface $factory */
/** @var RetryerInterface $retryer */

// Up to 5 attempts; 250 ms, 500 ms, 1 s, 2 s before jitter; capped at 5 s; full jitter;
// retry only 5xx, 429 and timeouts.
$policy = $factory->fromSpec('rtry:a=5;d=250ms;m=exp;b=2;cap=5s;j=100%@full;on=5xx,429,ETIMEDOUT');

$response = $retryer->try(
    fn ($attempt) => $httpClient->sendRequest($request),
    $policy,
    ['correlation_id' => 'req-abc123'],
);
```

When the reason matters, ask for the outcome instead:

```php
use Gohany\Retry\GiveUpReason;

$result = $retryer->tryOutcome(fn ($attempt) => $mailer->send($message), $policy);

if (!$result->isSuccess() && $result->giveUpReason() === GiveUpReason::NotRetryable) {
    // A 4xx: fix the request, do not queue it again.
}
```

## Durable retries

A retry stored as a row or a queue message must not sleep in a web request. Ask the scheduler
for the delay and store it:

```php
use Gohany\Retry\RetrySchedulerInterface;

/** @var RetrySchedulerInterface $scheduler  built with a clock and a Randomizer */
$decision = $scheduler->next($policy, $outcome, $attemptContext);

if ($decision->retry()) {
    $job->nextAttemptAt = $now->modify('+' . $decision->delayMs() . ' milliseconds');
} else {
    $job->failedBecause = $decision->giveUpReason()->value;
}
```

## Retry budgets and circuit breakers

Both are per key, usually one downstream service.

```php
if (!$breaker->allow('payments-api')) {
    // Open: fail fast instead of adding load.
}

try {
    $result = $call();
    $breaker->recordSuccess('payments-api');
    $budget->recordSuccess('payments-api');
} catch (\Throwable $e) {
    $breaker->recordFailure('payments-api');
    if ($budget->tryAcquire('payments-api')) {
        // still within budget: schedule a retry
    }
}
```

To share state between processes, back them with `RetryBudgetStoreInterface` (an atomic clamped
add) and `CircuitBreakerStoreInterface` (a versioned compare-and-set). Each call touches one key.

## The `rtry:` string

```
rtry:a=4;d=100;m=exp;b=2;cap=2s;j=100%@full;dl=10s;on=default;fh=1
```

| Key | Meaning | Default |
|---|---|---|
| `a` | attempts, including the first | required |
| `d` | base delay (first retry in `exp`, step in `lin`) | required for `exp`, `lin` |
| `m` | `exp`, `lin` or `seq` | `exp` |
| `b` | exponential factor | 2 |
| `seq` | explicit delays, `*` repeats the last | required for `seq` |
| `cap` | cap on the delay before jitter | none |
| `j` | jitter, `<window>[@full\|pm\|none]` | `100%@full` |
| `t` | advisory per-attempt timeout | none |
| `dl` | deadline from the start of attempt 1 | none |
| `on` | retryable statuses, families and tags | `default` |
| `sa` | delay before attempt 1 | 0 |
| `fh` | honour Retry-After / RateLimit-Reset | 1 |
| `h` | hedging hint, `<lanes>@<stagger>[&1]` | none |

More examples: `rtry:a=3;m=lin;d=500ms;j=20%@pm`, `rtry:a=6;m=seq;seq=100,500,2s*;j=250@full`.
[SPEC.md](SPEC.md) is normative: the grammar, validation, jitter bounds, the retryable token set,
the canonical form and every interface.

## Testing your implementation

`Gohany\Retry\Testing` ships abstract PHPUnit test cases. Extend one per component and implement
its `create…()` methods:

```php
use Gohany\Retry\Testing\RetrySchedulerContractTestCase;

final class MySchedulerTest extends RetrySchedulerContractTestCase
{
    protected function createScheduler(\Psr\Clock\ClockInterface $clock, \Random\Randomizer $random): \Gohany\Retry\RetrySchedulerInterface
    {
        return new MyScheduler($clock, $random);
    }

    protected function createPolicyFactory(): \Gohany\Retry\RetryPolicyFactoryInterface
    {
        return new MyPolicyFactory();
    }
}
```

The others are `RetryPolicyContractTestCase`, `RetryerContractTestCase`,
`RetryBudgetContractTestCase`, `RetryBudgetStoreContractTestCase`,
`CircuitBreakerContractTestCase` and `CircuitBreakerStoreContractTestCase`. `FrozenClock`,
`RecordingSleeper` and `SeededRandom` let a test check the exact sleeps a spec produces.

This repository's own tests run the contracts against small in-memory fakes in `tests/Fixtures`
and check SPEC.md against the code: `composer install && vendor/bin/phpunit`.

## License

- **Code** (interfaces, contract tests): MIT
- **Spec/docs** (SPEC.md, this README): **CC-BY 4.0**

> © 2025 Gregory Riley and contributors. Licensed under Creative Commons Attribution 4.0 (CC BY 4.0). Changes may have been made. License: https://creativecommons.org/licenses/by/4.0/
