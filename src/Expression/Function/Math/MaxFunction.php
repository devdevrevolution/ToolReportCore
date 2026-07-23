<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * MAX: Maximum of value and all params.
 *
 * Usage: MAX(a, b, c) or MAX(values[])
 */
class MaxFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'MAX';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $numbers = $this->collectNumbers($value, $params);

        if ($numbers === []) {
            return '';
        }

        return max($numbers);
    }

    /**
     * @return list<float>
     */
    private function collectNumbers(mixed $value, array $params): array
    {
        $numbers = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $numbers[] = (float) $item;
                }
            }
        } elseif (is_numeric($value)) {
            $numbers[] = (float) $value;
        }

        foreach ($params as $param) {
            if (is_numeric($param)) {
                $numbers[] = (float) $param;
            }
        }

        return $numbers;
    }
}
