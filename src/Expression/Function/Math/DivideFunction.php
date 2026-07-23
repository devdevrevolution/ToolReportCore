<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * DIVIDE: value / first param. Zero divisor → empty string.
 *
 * Usage: DIVIDE(a, b)
 */
class DivideFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'DIVIDE';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value) || !isset($params[0]) || !is_numeric($params[0])) {
            return '';
        }

        $divisor = (float) $params[0];

        if ($divisor === 0.0) {
            return '';
        }

        return (float) $value / $divisor;
    }
}
