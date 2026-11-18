<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\HedgeInterface;
use Gohany\Retry\JitterInterface;
use Gohany\Retry\RetryPolicyFactoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SPEC §5.3, §5.10 and §6: what a parsed rtry: string means.
 */
abstract class RetryPolicyContractTestCase extends TestCase
{
    abstract protected function createPolicyFactory(): RetryPolicyFactoryInterface;

    /** @return list<int> nominal delays before attempts 2..a */
    private function nominal(string $spec): array
    {
        $policy = $this->createPolicyFactory()->fromSpec($spec);
        $out = [];
        for ($n = 2; $n <= $policy->attempts(); $n++) {
            $out[] = $policy->nominalDelayMs($n);
        }
        return $out;
    }

    public function testExponentialDelaysStartAtDAndMultiplyByB(): void
    {
        self::assertSame([100, 200, 400, 800], $this->nominal('rtry:a=5;d=100;m=exp;b=2'));
        self::assertSame([100, 150, 225], $this->nominal('rtry:a=4;d=100;b=1.5'));
    }

    public function testModeDefaultsToExpAndFactorToTwo(): void
    {
        self::assertSame([250, 500, 1000], $this->nominal('rtry:a=4;d=250ms'));
    }

    public function testLinearDelaysStepByD(): void
    {
        self::assertSame([100, 200, 300], $this->nominal('rtry:a=4;d=100;m=lin'));
    }

    public function testSequenceStartsBeforeAttemptTwo(): void
    {
        self::assertSame([50, 100, 1000], $this->nominal('rtry:a=4;m=seq;seq=50,100,1s'));
        self::assertSame([50, 100, 100, 100], $this->nominal('rtry:a=5;m=seq;seq=50,100*'));
        self::assertSame([50, 100, 1500, 1500], $this->nominal('rtry:a=5;m=seq;seq=(50,100ms,1.5s*)'));
        self::assertSame([50, 100, 1500, 1500], $this->nominal('rtry:a=5;m=seq;seq=50,100ms,1.5s,*'));

        $sequence = $this->createPolicyFactory()->fromSpec('rtry:a=3;m=seq;seq=50,100')->sequence();
        self::assertNotNull($sequence);
        self::assertSame([50, 100], $sequence->delaysMs());
        self::assertFalse($sequence->isRepeatLastDelay());
        self::assertSame(50, $sequence->delayMsAt(2));
        self::assertSame(100, $sequence->delayMsAt(3));
        self::assertNull($sequence->delayMsAt(4));
    }

    public function testNominalDelayIsAFunctionOfTheAttemptNumberOnly(): void
    {
        $policy = $this->createPolicyFactory()->fromSpec('rtry:a=5;d=100');
        $forward = [$policy->nominalDelayMs(2), $policy->nominalDelayMs(3), $policy->nominalDelayMs(4)];
        $backward = [$policy->nominalDelayMs(4), $policy->nominalDelayMs(3), $policy->nominalDelayMs(2)];
        self::assertSame($forward, array_reverse($backward));
        self::assertSame(200, $policy->nominalDelayMs(3));
    }

    public function testNominalDelayRejectsAttemptsBeforeTwo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createPolicyFactory()->fromSpec('rtry:a=3;d=100')->nominalDelayMs(1);
    }

    public function testDurationUnits(): void
    {
        $policy = $this->createPolicyFactory()->fromSpec('rtry:a=2;d=1.5s;cap=2m;t=250ms;dl=1h;sa=20');
        self::assertSame(1500, $policy->delayMs());
        self::assertSame(120000, $policy->capMs());
        self::assertSame(250, $policy->attemptTimeoutMs());
        self::assertSame(3600000, $policy->deadlineBudgetMs());
        self::assertSame(20, $policy->startAfterMs());
    }

    public function testDefaults(): void
    {
        $policy = $this->createPolicyFactory()->fromSpec('rtry:a=3;d=100');
        self::assertSame(3, $policy->attempts());
        self::assertSame(0, $policy->startAfterMs());
        self::assertNull($policy->capMs());
        self::assertNull($policy->attemptTimeoutMs());
        self::assertNull($policy->deadlineBudgetMs());
        self::assertTrue($policy->followHeaders());
        self::assertNull($policy->hedge());
        self::assertNull($policy->sequence());

        $jitter = $policy->jitter();
        self::assertSame(JitterInterface::MODE_FULL, $jitter->mode());
        self::assertSame(100.0, $jitter->percent());
        self::assertNull($jitter->windowMs());
    }

    public function testJitterForms(): void
    {
        $factory = $this->createPolicyFactory();

        $j = $factory->fromSpec('rtry:a=2;d=100;j=250')->jitter();
        self::assertSame([JitterInterface::MODE_FULL, null, 250], [$j->mode(), $j->percent(), $j->windowMs()]);

        $j = $factory->fromSpec('rtry:a=2;d=100;j=20%@pm')->jitter();
        self::assertSame([JitterInterface::MODE_PM, 20.0, null], [$j->mode(), $j->percent(), $j->windowMs()]);

        self::assertSame(JitterInterface::MODE_NONE, $factory->fromSpec('rtry:a=2;d=100;j=0')->jitter()->mode());
        self::assertSame(JitterInterface::MODE_NONE, $factory->fromSpec('rtry:a=2;d=100;j=none')->jitter()->mode());
    }

    public function testJitterBounds(): void
    {
        $factory = $this->createPolicyFactory();
        $cases = [
            ['rtry:a=2;d=100;j=100%@full', 400, [0, 400]],
            ['rtry:a=2;d=100;j=100@full', 400, [300, 400]],
            ['rtry:a=2;d=100;j=1s@full', 400, [0, 400]],
            ['rtry:a=2;d=100;j=25%@pm', 400, [300, 500]],
            ['rtry:a=2;d=100;j=50@pm', 400, [350, 450]],
        ];
        foreach ($cases as $i => [$spec, $delay, $bounds]) {
            $jitter = $factory->fromSpec($spec)->jitter();
            $seed = 1000 + $i;
            self::assertSame(
                SeededRandom::expectedDraws($seed, [$bounds]),
                [$jitter->apply($delay, SeededRandom::randomizer($seed))],
                $spec
            );
        }
        self::assertSame(400, $factory->fromSpec('rtry:a=2;d=100;j=0')->jitter()->apply(400, SeededRandom::randomizer(1)));
        self::assertSame(0, $factory->fromSpec('rtry:a=2;d=100')->jitter()->apply(0, SeededRandom::randomizer(1)));
    }

    public function testFollowHeadersAndHedge(): void
    {
        $policy = $this->createPolicyFactory()->fromSpec('rtry:a=2;d=100;fh=0;h=3@100&1');
        self::assertFalse($policy->followHeaders());
        $hedge = $policy->hedge();
        self::assertNotNull($hedge);
        self::assertSame(3, $hedge->lanes());
        self::assertSame(100, $hedge->staggerDelayMs());
        self::assertSame(HedgeInterface::CANCEL_ON_FIRST_COMPLETION, $hedge->cancelPolicy());

        $hedge = $this->createPolicyFactory()->fromSpec('rtry:a=2;d=100;h=2@1s')->hedge();
        self::assertNotNull($hedge);
        self::assertSame(HedgeInterface::CANCEL_ON_FIRST_SUCCESS, $hedge->cancelPolicy());
    }

    /** @return iterable<string,array{string,string}> */
    public static function canonicalCases(): iterable
    {
        yield 'spec example' => ['rtry:j=10%@pm;d=1s;a=3;cap=2.5s', 'rtry:a=3;d=1000;cap=2500;j=10%@pm'];
        yield 'case and spaces' => [
            'RTRY: A=4; M=SEQ; seq=(50,100ms,1.5s*); fh=0; h=2@1s&1; on=5xx,ETIMEDOUT',
            'rtry:a=4;m=seq;seq=50,100,1500*;on=5xx,ETIMEDOUT;fh=0;h=2@1000&1',
        ];
        yield 'jitter defaults to full, b trims' => ['rtry:a=3;d=100;b=1.50;j=250', 'rtry:a=3;d=100;b=1.5;j=250@full'];
        yield 'jitter off' => ['rtry:a=3;d=100;j=0', 'rtry:a=3;d=100;j=none'];
        yield 'everything' => [
            'rtry:h=2@100;fh=1;sa=10;on=default;dl=5s;t=1s;j=100%@full;cap=2s;b=2;m=exp;d=100;a=4',
            'rtry:a=4;d=100;m=exp;b=2;cap=2000;j=100%@full;t=1000;dl=5000;on=default;sa=10;fh=1;h=2@100',
        ];
    }

    #[DataProvider('canonicalCases')]
    public function testCanonicalSpec(string $input, string $canonical): void
    {
        $factory = $this->createPolicyFactory();
        self::assertSame($canonical, $factory->fromSpec($input)->canonicalSpec());
        self::assertSame($canonical, $factory->fromSpec($canonical)->canonicalSpec(), 'canonical form is a fixed point');
    }

    /** @return iterable<string,array{string}> */
    public static function invalidSpecs(): iterable
    {
        yield 'empty' => [''];
        yield 'prefix only' => ['rtry:'];
        yield 'missing a' => ['rtry:d=100'];
        yield 'a below 1' => ['rtry:a=0;d=100'];
        yield 'a not an int' => ['rtry:a=two;d=100'];
        yield 'unknown key' => ['rtry:a=3;d=100;x=1'];
        yield 'old mode key' => ['rtry:a=3;d=100;mode=exp'];
        yield 'duplicate key' => ['rtry:a=3;a=4;d=100'];
        yield 'malformed pair' => ['rtry:a=3;d'];
        yield 'missing d in exp' => ['rtry:a=3'];
        yield 'missing d in lin' => ['rtry:a=3;m=lin'];
        yield 'missing seq' => ['rtry:a=3;m=seq'];
        yield 'd in seq' => ['rtry:a=3;m=seq;seq=1,2;d=100'];
        yield 'seq outside seq mode' => ['rtry:a=3;d=100;seq=1,2'];
        yield 'seq too short' => ['rtry:a=4;m=seq;seq=1,2'];
        yield 'b below 1' => ['rtry:a=3;d=100;b=0.5'];
        yield 'b outside exp' => ['rtry:a=3;m=lin;d=100;b=2'];
        yield 'unknown mode' => ['rtry:a=3;d=100;m=fib'];
        yield 'bad duration' => ['rtry:a=3;d=abc'];
        yield 'negative duration' => ['rtry:a=3;d=-5'];
        yield 'bad jitter mode' => ['rtry:a=3;d=100;j=20%@sideways'];
        yield 'jitter over 100%' => ['rtry:a=3;d=100;j=150%'];
        yield 'fh not 0 or 1' => ['rtry:a=3;d=100;fh=2'];
        yield 'dl as a date' => ['rtry:a=3;d=100;dl=tomorrow'];
        yield 'hedge lanes 0' => ['rtry:a=3;d=100;h=0@100'];
        yield 'hedge cancel 2' => ['rtry:a=3;d=100;h=2@100&2'];
        yield 'empty on' => ['rtry:a=3;d=100;on='];
    }

    #[DataProvider('invalidSpecs')]
    public function testRejectsInvalidSpecs(string $spec): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createPolicyFactory()->fromSpec($spec);
    }

    /** @return iterable<string,array{string,?int,list<string>,bool}> */
    public static function retryableCases(): iterable
    {
        yield '503 by default' => ['rtry:a=2;d=1', 503, [], true];
        yield '500 by default' => ['rtry:a=2;d=1', 500, [], true];
        yield '408 by default' => ['rtry:a=2;d=1', 408, [], true];
        yield '429 by default' => ['rtry:a=2;d=1', 429, [], true];
        yield 'timeout tag by default' => ['rtry:a=2;d=1', null, ['etimedout'], true];
        yield 'deadlock tag by default' => ['rtry:a=2;d=1', null, ['DEADLOCK'], true];
        yield '400 never by default' => ['rtry:a=2;d=1', 400, [], false];
        yield '404 never by default' => ['rtry:a=2;d=1', 404, [], false];
        yield '409 never by default' => ['rtry:a=2;d=1', 409, [], false];
        yield 'nothing known' => ['rtry:a=2;d=1', null, [], false];
        yield 'unknown tag' => ['rtry:a=2;d=1', null, ['VALIDATION'], false];
        yield 'on=503 matches 503' => ['rtry:a=2;d=1;on=503', 503, [], true];
        yield 'on=503 not 500' => ['rtry:a=2;d=1;on=503', 500, [], false];
        yield 'on=5xx,deadlock tag' => ['rtry:a=2;d=1;on=5xx,DEADLOCK', null, ['deadlock'], true];
        yield 'on=5xx not 429' => ['rtry:a=2;d=1;on=5xx', 429, [], false];
        yield 'on=default,409' => ['rtry:a=2;d=1;on=default,409', 409, [], true];
    }

    /** @param list<string> $tags */
    #[DataProvider('retryableCases')]
    public function testDeciderFollowsTheTokenRules(string $spec, ?int $status, array $tags, bool $retryable): void
    {
        $decider = $this->createPolicyFactory()->fromSpec($spec)->decider();
        self::assertSame($retryable, $decider->shouldRetry(TestOutcome::failure($status, $tags), new TestAttemptContext(1)));
    }

    public function testAGivenDeciderReplacesTheOnTokens(): void
    {
        $policy = $this->createPolicyFactory()->fromSpec('rtry:a=2;d=1;on=5xx', FixedDecider::never());
        self::assertFalse($policy->decider()->shouldRetry(TestOutcome::failure(503), new TestAttemptContext(1)));
    }
}
