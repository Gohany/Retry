<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\RetrySchedulerInterface;
use Gohany\Retry\Testing\RetrySchedulerContractTestCase;
use Gohany\Retry\Tests\Fixtures\PolicyFactory;
use Gohany\Retry\Tests\Fixtures\Scheduler;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

final class FixtureSchedulerTest extends RetrySchedulerContractTestCase
{
    protected function createScheduler(ClockInterface $clock, Randomizer $random): RetrySchedulerInterface
    {
        return new Scheduler($clock, $random);
    }

    protected function createPolicyFactory(): RetryPolicyFactoryInterface
    {
        return new PolicyFactory();
    }
}
