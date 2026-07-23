<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression\Functions;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport\Core\Expression\FunctionRegistry;

class FormattingTest extends TestCase
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
    public function it_formats_number_with_defaults(): void
    {
        $this->assertEquals('1,235', $this->eval('FORMAT_NUMBER(amount)', ['amount' => 1234.5]));
    }

    #[Test]
    public function it_formats_number_with_decimals(): void
    {
        $this->assertEquals('1,234.57', $this->eval('FORMAT_NUMBER(amount, 2)', ['amount' => 1234.567]));
    }

    #[Test]
    public function it_formats_number_with_custom_separators(): void
    {
        $this->assertEquals('1.234,56', $this->eval('FORMAT_NUMBER(amount, 2, ",", ".")', ['amount' => 1234.56]));
    }

    #[Test]
    public function it_formats_currency_before(): void
    {
        $this->assertEquals('$1,234.56', $this->eval('FORMAT_CURRENCY(amount, "$")', ['amount' => 1234.56]));
    }

    #[Test]
    public function it_formats_currency_after(): void
    {
        $this->assertEquals('1.234,56 €', $this->eval('FORMAT_CURRENCY(amount, "€", 2, ",", ".", "after")', ['amount' => 1234.56]));
    }

    #[Test]
    public function it_formats_date(): void
    {
        $this->assertEquals('13/06/2026', $this->eval('FORMAT_DATE(created_at, "d/m/Y")', ['created_at' => '2026-06-13']));
    }

    #[Test]
    public function it_applies_if_logic(): void
    {
        $this->assertEquals('Active', $this->eval('IF(status, "active", "Active", "Inactive")', ['status' => 'active']));
        $this->assertEquals('Inactive', $this->eval('IF(status, "active", "Active", "Inactive")', ['status' => 'pending']));
    }

    #[Test]
    public function it_applies_default_logic(): void
    {
        $this->assertEquals('N/A', $this->eval('DEFAULT(phone, "N/A")', ['phone' => null]));
        $this->assertEquals('555-1234', $this->eval('DEFAULT(phone, "N/A")', ['phone' => '555-1234']));
    }

    #[Test]
    public function it_applies_default_with_empty_string(): void
    {
        $this->assertEquals('fallback', $this->eval('DEFAULT(name, "fallback")', ['name' => '']));
    }

    #[Test]
    public function it_returns_empty_for_non_numeric_currency(): void
    {
        $this->assertEquals('', $this->eval('FORMAT_CURRENCY(amount)', ['amount' => 'not a number']));
    }
}
