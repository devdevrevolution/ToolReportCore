<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * ADD: value + sum of params.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: ADD(a, b) or ADD(value, 1, 2, 3)
 */
class AddFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'ADD';
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
                $result += $parsed;
            }
        }

        return $result;
    }
}
