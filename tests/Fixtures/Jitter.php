<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\JitterInterface;
use Random\Randomizer;

final class Jitter implements JitterInterface
{
    public function __construct(
        private readonly string $mode,
        private readonly ?float $percent,
        private readonly ?int $windowMs,
    ) {
    }

    public static function parse(string $value): self
    {
        $v = strtolower(trim($value));
        if ($v === '0' || $v === 'none' || $v === '0%') {
            return new self(self::MODE_NONE, null, 0);
        }
        $mode = self::MODE_FULL;
        if (str_contains($v, '@')) {
            [$v, $mode] = explode('@', $v, 2);
            if (!in_array($mode, [self::MODE_FULL, self::MODE_PM, self::MODE_NONE], true)) {
                throw new \InvalidArgumentException('Invalid jitter mode: ' . $mode);
            }
        }
        if (str_ends_with($v, '%')) {
            $number = substr($v, 0, -1);
            if (!is_numeric($number) || (float) $number < 0 || (float) $number > 100) {
                throw new \InvalidArgumentException('Invalid jitter percent: ' . $value);
            }
            return new self($mode, (float) $number, null);
        }
        return new self($mode, null, Duration::parseMs($v));
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function percent(): ?float
    {
        return $this->percent;
    }

    public function windowMs(): ?int
    {
        return $this->windowMs;
    }

    public function apply(int $nominalDelayMs, Randomizer $random): int
    {
        $d = max(0, $nominalDelayMs);
        if ($this->mode === self::MODE_NONE) {
            return $d;
        }
        $w = $this->percent !== null ? (int) round($this->percent / 100 * $d) : (int) $this->windowMs;
        $low = max(0, $d - $w);
        $high = $this->mode === self::MODE_PM ? $d + $w : $d;
        return $low < $high ? $random->getInt($low, $high) : $low;
    }

    public function canonical(): string
    {
        if ($this->mode === self::MODE_NONE) {
            return 'none';
        }
        $amount = $this->percent !== null ? self::number($this->percent) . '%' : (string) $this->windowMs;
        return $amount . '@' . $this->mode;
    }

    public static function number(float $n): string
    {
        return rtrim(rtrim(sprintf('%.6F', $n), '0'), '.');
    }
}
