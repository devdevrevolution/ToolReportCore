<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * RIGHT: Extract last N characters.
 *
 * Usage: RIGHT(value, count)
 */
class RightFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'RIGHT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        $count = (int) ($params[0] ?? 1);
        $length = mb_strlen($string);

        return mb_substr($string, max(0, $length - $count));
    }
}
