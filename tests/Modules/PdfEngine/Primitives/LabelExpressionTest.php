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
        $current = $data;

        foreach ($segments as $segment) {
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
}
