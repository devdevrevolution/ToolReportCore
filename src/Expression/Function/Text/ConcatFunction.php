<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Text;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * CONCAT: Concatenate multiple values.
 *
 * Usage: CONCAT(a, b, c) or CONCAT(values[])
 */
class ConcatFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'CONCAT';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $parts = [];

        if ($value !== null) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = $this->stringify($item);
                }
            } else {
                $parts[] = $this->stringify($value);
            }
        }

        foreach ($params as $param) {
            if (is_array($param)) {
                foreach ($param as $item) {
                    $parts[] = $this->stringify($item);
                }
            } else {
                $parts[] = $this->stringify($param);
            }
        }

        return implode('', $parts);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
