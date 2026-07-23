<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport\Core\Expression\FunctionRegistry;

/**
 * Backward compatibility tests: existing pipe syntax expressions
 * must work through the NEW pipeline (Tokenizer → Parser → Evaluator).
 */
class BackwardCompatTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator(FunctionRegistry::defaults());
    }

    private function eval(string $expression, array $data = []): string
    {
        return $this->evaluator->evaluateExpression($expression, function (string $key) use ($data): mixed {
            return $data[$key] ?? null;
        });
    }

    #[Test]
    public function it_applies_upper_filter_via_pipe(): void
    {
        $this->assertEquals('JOHN', $this->eval('name | upper', ['name' => 'John']));
    }

    #[Test]
    public function it_applies_currency_filter_via_pipe(): void
    {
        $this->assertEquals('$1,234.56', $this->eval('price | currency("$")', ['price' => 1234.56]));
    }

    #[Test]
    public function it_concatenates_literal_with_filtered_variable(): void
    {
        $this->assertEquals('Total: $1,234.56', $this->eval("'Total: ' + price | currency('$')", ['price' => 1234.56]));
    }

    #[Test]
    public function it_chains_multiple_filters(): void
    {
        $this->assertEquals('JOHN', $this->eval('name | trim | upper', ['name' => '  john  ']));
    }

    #[Test]
    public function it_applies_default_filter_with_null(): void
    {
        $this->assertEquals('N/A', $this->eval('phone | default("N/A")', ['phone' => null]));
    }

    #[Test]
    public function it_applies_substr_filter(): void
    {
        $this->assertEquals('Hello', $this->eval('desc | substr(0, 5)', ['desc' => 'Hello World']));
    }

    #[Test]
    public function it_applies_replace_filter(): void
    {
        $this->assertEquals('hello world', $this->eval('code | replace("_", " ")', ['code' => 'hello_world']));
    }

    #[Test]
    public function it_applies_if_filter(): void
    {
        $this->assertEquals('Active', $this->eval('status | if("active", "Active", "Inactive")', ['status' => 'active']));
    }

    #[Test]
    public function it_applies_number_filter(): void
    {
        $this->assertEquals('1,234.57', $this->eval('amount | number(2)', ['amount' => 1234.567]));
    }

    #[Test]
    public function it_applies_lower_filter(): void
    {
        $this->assertEquals('john doe', $this->eval('name | lower', ['name' => 'JOHN DOE']));
    }

    #[Test]
    public function it_applies_trim_filter(): void
    {
        $this->assertEquals('john', $this->eval('name | trim', ['name' => '  john  ']));
    }

    #[Test]
    public function it_applies_date_filter(): void
    {
        $this->assertEquals('13/06/2026', $this->eval('created_at | date("d/m/Y")', ['created_at' => '2026-06-13']));
    }

    #[Test]
    public function it_concatenates_multiple_parts(): void
    {
        $this->assertEquals('[ABC] Widget', $this->eval("'[' + code + '] ' + name", ['code' => 'ABC', 'name' => 'Widget']));
    }

    #[Test]
    public function it_concatenates_with_literal_only(): void
    {
        $this->assertEquals('Static Text', $this->eval("'Static Text'"));
    }
}
