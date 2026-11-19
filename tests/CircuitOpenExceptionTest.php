<?php declare(strict_types=1);

namespace Gohany\Retry\Tests;

use Gohany\Retry\CircuitOpenException;
use PHPUnit\Framework\TestCase;

/**
 * Breaker keys can carry tenant or customer ids (payments.stripe.<tenantId>). Callers log
 * exception messages and error trackers index them, so the message must never contain the
 * key; the key is read from key() and belongs in log context.
 */
final class CircuitOpenExceptionTest extends TestCase
{
    public function testMessageIsConstantAndDoesNotContainTheKey(): void
    {
        $key = 'payments.stripe.t_7Hq2mXnPz9kLwR4vB8cD1eF3gJ';

        $e = new CircuitOpenException($key);

        self::assertSame('Circuit open', $e->getMessage());
        self::assertStringNotContainsString($key, $e->getMessage());
        self::assertStringNotContainsString('t_7Hq2mXnPz9kLwR4vB8cD1eF3gJ', (string) $e);
    }

    public function testMessageIsTheSameForEveryKey(): void
    {
        self::assertSame(
            (new CircuitOpenException('search-api'))->getMessage(),
            (new CircuitOpenException('payments.finix.t_other'))->getMessage(),
        );
    }

    public function testKeyIsStillReadableThroughTheAccessor(): void
    {
        self::assertSame('payments.stripe.t_abc', (new CircuitOpenException('payments.stripe.t_abc'))->key());
        self::assertSame('', (new CircuitOpenException(''))->key());
    }

    public function testPreviousAndCodeAreKept(): void
    {
        $previous = new \RuntimeException('store down');

        $e = new CircuitOpenException('k', $previous);

        self::assertSame($previous, $e->getPrevious());
        self::assertSame(0, $e->getCode());
        self::assertInstanceOf(\RuntimeException::class, $e);
    }
}
