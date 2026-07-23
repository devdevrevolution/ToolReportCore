<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * ADD: value + sum of params.
 *
 * Usage: ADD(a, b) or ADD(value, 1, 2, 3)
 */
class AddFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'ADD';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_numeric($value)) {
            return '';
        }

        $result = (float) $value;

        foreach ($params as $param) {
            if (is_numeric($param)) {
                $result += (float) $param;
            }
        }

        return $result;
    }
}
