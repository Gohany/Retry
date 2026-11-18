<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\HedgeInterface;

final class Hedge implements HedgeInterface
{
    public function __construct(private readonly int $lanes, private readonly int $staggerMs, private readonly int $cancel)
    {
    }

    public static function parse(string $value): self
    {
        if (!preg_match('/^(\d+)@([^&]+)(?:&(\d+))?$/', trim($value), $m)) {
            throw new \InvalidArgumentException('Invalid h: ' . $value);
        }
        $lanes = (int) $m[1];
        $cancel = isset($m[3]) ? (int) $m[3] : self::CANCEL_ON_FIRST_SUCCESS;
        if ($lanes < 1 || !in_array($cancel, [self::CANCEL_ON_FIRST_SUCCESS, self::CANCEL_ON_FIRST_COMPLETION], true)) {
            throw new \InvalidArgumentException('Invalid h: ' . $value);
        }
        return new self($lanes, Duration::parseMs($m[2]), $cancel);
    }

    public function cancelPolicy(): int
    {
        return $this->cancel;
    }

    public function staggerDelayMs(): int
    {
        return $this->staggerMs;
    }

    public function lanes(): int
    {
        return $this->lanes;
    }

    public function canonical(): string
    {
        return $this->lanes . '@' . $this->staggerMs . ($this->cancel === self::CANCEL_ON_FIRST_COMPLETION ? '&1' : '');
    }
}
