<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Logic;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * DEFAULT: Provide fallback when value is null or empty.
 *
 * Usage: DEFAULT(value, fallback)
 */
class DefaultFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'DEFAULT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $fallback = $params[0] ?? '';

        if ($value === null || $value === '') {
            return $fallback;
        }

        return $value;
    }
}
