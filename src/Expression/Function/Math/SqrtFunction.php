<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * SQRT: Square root of value. Negative → empty string.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: SQRT(value)
 */
class SqrtFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'SQRT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $num = $this->parseToFloat($value);
        if ($num === null) {
            return '';
        }

        if ($num < 0) {
            return '';
        }

        return sqrt($num);
    }
}
