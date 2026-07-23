<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression\Functions;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport_Core\Expression\FunctionRegistry;

class MathTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator(\Toolreport\Core\Expression\FunctionRegistry::defaults());
    }

    private function eval(string $expression, array $data = []): string
    {
        return $this->evaluator->evaluateExpression($expression, function (string $key) use ($data): mixed {
            return $data[$key] ?? null;
        });
    }

    #[Test]
    public function it_sums_array_values(): void
    {
        $this->assertEquals('60', $this->eval('SUM(values)', ['values' => [10, 20, 30]]));
    }

    #[Test]
    public function it_sums_single_value(): void
    {
        $this->assertEquals('42', $this->eval('SUM(amount)', ['amount' => 42]));
    }

    #[Test]
    public function it_multiplies_values(): void
    {
        $this->assertEquals('6', $this->eval('MULTIPLY(a, b)', ['a' => 2, 'b' => 3]));
    }

    #[Test]
    public function it_divides_values(): void
    {
        $this->assertEquals('2.5', $this->eval('DIVIDE(a, b)', ['a' => 5, 'b' => 2]));
    }

    #[Test]
    public function it_returns_empty_on_zero_divisor(): void
    {
        $this->assertEquals('', $this->eval('DIVIDE(a, b)', ['a' => 5, 'b' => 0]));
    }

    #[Test]
    public function it_adds_values(): void
    {
        $this->assertEquals('8', $this->eval('ADD(a, b)', ['a' => 3, 'b' => 5]));
    }

    #[Test]
    public function it_subtracts_values(): void
    {
        $this->assertEquals('3', $this->eval('SUBTRACT(a, b)', ['a' => 5, 'b' => 2]));
    }

    #[Test]
    public function it_calculates_mod(): void
    {
        $this->assertEquals('1', $this->eval('MOD(a, b)', ['a' => 5, 'b' => 2]));
    }

    #[Test]
    public function it_returns_empty_on_zero_mod_divisor(): void
    {
        $this->assertEquals('', $this->eval('MOD(a, b)', ['a' => 5, 'b' => 0]));
    }

    #[Test]
    public function it_calculates_pow(): void
    {
        $this->assertEquals('8', $this->eval('POW(a, b)', ['a' => 2, 'b' => 3]));
    }

    #[Test]
    public function it_calculates_sqrt(): void
    {
        $this->assertEquals('3', $this->eval('SQRT(a)', ['a' => 9]));
    }

    #[Test]
    public function it_returns_empty_on_negative_sqrt(): void
    {
        $this->assertEquals('', $this->eval('SQRT(a)', ['a' => -4]));
    }

    #[Test]
    public function it_calculates_abs(): void
    {
        $this->assertEquals('5', $this->eval('ABS(a)', ['a' => -5]));
    }

    #[Test]
    public function it_rounds_value(): void
    {
        $this->assertEquals('42.35', $this->eval('ROUND(a, 2)', ['a' => 42.345]));
    }

    #[Test]
    public function it_rounds_to_zero_decimals(): void
    {
        $this->assertEquals('42', $this->eval('ROUND(a)', ['a' => 42.3]));
    }

    #[Test]
    public function it_calculates_ceil(): void
    {
        $this->assertEquals('5', $this->eval('CEIL(a)', ['a' => 4.1]));
    }

    #[Test]
    public function it_calculates_floor(): void
    {
        $this->assertEquals('4', $this->eval('FLOOR(a)', ['a' => 4.9]));
    }

    #[Test]
    public function it_finds_min(): void
    {
        $this->assertEquals('1', $this->eval('MIN(a, b, c)', ['a' => 3, 'b' => 1, 'c' => 5]));
    }

    #[Test]
    public function it_finds_max(): void
    {
        $this->assertEquals('5', $this->eval('MAX(a, b, c)', ['a' => 3, 'b' => 1, 'c' => 5]));
    }

    #[Test]
    public function it_clamps_value(): void
    {
        $this->assertEquals('5', $this->eval('CLAMP(a, 1, 10)', ['a' => 5]));
        $this->assertEquals('1', $this->eval('CLAMP(a, 1, 10)', ['a' => -5]));
        $this->assertEquals('10', $this->eval('CLAMP(a, 1, 10)', ['a' => 15]));
    }

    #[Test]
    public function it_returns_empty_for_non_numeric_input(): void
    {
        $this->assertEquals('0', $this->eval('SUM(a)', ['a' => 'not a number']));
    }
}
