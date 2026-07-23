<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport\Core\Expression\FunctionRegistry;

class PerformanceTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator(FunctionRegistry::defaults());
    }

    private function bench(string $expression, array $data, int $iterations): float
    {
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->evaluator->evaluateExpression($expression, function (string $key) use ($data): mixed {
                return $data[$key] ?? null;
            });
        }
        return microtime(true) - $start;
    }

    #[Test]
    public function fast_path_is_at_least_2x_faster_than_full_pipeline(): void
    {
        $iterations = 1000;

        // Fast path: simple variable resolution
        $fastTime = $this->bench('name', ['name' => 'John'], $iterations);

        // Full pipeline: SUM with array (tokenize → parse → evaluate)
        $fullTime = $this->bench('SUM(values)', ['values' => [10, 20, 30, 40, 50]], $iterations);

        $this->assertLessThan($fullTime / 2, $fastTime,
            "Fast path ({$fastTime}s) should be at least 2x faster than full pipeline ({$fullTime}s)"
        );
    }

    #[Test]
    public function nested_functions_complete_within_reasonable_time(): void
    {
        $iterations = 1000;

        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->evaluator->evaluateExpression(
                'ROUND(MULTIPLY(price, 0.21), 2)',
                fn (string $key) => $key === 'price' ? 100.0 : null,
            );
        }
        $elapsed = microtime(true) - $start;

        // 1000 nested function evaluations should complete in under 2 seconds
        $this->assertLessThan(2.0, $elapsed,
            "1000 nested function evaluations took {$elapsed}s — too slow"
        );
    }

    #[Test]
    public function full_pipeline_with_sum_completes_within_reasonable_time(): void
    {
        $iterations = 1000;
        $data = ['values' => range(1, 100)];

        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->evaluator->evaluateExpression(
                'SUM(values)',
                fn (string $key) => $key === 'values' ? $data['values'] : null,
            );
        }
        $elapsed = microtime(true) - $start;

        // 1000 SUM evaluations on 100-element arrays should complete in under 2 seconds
        $this->assertLessThan(2.0, $elapsed,
            "1000 SUM evaluations on 100 elements took {$elapsed}s — too slow"
        );
    }
}
