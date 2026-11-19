# Changelog

## 2.0.1 — 2026-11-19

### Security

- `CircuitOpenException`'s message is now the constant `Circuit open`; 2.0.0 rendered
  `Circuit open for <key>`. Breaker keys can carry tenant or customer ids
  (`payments.stripe.<tenantId>`), and anything that logs the exception or forwards it to an
  error tracker put that id into the rendered message. The key is still available, unchanged,
  through `key()`; put it in log context. SPEC §5.21 now says the message never contains the
  key and that its exact text is not part of the contract, so match on the type, not the
  message.
- A classifier that reads exception messages no longer sees the key. On 2.0.0 a refused circuit
  on a key such as `db.deadlock` or `upstream connection refused` was tagged by its own name
  (`DEADLOCK`, `ECONNREFUSED`), so an outer policy retrying on that tag would retry the refusal;
  it is now untagged.
- SPEC §10 now says breaker and budget keys can carry tenant or customer ids and stay out of
  exception and log messages.

## 2.0.0 — 2026-11-17

Version 2 makes SPEC.md, README.md and the code agree, makes policies immutable, and adds a
scheduling call, retry budgets and circuit breakers. `tests/SpecConformanceTest.php` now fails
when SPEC §5 and `src/` drift apart.

### Requirements

- PHP `^8.2` (was `>=7.1`). Adds `psr/clock` `^1.0`.

### Breaking changes (what an implementation such as Gohany/Rtry 1.x must change)

- `RetryerInterface::try()` returns the operation's value (`mixed`) and rethrows the last
  error on give-up. 1.x declared `AttemptOutcomeInterface` as its return type while its
  docblock and the SPEC promised the value.
- `RetryerInterface::tryOutcome()` is new: the same run, returning a `RetryResultInterface`
  (value or last error, attempts, `GiveUpReason`). Implementations must add it.
- `RetryPolicyInterface::nextDelayMs(int)` is renamed `nominalDelayMs(int)` and returns the
  delay before cap and jitter for attempt n ≥ 2, as a pure function of n
  (`exp` d·b^(n−2), `lin` d·(n−1), `seq` by position). It throws for n < 2.
- `RetryPolicyInterface::capMs()` returns `?int`; null means no cap. (1.x returned `int`,
  and Rtry's default of 0 capped every delay to 0 ms.)
- `RetryPolicyInterface::jitter()` returns `JitterInterface`, never null: no `j` means
  `100%@full`. `j=0` or `j=none` turns jitter off.
- `RetryPolicyInterface::canonicalSpec()` moves up from Rtry's own interface into this one.
- Policies hold no iteration state: no cursor, no seed, no `resetCursor()` / `setSeed()`.
- `SequenceInterface::reset()` and `nextDelayMs()` are removed; `delayMsAt(int $attemptNumber)`
  replaces them. The first configured delay is the delay before attempt #2.
- `JitterInterface::apply(int $nominalDelayMs, \Random\Randomizer $random)` takes an injected
  Randomizer instead of `?int $seed`, and must draw exactly once with `getInt($low, $high)`
  when low < high. `full` with a window w now means [max(0, D − w), D] (1.x Rtry drew from
  [0, w], ignoring the delay). `percent()` is 0–100, matching `j=20%` (1.x documented 0–1).
- `AttemptOutcomeInterface::result()` and `RetryResultInterface::value()` return `mixed`;
  `AttemptOutcomeInterface::notBeforeUnixMs()` is new, carrying Retry-After / RateLimit-Reset.

### Added

- `RetrySchedulerInterface::next(policy, outcome, context): RetryDecision` — "retry after N
  ms" or "give up" with a `GiveUpReason`, never sleeping. For durable retries.
- `RetryDecision`, `GiveUpReason`, `RetryResultInterface`, `SleeperInterface` (moved here from
  Rtry's contracts).
- `RetryBudgetInterface` and `RetryBudgetStoreInterface` (atomic clamped add, one key).
- `CircuitBreakerInterface`, `CircuitState`, `CircuitBreakerStoreInterface` (versioned
  compare-and-set, one key), `VersionedValue`, `CircuitOpenException`.
- `Gohany\Retry\Testing`: abstract contract test cases for policies, schedulers, retryers,
  budgets, breakers and both stores, plus `FrozenClock`, `RecordingSleeper`, `SeededRandom`,
  `TestOutcome`, `TestAttemptContext`, `FixedDecider`.

### `rtry:` string

The keys are exactly the ones Rtry 1.x parses: `a d m b seq cap j t dl on sa fh h`.

- `m` is the mode key (the 1.x SPEC and README said `mode=`); `h` is the hedge key (the 1.x
  grammar said `hedge=`); `fh` is now documented.
- `d` has one meaning, the base delay (first retry in `exp`, step in `lin`). The second `d`
  ("linear delay") and the never-defined `inc` are gone. In Rtry 1.x, `d` also switched the
  mode to `lin`; under 2.0 it does not.
- `jmode` is gone; the mode is part of `j` (`j=20%@pm`).
- `dl` is a duration only (Rtry 1.x also accepted a date).
- Validation is specified (§6): `a` required and ≥ 1, `d` required for `exp`/`lin`, `seq`
  only in `seq` mode and long enough for `a` unless it ends in `*`, `b` ≥ 1 and `exp` only,
  `fh` 0 or 1, unknown and repeated keys rejected.
- Without `on`, only `408`, `429`, `5xx` and transport / deadlock / rate-limit tags retry.
- The canonical form is specified (§6.4): input keys only, fixed order, durations in ms.

### Docs

- README quick start no longer imports `foreup\…` or `Psr\Retry\Impl\…` classes that do not
  exist; it uses the interfaces.
