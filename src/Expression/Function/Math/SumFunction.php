<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * SUM: Flatten arrays and sum all numeric values.
 *
 * Usage: SUM(values[]) or SUM(a, b, c)
 */
class SumFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'SUM';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $numbers = $this->collectNumbers($value, $params);

        if ($numbers === []) {
            return 0;
        }

        return array_sum($numbers);
    }

    /**
     * @return list<float>
     */
    private function collectNumbers(mixed $value, array $params): array
    {
        $numbers = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    foreach ($item as $v) {
                        if (is_numeric($v)) {
                            $numbers[] = (float) $v;
                        }
                    }
                } elseif (is_numeric($item)) {
                    $numbers[] = (float) $item;
                }
            }
        } elseif (is_numeric($value)) {
            $numbers[] = (float) $value;
        }

        foreach ($params as $param) {
            if (is_array($param)) {
                foreach ($param as $item) {
                    if (is_numeric($item)) {
                        $numbers[] = (float) $item;
                    }
                }
            } elseif (is_numeric($param)) {
                $numbers[] = (float) $param;
            }
        }

        return $numbers;
    }
}
