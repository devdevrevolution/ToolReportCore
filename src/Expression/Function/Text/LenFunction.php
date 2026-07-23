<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * LEN: Get string length.
 *
 * Usage: LEN(value)
 */
class LenFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'LEN';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return 0;
        }

        return mb_strlen((string) $value);
    }
}
