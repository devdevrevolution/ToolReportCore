<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * SUBTRACT: value - first param.
 *
 * Usage: SUBTRACT(a, b)
 */
class SubtractFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'SUBTRACT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value) || !isset($params[0]) || !is_numeric($params[0])) {
            return '';
        }

        return (float) $value - (float) $params[0];
    }
}
