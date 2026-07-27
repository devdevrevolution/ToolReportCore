<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * MOD: value % first param. Zero divisor → empty string.
 *
 * Automatically parses formatted number strings (e.g., "1.234,56" → 1234.56).
 *
 * Usage: MOD(a, b)
 */
class ModFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'MOD';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $parsed = $this->parseToFloat($value);
        if ($parsed === null || !isset($params[0])) {
            return '';
        }

        $divisor = $this->parseToFloat($params[0]);
        if ($divisor === null || $divisor === 0.0) {
            return '';
        }

        return fmod($parsed, $divisor);
    }
}
