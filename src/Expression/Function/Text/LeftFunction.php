<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * LEFT: Extract first N characters.
 *
 * Usage: LEFT(value, count)
 */
class LeftFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'LEFT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        $count = (int) ($params[0] ?? 1);

        return mb_substr($string, 0, $count);
    }
}
