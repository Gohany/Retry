<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Contract;

use Gohany\Retry\RetryerInterface;
use Gohany\Retry\RetryPolicyFactoryInterface;
use Gohany\Retry\SleeperInterface;
use Gohany\Retry\Testing\RetryerContractTestCase;
use Gohany\Retry\Tests\Fixtures\PolicyFactory;
use Gohany\Retry\Tests\Fixtures\Retryer;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

final class FixtureRetryerTest extends RetryerContractTestCase
{
    protected function createRetryer(SleeperInterface $sleeper, ClockInterface $clock, Randomizer $random): RetryerInterface
    {
        return new Retryer($sleeper, $clock, $random);
    }

    protected function createPolicyFactory(): RetryPolicyFactoryInterface
    {
        return new PolicyFactory();
    }
}
