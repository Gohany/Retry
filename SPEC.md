# Retry Interfaces & RTRY Policy — SPEC (v2)

> **Status:** Draft, version 2 of the interfaces (package `gohany/retry` 2.x). The `rtry:` string
> keeps its v1 prefix and the keys Gohany/Rtry 1.x parses, with the meanings fixed in §6.
> `tests/SpecConformanceTest.php` fails when this document and `src/` drift apart.

The key words MUST, MUST NOT, SHOULD and MAY are used as in RFC 2119.

## 1. Abstract

This SPEC defines a small set of PHP interfaces for retrying idempotent or compensatable
operations, a portable one-line retry policy string (`rtry:`), a pure scheduling call for
durable retries, and contracts for a per-key retry budget and circuit breaker whose state can
be shared between processes.

## 2. Scope

- **In scope**
    - Running an operation under a policy (`RetryerInterface`), returning its value or the
      reason it gave up.
    - An immutable policy (`RetryPolicyInterface`) and the `rtry:` string that compiles to one.
    - A scheduling call that never sleeps (`RetrySchedulerInterface`), for retries stored as a
      row, a queue message or a workflow timer.
    - Retry budgets and circuit breakers per key, with atomic single-key storage seams.
    - Contract test cases (`Gohany\Retry\Testing`) every implementation can run.
- **Out of scope**
    - Cancelling running user code (PHP cannot pre-empt synchronous code).
    - A hedging runtime (`h` is a hint only).
    - Transport-specific parsing (HTTP headers, driver error codes) beyond the outcome fields.

## 3. Terminology

- **Attempt**: one invocation of the operation. Attempts are numbered from 1.
- **Nominal delay**: the delay before attempt *n* (n ≥ 2) given by the backoff mode, before cap
  and jitter.
- **Jitter**: randomisation of the delay so that callers who failed together do not retry together.
- **Deadline**: upper bound on the time from the start of attempt #1; no attempt starts after it.
- **Key**: the name a budget or breaker counts under, usually one downstream service.

## 4. Design overview

- A **policy** is data. It holds no cursor, seed or other iteration state; every delay is a
  function of the attempt number. One instance MUST be safe to share across requests in a
  long-running worker.
- A **scheduler** turns (policy, failed outcome, context) into "retry after N ms" or "give up,
  because …". Randomness comes from an injected `\Random\Randomizer`, time from an injected
  PSR-20 clock.
- A **retryer** runs the operation, asks the scheduler, and sleeps through a `SleeperInterface`.
  `try()` returns the value and rethrows the last error; `tryOutcome()` returns a
  `RetryResultInterface` that says why it stopped.
- A **budget** and a **breaker** cap the extra load sent to a service that is already failing.
  Their state goes through a store that does one atomic operation on one key per call, so a
  clustered key-value store needs no multi-key script.

## 5. Interfaces

Every type below lives in `Gohany\Retry`. Each bullet names a method the type declares; the
list is complete.

### 5.1 RetryerInterface

- `try(callable $operation, RetryPolicyInterface $policy, array $context = []): mixed` — runs
  the operation (it receives an `AttemptContextInterface`) until it succeeds or the retryer gives
  up. Returns the value. On give-up it MUST rethrow the last attempt's error — the same instance
  `tryOutcome()` reports — or a `CircuitOpenException` if a breaker refused the first attempt.
- `tryOutcome(callable $operation, RetryPolicyInterface $policy, array $context = []): RetryResultInterface`
  — the same run, but the operation's error is reported, not thrown.

Between attempts a retryer MUST follow `RetrySchedulerInterface::next()` (§5.11) and MUST wait
only through `SleeperInterface`. If it was given a budget and a key, it MUST call `tryAcquire()`
before each retry and give up with `BudgetExhausted` when refused, and call `recordSuccess()` on
success. If it was given a breaker and a key, it MUST call `allow()` before each attempt (giving
up with `CircuitOpen` when refused) and report each attempt's result to the breaker.

### 5.2 RetryResultInterface

- `isSuccess(): bool`
- `value(): mixed` — the operation's value, null on failure.
- `lastError(): ?\Throwable` — the last attempt's error, or the `CircuitOpenException` when no
  attempt ran; null on success.
- `attempts(): int` — attempts started.
- `giveUpReason(): ?GiveUpReason` — null on success.

### 5.3 RetryPolicyInterface

- `attempts(): int` — total attempts, including the first (`a`).
- `startAfterMs(): int` — delay before attempt #1 (`sa`, default 0). Not jittered.
- `delayMs(): ?int` — base delay (`d`); null in `seq` mode.
- `nominalDelayMs(int $attemptNumber): int` — nominal delay before attempt *n* ≥ 2:
  `exp` d·b^(n−2), `lin` d·(n−1), `seq` the sequence's `delayMsAt(n)`; rounded to the nearest
  integer. MUST throw `\InvalidArgumentException` for n < 2.
- `capMs(): ?int` — upper bound on the nominal delay before jitter (`cap`); null for none.
- `jitter(): JitterInterface` — never null; an absent `j` means `100%@full`.
- `sequence(): ?SequenceInterface` — the `seq` delays in `seq` mode, otherwise null.
- `attemptTimeoutMs(): ?int` — advisory per-attempt timeout (`t`).
- `deadlineBudgetMs(): ?int` — overall budget from the start of attempt #1 (`dl`).
- `followHeaders(): bool` — honour a server's Retry-After / RateLimit-Reset (`fh`, default true).
- `hedge(): ?HedgeInterface` — hedging hints (`h`).
- `decider(): RetryDeciderInterface` — retryable or not.
- `canonicalSpec(): string` — the canonical `rtry:` string (§6.4).

### 5.4 SequenceInterface

- `delaysMs(): array` — the delays in ms; the first is the delay before attempt #2.
- `isRepeatLastDelay(): bool` — whether the last delay repeats (`*`).
- `delayMsAt(int $attemptNumber): ?int` — `delaysMs()[n − 2]`; past the end the last delay if it
  repeats, otherwise null.

### 5.5 JitterInterface

- `mode(): string` — `full`, `pm` or `none` (constants `MODE_FULL`, `MODE_PM`, `MODE_NONE`).
- `percent(): ?float` — the window as 0–100 percent of the delay, or null.
- `windowMs(): ?int` — the window in ms, or null.
- `apply(int $nominalDelayMs, \Random\Randomizer $random): int` — the jittered delay, bounds in
  §6.2. It MUST draw exactly one value with `$random->getInt($low, $high)` when low < high and
  MUST NOT draw otherwise, so a seeded `Randomizer` gives the same delays in every implementation.

### 5.6 HedgeInterface

- `lanes(): int` — total lanes including the first.
- `staggerDelayMs(): int` — delay between lane starts.
- `cancelPolicy(): int` — `CANCEL_ON_FIRST_SUCCESS` (0) or `CANCEL_ON_FIRST_COMPLETION` (1).

### 5.7 RetryDeciderInterface

- `shouldRetry(AttemptOutcomeInterface $outcome, AttemptContextInterface $context): bool`

The decider a factory builds from `on` (or from its default when `on` is absent) MUST follow §6.3.

### 5.8 AttemptContextInterface

- `attemptNumber(): int`
- `maxAttempts(): int`
- `scheduledDelayMs(): int` — the delay waited before this attempt.
- `elapsedSinceFirstMs(): int`
- `remainingBudgetMs(): ?int` — deadline budget left (clamped at 0), or null without `dl`.
- `context(): array` — the caller's free-form context.

### 5.9 AttemptOutcomeInterface

- `isSuccess(): bool`
- `result(): mixed`
- `error(): ?\Throwable`
- `statusCode(): ?int` — e.g. the HTTP status.
- `tags(): array` — adapter tags such as `ETIMEDOUT`, compared case-insensitively.
- `notBeforeUnixMs(): ?int` — the next attempt MUST NOT start before this time (epoch ms) when
  the policy follows headers. Whoever builds the outcome sets it from `Retry-After` (seconds or
  HTTP-date) or `RateLimit-Reset`.

### 5.10 RetryPolicyFactoryInterface

- `fromSpec(string $spec, ?RetryDeciderInterface $decider = null): RetryPolicyInterface` — parse
  and validate an `rtry:` string; a given decider replaces the one `on` would build. MUST throw
  `\InvalidArgumentException` for an invalid string.

### 5.11 RetrySchedulerInterface

- `next(RetryPolicyInterface $policy, AttemptOutcomeInterface $outcome, AttemptContextInterface $context): RetryDecision`

Given failed attempt *n* = `$context->attemptNumber()`, in this order:

1. A successful outcome is a caller error: throw `\InvalidArgumentException`.
2. If `decider()->shouldRetry()` is false: give up, `NotRetryable`.
3. If n ≥ `attempts()`: give up, `AttemptsExhausted`.
4. delay = `nominalDelayMs(n + 1)`; then `min(delay, capMs())` when a cap is set; then
   `jitter()->apply(delay, $random)`.
5. If `followHeaders()` and `notBeforeUnixMs()` is set: delay = max(delay, notBefore − now).
   A server's Retry-After is honoured even when it exceeds `cap`.
6. If `remainingBudgetMs()` is not null and delay ≥ it: give up, `DeadlineExceeded`.
7. Retry after delay.

### 5.12 RetryDecision

A final value class.

- `retryAfter(int $delayMs): RetryDecision` — static constructor; rejects a negative delay.
- `giveUp(GiveUpReason $reason): RetryDecision` — static constructor.
- `retry(): bool`
- `delayMs(): int` — 0 when giving up.
- `giveUpReason(): ?GiveUpReason` — null when retrying.

### 5.13 GiveUpReason

A string-backed enum.

- case `NotRetryable` — the decider refused.
- case `AttemptsExhausted` — the last allowed attempt failed.
- case `DeadlineExceeded` — the next attempt could not start before the deadline.
- case `BudgetExhausted` — a retry budget refused.
- case `CircuitOpen` — a circuit breaker refused.

### 5.14 SleeperInterface

- `sleepMs(int $milliseconds): void`

### 5.15 RetryBudgetInterface

Token bucket per key, as in gRPC retry throttling: a key starts with `maxTokens`; each retry
wanted costs 1; each success earns `tokenRatio`; a retry is allowed only while the tokens left
are above `maxTokens / 2`. `maxTokens` and `tokenRatio` are implementation configuration.

- `tryAcquire(string $key): bool` — spend 1 token (never below 0); true when the tokens left are
  above maxTokens / 2.
- `recordSuccess(string $key): void` — add `tokenRatio`, never above maxTokens.
- `tokens(string $key): float` — tokens left; maxTokens for a new or expired key.

### 5.16 RetryBudgetStoreInterface

- `add(string $key, float $delta, float $max, int $ttlMs): float` — atomically: read the key
  (treated as `$max` when absent or expired), add `$delta`, clamp to [0, `$max`], store with the
  TTL, return the new value. One key per call.

### 5.17 CircuitBreakerInterface

Per key. **Closed**: `allow()` is true; the circuit opens when failures within the window reach
the threshold (a count, a rate, or both: implementation configuration). **Open**: `allow()` is
false until the open period ends. **Half-open**: exactly one caller across every process sharing
the state gets true (the probe); its success closes the circuit, its failure opens it again. A
probe that never reports is replaced after another open period.

- `allow(string $key): bool`
- `recordSuccess(string $key): void`
- `recordFailure(string $key): void`
- `state(string $key): CircuitState`

### 5.18 CircuitState

A string-backed enum.

- case `Closed`
- case `Open`
- case `HalfOpen`

### 5.19 CircuitBreakerStoreInterface

- `load(string $key): ?VersionedValue` — null when absent or expired.
- `compareAndSet(string $key, ?int $expectedVersion, string $value, int $ttlMs): bool` —
  atomically write if the stored version is still `$expectedVersion` (null: the key must be
  absent). The version is 1 on create and goes up by 1 on each successful write. False, and
  nothing written, otherwise. One key per call.

### 5.20 VersionedValue

A final value class.

- `version(): int`
- `value(): string` — opaque to the store; the breaker serialises its own state.

### 5.21 CircuitOpenException

Extends `\RuntimeException`.

- `key(): string` — the breaker key that refused.

The message is the same for every key and is never built from it: keys can carry tenant or customer ids, and callers log
messages. The exact message text is not part of this contract; match on the type and read
`key()`.

## 6. RTRY string (normative)

A semicolon-separated `key=value` list after the `rtry:` prefix. Keys are case-insensitive,
order is free, whitespace around pairs is ignored.

```
rtry:a=<int>;d=<duration>;m=<exp|lin|seq>;b=<float>;seq=<dur>[,<dur>...][*];cap=<duration>;
j=<duration|percent>[@<full|pm|none>];t=<duration>;dl=<duration>;on=<token>[,<token>...];
sa=<duration>;fh=<0|1>;h=<lanes>@<duration>[&<0|1>]
```

- **a** attempts, including the first. Integer ≥ 1. Required.
- **d** base delay: the first retry's delay in `exp` mode and the step in `lin` mode. Required
  for `exp` and `lin`, rejected in `seq` mode. It has no other meaning.
- **m** backoff mode: `exp` (default), `lin` or `seq`.
- **b** exponential factor, ≥ 1, default 2. `exp` mode only.
- **seq** explicit delays, the first being the delay before attempt #2. A trailing `*` repeats
  the last one. Required in `seq` mode, rejected otherwise. Without `*`, `a` MUST NOT exceed
  the number of delays + 1. `seq=(50,100,1.5s*)` and `seq=50,100,1.5s,*` are accepted as the
  same value.
- **cap** upper bound on the nominal delay, applied before jitter. Default: none.
- **j** jitter window and mode, §6.2. Default `100%@full`; `j=0` or `j=none` turns it off.
- **t** advisory per-attempt timeout.
- **dl** deadline: the budget from the start of attempt #1, a duration.
- **on** retryable tokens, §6.3. Default `default`.
- **sa** delay before attempt #1. Default 0, not jittered.
- **fh** follow server rate-limit headers: `1` (default) or `0`.
- **h** hedging hint: lanes ≥ 1, the stagger between lane starts, and an optional cancel policy
  after `&` (0 first success, the default; 1 first completion).

A factory MUST reject: a missing `a`; an unknown or repeated key; a value that does not match
its form; `d` missing in `exp`/`lin` or present in `seq`; `seq` missing in `seq` mode or present
otherwise; `b` below 1 or outside `exp` mode; a `seq` without `*` too short for `a`.

### 6.1 Durations

Regex `~^([0-9]+(?:\.[0-9]+)?)(ms|s|m|h)?$~i`; no unit means milliseconds. Results are
rounded to whole milliseconds.

### 6.2 Jitter

`j=<window>[@<mode>]`, mode `full` when omitted. The window is a duration or a percent
(`20%`, 0–100) of the delay being jittered. With D the capped nominal delay and w the window in
ms (for a percent p: round(p / 100 × D)):

- `full`: a uniform integer in [max(0, D − w), D]. `100%@full` is classic full jitter, [0, D].
- `pm`: a uniform integer in [max(0, D − w), D + w].
- `none`: D.

The draw is one `Randomizer::getInt(low, high)` call when low < high (§5.5).

### 6.3 Retryable tokens

`on` lists tokens, compared case-insensitively. A failed outcome is retryable when any token
matches it:

- a three-digit status (`503`) matches that `statusCode()`;
- a family (`5xx`) matches any status in it;
- any other token matches an equal entry of `tags()`;
- `default` expands to `408,429,5xx,ETIMEDOUT,ECONNRESET,ECONNREFUSED,NETWORK_ERROR,DEADLOCK,RATE_LIMITED`.

Without `on` the token list is `default`. So by default a 4xx other than 408 and 429 never
retries, and neither does an error with no status and no tag.

### 6.4 Canonical form

A canonical string contains exactly the keys the input had, lowercased, in the order below;
durations as integer milliseconds without a unit; `j` as `<n>[%]@<mode>` (`j=none` for none);
`seq` as comma-separated milliseconds with a trailing `*` when it repeats; `b` without
trailing zeros; `on` tokens in input order; `fh` as `0` or `1`; `h` as `<lanes>@<ms>`, adding
`&1` only for cancel-on-first-completion.

**Canonical key order**: `a; d; m; b; seq; cap; j; t; dl; on; sa; fh; h`

Example: `rtry:j=10%@pm;d=1s;a=3;cap=2.5s` is canonically `rtry:a=3;d=1000;cap=2500;j=10%@pm`.

## 7. Interoperability and PSRs

- **PSR-20**: implementations take a `Psr\Clock\ClockInterface` for time (deadlines, Retry-After,
  breaker windows).
- **PSR-3 / PSR-14**: implementations MAY log or dispatch events per attempt; no dependency is
  required.
- **PSR-18 / PSR-7**: compatible as a wrapping layer around HTTP clients.

## 8. Error handling and semantics

- `try()` rethrows the last attempt's error on give-up; `tryOutcome()` reports it with the reason.
- Per-attempt timeouts are advisory.
- Retrying or hedging an operation with side effects needs an idempotency key at the call site.
- A retryer MUST NOT sleep past the deadline; a durable retry SHOULD store the scheduler's delay
  rather than sleep.

## 9. Contract tests

`Gohany\Retry\Testing` ships abstract PHPUnit test cases (PHPUnit is a dev requirement of the
implementation, not of this package): `RetryPolicyContractTestCase`,
`RetrySchedulerContractTestCase`, `RetryerContractTestCase`, `RetryBudgetContractTestCase`,
`RetryBudgetStoreContractTestCase`, `CircuitBreakerContractTestCase` and
`CircuitBreakerStoreContractTestCase`, with the helpers `FrozenClock`, `RecordingSleeper`,
`SeededRandom`, `TestOutcome`, `TestAttemptContext` and `FixedDecider`. An implementation extends each one and
implements its `create…()` methods.

## 10. Security considerations

- Jitter every retry delay; a fixed schedule shared by many callers synchronises them.
- Use a budget and a breaker per downstream service so retries cannot multiply an outage.
- Retries can magnify side effects: protect webhooks and money moves with idempotency keys.
- Never put credentials or card data in tags, context or failure messages.
- Breaker and budget keys can carry tenant or customer ids: keep them out of exception and log
  messages and put them in log context.

## 11. Versioning

- Interfaces follow semantic versioning. 2.0 breaks 1.x; `CHANGELOG.md` lists every break.
- If the string format ever changes incompatibly, the prefix becomes `rtry2:`.

## 12. Prior art

- Exponential backoff with full jitter (AWS Architecture Blog, 2015).
- gRPC retry throttling; the AWS SDK retry quota.
- The circuit breaker pattern (Nygard, *Release It!*).
- PSR-20 for time.

## 13. IPR & license

This specification text is © 2025 Gregory Riley and contributors. Licensed under **Creative
Commons Attribution 4.0 (CC-BY-4.0)**. Reference code (interfaces and examples) is licensed
under **MIT** unless noted otherwise.

> © 2025 Gregory Riley and contributors. Licensed under Creative Commons Attribution 4.0 (CC BY 4.0). Changes may have been made. License: https://creativecommons.org/licenses/by/4.0/
