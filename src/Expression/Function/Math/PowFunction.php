<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * POW: value ^ first param.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: POW(base, exponent)
 */
class PowFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'POW';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $parsed = $this->parseToFloat($value);
        if ($parsed === null || !isset($params[0])) {
            return '';
        }

        $exponent = $this->parseToFloat($params[0]);
        if ($exponent === null) {
            return '';
        }

        return pow($parsed, $exponent);
    }
}
