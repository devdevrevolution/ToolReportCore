<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * MID: Extract substring from start position with length.
 *
 * Usage: MID(value, start, length)
 */
class MidFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'MID';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        $start = (int) ($params[0] ?? 0);
        $length = (int) ($params[1] ?? mb_strlen($string));

        return mb_substr($string, $start, $length);
    }
}
