<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Logic;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * IF: Conditional return based on value matching a comparison.
 *
 * Usage: IF(value, compare, trueResult, falseResult)
 */
class IfFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'IF';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $compare = $params[0] ?? null;
        $trueResult = $params[1] ?? '';
        $falseResult = $params[2] ?? '';

        if (is_numeric($value) && is_numeric($compare)) {
            return ((float) $value === (float) $compare) ? $trueResult : $falseResult;
        }

        if ($compare === 'true') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $trueResult : $falseResult;
        }

        if ($compare === 'false') {
            return !filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $trueResult : $falseResult;
        }

        return ($value === $compare) ? $trueResult : $falseResult;
    }
}
