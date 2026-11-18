<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\GiveUpReason;
use Gohany\Retry\RetryResultInterface;

final class Result implements RetryResultInterface
{
    public function __construct(
        private readonly bool $success,
        private readonly mixed $value,
        private readonly ?\Throwable $lastError,
        private readonly int $attempts,
        private readonly ?GiveUpReason $reason,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function lastError(): ?\Throwable
    {
        return $this->lastError;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function giveUpReason(): ?GiveUpReason
    {
        return $this->reason;
    }
}
