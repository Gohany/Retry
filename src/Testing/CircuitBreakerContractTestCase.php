<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\CircuitBreakerInterface;
use Gohany\Retry\CircuitState;
use PHPUnit\Framework\TestCase;

/**
 * SPEC §5.17: trips on failures in a window, one half-open probe, closes on its success.
 *
 * The contract configures the breaker by failure count; an implementation that also trips
 * on a failure rate must still trip on the count given here.
 */
abstract class CircuitBreakerContractTestCase extends TestCase
{
    protected const THRESHOLD = 3;
    protected const WINDOW_MS = 10_000;
    protected const OPEN_MS = 5_000;

    abstract protected function createBreaker(FrozenClock $clock, int $failureThreshold, int $windowMs, int $openMs): CircuitBreakerInterface;

    /**
     * Two or more breakers sharing state, as two processes would. Return [] when the
     * implementation cannot share state; the cross-process test is then skipped.
     *
     * @return list<CircuitBreakerInterface>
     */
    protected function createSharedBreakers(FrozenClock $clock, int $failureThreshold, int $windowMs, int $openMs): array
    {
        return [];
    }

    private FrozenClock $clock;
    private CircuitBreakerInterface $breaker;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->breaker = $this->createBreaker($this->clock, self::THRESHOLD, self::WINDOW_MS, self::OPEN_MS);
    }

    private function trip(CircuitBreakerInterface $breaker, string $key = 'svc'): void
    {
        for ($i = 0; $i < self::THRESHOLD; $i++) {
            $breaker->recordFailure($key);
        }
    }

    public function testStartsClosed(): void
    {
        self::assertSame(CircuitState::Closed, $this->breaker->state('svc'));
        self::assertTrue($this->breaker->allow('svc'));
    }

    public function testTripsAtTheThreshold(): void
    {
        for ($i = 1; $i < self::THRESHOLD; $i++) {
            $this->breaker->recordFailure('svc');
        }
        self::assertSame(CircuitState::Closed, $this->breaker->state('svc'));
        self::assertTrue($this->breaker->allow('svc'));

        $this->breaker->recordFailure('svc');
        self::assertSame(CircuitState::Open, $this->breaker->state('svc'));
        self::assertFalse($this->breaker->allow('svc'));
    }

    public function testFailuresOutsideTheWindowDoNotCount(): void
    {
        for ($i = 1; $i < self::THRESHOLD; $i++) {
            $this->breaker->recordFailure('svc');
        }
        $this->clock->advanceMs(self::WINDOW_MS + 1);
        $this->breaker->recordFailure('svc');

        self::assertSame(CircuitState::Closed, $this->breaker->state('svc'));
    }

    public function testStaysOpenForTheOpenPeriod(): void
    {
        $this->trip($this->breaker);
        $this->clock->advanceMs(self::OPEN_MS - 1);
        self::assertFalse($this->breaker->allow('svc'));
    }

    public function testLetsExactlyOneProbeThroughWhenHalfOpen(): void
    {
        $this->trip($this->breaker);
        $this->clock->advanceMs(self::OPEN_MS);

        self::assertTrue($this->breaker->allow('svc'), 'the probe');
        self::assertSame(CircuitState::HalfOpen, $this->breaker->state('svc'));
        self::assertFalse($this->breaker->allow('svc'), 'a second caller while the probe is out');
        self::assertFalse($this->breaker->allow('svc'));
    }

    public function testAProbeSuccessCloses(): void
    {
        $this->trip($this->breaker);
        $this->clock->advanceMs(self::OPEN_MS);
        self::assertTrue($this->breaker->allow('svc'));

        $this->breaker->recordSuccess('svc');

        self::assertSame(CircuitState::Closed, $this->breaker->state('svc'));
        self::assertTrue($this->breaker->allow('svc'));
        self::assertTrue($this->breaker->allow('svc'));

        // The failures that tripped it are forgotten.
        $this->breaker->recordFailure('svc');
        self::assertSame(CircuitState::Closed, $this->breaker->state('svc'));
    }

    public function testAProbeFailureReopens(): void
    {
        $this->trip($this->breaker);
        $this->clock->advanceMs(self::OPEN_MS);
        self::assertTrue($this->breaker->allow('svc'));

        $this->breaker->recordFailure('svc');

        self::assertSame(CircuitState::Open, $this->breaker->state('svc'));
        self::assertFalse($this->breaker->allow('svc'));
        $this->clock->advanceMs(self::OPEN_MS - 1);
        self::assertFalse($this->breaker->allow('svc'));
        $this->clock->advanceMs(1);
        self::assertTrue($this->breaker->allow('svc'));
    }

    public function testALostProbeIsReplacedAfterAnotherOpenPeriod(): void
    {
        $this->trip($this->breaker);
        $this->clock->advanceMs(self::OPEN_MS);
        self::assertTrue($this->breaker->allow('svc'));

        $this->clock->advanceMs(self::OPEN_MS - 1);
        self::assertFalse($this->breaker->allow('svc'));
        $this->clock->advanceMs(1);
        self::assertTrue($this->breaker->allow('svc'), 'a new probe once the first one is presumed lost');
    }

    public function testKeysAreIndependent(): void
    {
        $this->trip($this->breaker, 'a');
        self::assertFalse($this->breaker->allow('a'));
        self::assertTrue($this->breaker->allow('b'));
        self::assertSame(CircuitState::Closed, $this->breaker->state('b'));
    }

    public function testOneProbeAcrossProcessesSharingState(): void
    {
        $breakers = $this->createSharedBreakers($this->clock, self::THRESHOLD, self::WINDOW_MS, self::OPEN_MS);
        if (count($breakers) < 2) {
            self::markTestSkipped('This implementation does not share state between instances.');
        }
        [$first, $second] = $breakers;

        $first->recordFailure('svc');
        $second->recordFailure('svc');
        $first->recordFailure('svc');
        self::assertFalse($second->allow('svc'), 'failures from both processes count together');

        $this->clock->advanceMs(self::OPEN_MS);
        $granted = [$first->allow('svc'), $second->allow('svc'), $first->allow('svc')];
        self::assertSame(1, count(array_filter($granted)), 'exactly one probe across processes');

        $first->recordSuccess('svc');
        self::assertSame(CircuitState::Closed, $second->state('svc'));
    }
}
