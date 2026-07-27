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
            return $this->resolvePath($data, $key);
        });
    }

    private function resolvePath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        return $this->resolveSegments($data, $segments);
    }

    private function resolveSegments(mixed $current, array $segments): mixed
    {
        while (count($segments) > 0) {
            $segment = array_shift($segments);

            if (str_ends_with($segment, '[]')) {
                $key = substr($segment, 0, -2);

                if (!is_array($current) || !array_key_exists($key, $current)) {
                    return null;
                }

                $items = $current[$key];
                if (!is_array($items)) {
                    return null;
                }

                $results = [];
                foreach (array_values($items) as $item) {
                    $resolved = $this->resolveSegments($item, $segments);
                    if ($resolved !== null) {
                        $results[] = $resolved;
                    }
                }

                return $results !== [] ? $results : null;
            }

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
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

    #[Test]
    public function it_parses_number_with_thousands_separator(): void
    {
        $this->assertEquals('45000000', $this->eval('PARSE_NUMBER("45.000.000")'));
    }

    #[Test]
    public function it_parses_number_with_decimal_separator(): void
    {
        $this->assertEquals('1234.56', $this->eval('PARSE_NUMBER("1.234,56")'));
    }

    #[Test]
    public function it_parses_number_with_both_separators(): void
    {
        $this->assertEquals('45000001.23', $this->eval('PARSE_NUMBER("45.000.001,23")'));
    }

    #[Test]
    public function it_parses_plain_number(): void
    {
        $this->assertEquals('999', $this->eval('PARSE_NUMBER("999")'));
    }

    #[Test]
    public function it_parses_number_with_only_decimal(): void
    {
        $this->assertEquals('100.5', $this->eval('PARSE_NUMBER("100,5")'));
    }

    #[Test]
    public function it_parses_us_format_number(): void
    {
        $this->assertEquals('100.5', $this->eval('PARSE_NUMBER("100.5")'));
    }

    #[Test]
    public function it_parses_numeric_value_directly(): void
    {
        $this->assertEquals('123', $this->eval('PARSE_NUMBER(123)'));
    }

    #[Test]
    public function it_returns_empty_for_invalid_string(): void
    {
        $this->assertEquals('', $this->eval('PARSE_NUMBER("abc")'));
    }

    #[Test]
    public function it_sums_formatted_numbers_automatically(): void
    {
        $data = [
            'items' => [
                ['ki' => '45.000.000'],
                ['ki' => '30.500.000'],
            ],
        ];

        $this->assertEquals('75500000', $this->eval('SUM(items[].ki)', $data));
    }

    #[Test]
    public function it_multiplies_formatted_numbers_automatically(): void
    {
        $this->assertEquals('2469.12', $this->eval('MULTIPLY("1.234,56", 2)'));
    }

    #[Test]
    public function it_divides_formatted_numbers_automatically(): void
    {
        $this->assertEquals('1234.56', $this->eval('DIVIDE("1.234,56", 1)'));
    }

    #[Test]
    public function it_adds_formatted_numbers_automatically(): void
    {
        $this->assertEquals('2469.12', $this->eval('ADD("1.234,56", "1.234,56")'));
    }

    #[Test]
    public function it_subtracts_formatted_numbers_automatically(): void
    {
        $this->assertEquals('0', $this->eval('SUBTRACT("1.234,56", "1.234,56")'));
    }
}
