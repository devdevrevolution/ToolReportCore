<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * CLAMP: Clamp value between min and max.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: CLAMP(value, min, max)
 */
class ClampFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'CLAMP';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $num = $this->parseToFloat($value);
        if ($num === null || !isset($params[0], $params[1])) {
            return '';
        }

        $min = $this->parseToFloat($params[0]);
        $max = $this->parseToFloat($params[1]);
        if ($min === null || $max === null) {
            return '';
        }

        return max($min, min($max, $num));
    }
}
