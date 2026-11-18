<?php declare(strict_types=1);

namespace Gohany\Retry\Testing;

use Gohany\Retry\AttemptOutcomeInterface;

final class TestOutcome implements AttemptOutcomeInterface
{
    /** @param list<string> $tags */
    private function __construct(
        private readonly bool $success,
        private readonly mixed $result,
        private readonly ?\Throwable $error,
        private readonly ?int $statusCode,
        private readonly array $tags,
        private readonly ?int $notBeforeUnixMs,
    ) {
    }

    public static function success(mixed $result = null): self
    {
        return new self(true, $result, null, null, [], null);
    }

    /** @param list<string> $tags */
    public static function failure(?int $statusCode = null, array $tags = [], ?int $notBeforeUnixMs = null, ?\Throwable $error = null): self
    {
        return new self(false, null, $error ?? new \RuntimeException('failed'), $statusCode, $tags, $notBeforeUnixMs);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function result(): mixed
    {
        return $this->result;
    }

    public function error(): ?\Throwable
    {
        return $this->error;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function notBeforeUnixMs(): ?int
    {
        return $this->notBeforeUnixMs;
    }
}
