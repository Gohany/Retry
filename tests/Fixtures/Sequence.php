<?php declare(strict_types=1);

namespace Gohany\Retry\Tests\Fixtures;

use Gohany\Retry\SequenceInterface;

final class Sequence implements SequenceInterface
{
    /** @param list<int> $delaysMs */
    public function __construct(private readonly array $delaysMs, private readonly bool $repeatLast)
    {
    }

    public static function parse(string $value): self
    {
        $v = trim($value);
        if (str_starts_with($v, '(') && str_ends_with($v, ')')) {
            $v = substr($v, 1, -1);
        }
        $repeat = false;
        $items = array_map('trim', explode(',', $v));
        $last = array_key_last($items);
        if ($items[$last] === '*') {
            $repeat = true;
            array_pop($items);
        } elseif (str_ends_with($items[$last], '*')) {
            $repeat = true;
            $items[$last] = substr($items[$last], 0, -1);
        }
        if ($items === [] || in_array('', $items, true)) {
            throw new \InvalidArgumentException('Invalid seq: ' . $value);
        }
        return new self(array_map(Duration::parseMs(...), $items), $repeat);
    }

    public function delaysMs(): array
    {
        return $this->delaysMs;
    }

    public function isRepeatLastDelay(): bool
    {
        return $this->repeatLast;
    }

    public function delayMsAt(int $attemptNumber): ?int
    {
        $i = $attemptNumber - 2;
        if ($i < 0) {
            return null;
        }
        if ($i < count($this->delaysMs)) {
            return $this->delaysMs[$i];
        }
        return $this->repeatLast ? $this->delaysMs[count($this->delaysMs) - 1] : null;
    }

    public function canonical(): string
    {
        return implode(',', $this->delaysMs) . ($this->repeatLast ? '*' : '');
    }
}
