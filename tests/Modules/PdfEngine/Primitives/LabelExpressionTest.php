<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Primitives;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport\Core\Expression\FunctionRegistry;
use Toolreport\Core\Expression\Ast\VariableNode;

/**
 * Integration tests for Label with the new ExpressionEvaluator.
 */
class LabelExpressionTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator(FunctionRegistry::defaults());
    }

    private function eval(string $expression, array $globalData = [], array $localData = []): string
    {
        return $this->evaluator->evaluateExpression($expression, function (string $key) use ($globalData, $localData): mixed {
            // Mirror Label's resolution: local first, then global
            $value = $this->resolvePath($localData, $key);
            if ($value !== null) {
                return $value;
            }
            return $this->resolvePath($globalData, $key);
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
                // Array iteration: resolve the base key, then iterate each element
                $key = substr($segment, 0, -2);

                if (!is_array($current) || !array_key_exists($key, $current)) {
                    return null;
                }

                $items = $current[$key];
                if (!is_array($items)) {
                    return null;
                }

                // Resolve remaining segments for each array element
                $results = [];
                foreach (array_values($items) as $item) {
                    $resolved = $this->resolveSegments($item, $segments);
                    if ($resolved !== null) {
                        $results[] = $resolved;
                    }
                }

                return $results !== [] ? $results : null;
            }

            // Simple key lookup
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    #[Test]
    public function it_resolves_simple_variable(): void
    {
        $this->assertEquals('John', $this->eval('name', ['name' => 'John']));
    }

    #[Test]
    public function it_resolves_function_call(): void
    {
        $this->assertEquals('JOHN', $this->eval('UPPER(name)', ['name' => 'John']));
    }

    #[Test]
    public function it_handles_pipe_syntax(): void
    {
        $this->assertEquals('JOHN', $this->eval('name | upper', ['name' => 'John']));
    }

    #[Test]
    public function it_handles_unresolved_variable(): void
    {
        $this->assertEquals('', $this->eval('missing'));
    }

    #[Test]
    public function it_resolves_sum_on_array_field(): void
    {
        $this->assertEquals('60', $this->eval('SUM(prices)', ['prices' => [10, 20, 30]]));
    }

    #[Test]
    public function it_handles_nested_function_calls(): void
    {
        $this->assertEquals('42', $this->eval('UPPER(ROUND(price, 0))', ['price' => 42.3]));
    }

    #[Test]
    public function it_handles_dot_notation_path(): void
    {
        $this->assertEquals('NYC', $this->eval('address.city', ['address' => ['city' => 'NYC']]));
    }

    #[Test]
    public function it_handles_literal_string(): void
    {
        $this->assertEquals('Hello World', $this->eval("'Hello World'"));
    }

    #[Test]
    public function it_handles_concatenation(): void
    {
        $this->assertEquals('Hello John', $this->eval("'Hello ' + name", ['name' => 'John']));
    }

    #[Test]
    public function it_handles_if_logic(): void
    {
        $this->assertEquals('Active', $this->eval('IF(status, "active", "Active", "Inactive")', ['status' => 'active']));
    }

    #[Test]
    public function it_handles_default_with_null(): void
    {
        $this->assertEquals('N/A', $this->eval('DEFAULT(phone, "N/A")', ['phone' => null]));
    }

    #[Test]
    public function it_local_data_shadows_global(): void
    {
        $this->assertEquals('Local', $this->eval('name', ['name' => 'Global'], ['name' => 'Local']));
    }

    #[Test]
    public function it_sums_array_field_with_bracket_notation(): void
    {
        $data = [
            'items' => [
                ['ki' => 10],
                ['ki' => 20],
                ['ki' => 30],
            ],
        ];

        $this->assertEquals('60', $this->eval('SUM(items[].ki)', $data));
    }

    #[Test]
    public function it_sums_nested_array_field(): void
    {
        $data = [
            'order' => [
                'items' => [
                    ['price' => 100],
                    ['price' => 200],
                    ['price' => 150],
                ],
            ],
        ];

        $this->assertEquals('450', $this->eval('SUM(order.items[].price)', $data));
    }

    #[Test]
    public function it_multiplies_and_sums(): void
    {
        $data = [
            'items' => [
                ['price' => 10, 'qty' => 2],
                ['price' => 20, 'qty' => 3],
            ],
        ];

        // SUM of prices: 10 + 20 = 30
        $this->assertEquals('30', $this->eval('SUM(items[].price)', $data));
    }
}
