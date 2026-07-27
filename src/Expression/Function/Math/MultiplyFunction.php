<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * MULTIPLY: Product of value × all params.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: MULTIPLY(a, b) or MULTIPLY(value, 2, 3)
 */
class MultiplyFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'MULTIPLY';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $parsed = $this->parseToFloat($value);
        if ($parsed === null) {
            return '';
        }

        $result = $parsed;

        foreach ($params as $param) {
            $parsed = $this->parseToFloat($param);
            if ($parsed !== null) {
                $result *= $parsed;
            }
        }

        return $result;
    }
}
