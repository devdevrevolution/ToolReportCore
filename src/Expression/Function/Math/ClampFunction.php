<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * CLAMP: Clamp value between min and max.
 *
 * Usage: CLAMP(value, min, max)
 */
class ClampFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'CLAMP';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value) || !isset($params[0], $params[1])
            || !is_numeric($params[0]) || !is_numeric($params[1])) {
            return '';
        }

        $min = (float) $params[0];
        $max = (float) $params[1];
        $num = (float) $value;

        return max($min, min($max, $num));
    }
}
