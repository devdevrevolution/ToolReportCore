<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\Ast\FunctionCallNode;
use Toolreport\Core\Expression\Ast\NumberLiteralNode;
use Toolreport\Core\Expression\Ast\StringLiteralNode;
use Toolreport\Core\Expression\Ast\VariableNode;
use Toolreport\Core\Expression\Evaluator;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport\Core\Expression\FunctionRegistry;
use Toolreport\Core\Expression\Tokenizer;
use Toolreport\Core\Expression\Parser;

class EvaluatorTest extends TestCase
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
    public function it_resolves_a_variable(): void
    {
        $this->assertEquals('John', $this->eval('name', ['name' => 'John']));
    }

    #[Test]
    public function it_resolves_a_number_literal(): void
    {
        $this->assertEquals('42', $this->eval('42'));
    }

    #[Test]
    public function it_resolves_a_string_literal(): void
    {
        $this->assertEquals('hello', $this->eval("'hello'"));
    }

    #[Test]
    public function it_dispatches_function_call(): void
    {
        $this->assertEquals('JOHN', $this->eval('UPPER(name)', ['name' => 'John']));
    }

    #[Test]
    public function it_concatenates_strings(): void
    {
        $this->assertEquals('Hello World', $this->eval("'Hello ' + 'World'"));
    }

    #[Test]
    public function it_concatenates_variable_with_literal(): void
    {
        $this->assertEquals('Hello John', $this->eval("'Hello ' + name", ['name' => 'John']));
    }

    #[Test]
    public function it_applies_pipe_filter(): void
    {
        $this->assertEquals('JOHN', $this->eval('name | upper', ['name' => 'John']));
    }

    #[Test]
    public function it_chains_pipe_filters(): void
    {
        $this->assertEquals('JOHN', $this->eval('name | trim | upper', ['name' => '  john  ']));
    }

    #[Test]
    public function it_handles_fast_path_variable(): void
    {
        $this->assertEquals('John', $this->eval('name', ['name' => 'John']));
    }

    #[Test]
    public function it_returns_empty_for_unresolved_variable(): void
    {
        $this->assertEquals('', $this->eval('missing'));
    }

    #[Test]
    public function it_handles_function_with_multiple_args(): void
    {
        $this->assertEquals('1,234.57', $this->eval('number(amount, 2)', ['amount' => 1234.567]));
    }

    #[Test]
    public function it_handles_nested_function_calls(): void
    {
        // UPPER(ROUND(price, 2)) — ROUND returns float, UPPER stringifies
        $this->assertEquals('42', $this->eval('UPPER(ROUND(price, 0))', ['price' => 42.3]));
    }

    #[Test]
    public function it_handles_binary_op_error_on_unknown_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator');

        // We can't directly test this through evaluateExpression since the parser
        // only produces '+' operator for BinaryOpNode. But the evaluator itself
        // should throw for unknown operators.
        $tokenizer = new Tokenizer();
        $parser = new Parser();
        $evaluator = new Evaluator(FunctionRegistry::defaults());

        $node = new \Toolreport\Core\Expression\Ast\BinaryOpNode(
            new StringLiteralNode('a'),
            '*',
            new StringLiteralNode('b'),
        );

        $evaluator->evaluate($node, fn (string $key) => null);
    }

    #[Test]
    public function it_is_fast_path_for_simple_variable(): void
    {
        $this->assertTrue(ExpressionEvaluator::isFastPath('name'));
        $this->assertTrue(ExpressionEvaluator::isFastPath('address.city'));
        $this->assertTrue(ExpressionEvaluator::isFastPath('[].name'));
        $this->assertTrue(ExpressionEvaluator::isFastPath('orders[].total'));
    }

    #[Test]
    public function it_is_not_fast_path_for_expressions(): void
    {
        $this->assertFalse(ExpressionEvaluator::isFastPath('name | upper'));
        $this->assertFalse(ExpressionEvaluator::isFastPath('SUM(prices)'));
        $this->assertFalse(ExpressionEvaluator::isFastPath("'hello'"));
    }

    #[Test]
    public function it_applies_currency_filter(): void
    {
        $this->assertEquals('$1,234.56', $this->eval('currency(amount, "$")', ['amount' => 1234.56]));
    }

    #[Test]
    public function it_applies_if_filter(): void
    {
        $this->assertEquals('Active', $this->eval('if(status, "active", "Active", "Inactive")', ['status' => 'active']));
    }

    #[Test]
    public function it_applies_default_filter(): void
    {
        $this->assertEquals('N/A', $this->eval('default(phone, "N/A")', ['phone' => null]));
        $this->assertEquals('555-1234', $this->eval('default(phone, "N/A")', ['phone' => '555-1234']));
    }

    #[Test]
    public function it_evaluates_literal_only_expression(): void
    {
        $this->assertEquals('Static Text', $this->eval("'Static Text'"));
    }

    #[Test]
    public function it_evaluates_concatenation_with_multiple_parts(): void
    {
        $this->assertEquals('[ABC] Widget', $this->eval("'[' + code + '] ' + name", ['code' => 'ABC', 'name' => 'Widget']));
    }

    #[Test]
    public function it_sums_array_literal_with_three_elements(): void
    {
        $this->assertEquals('8', $this->eval('SUM([1, 2, 5])'));
    }

    #[Test]
    public function it_sums_array_literal_with_five_elements(): void
    {
        $this->assertEquals('15', $this->eval('SUM([1, 2, 3, 4, 5])'));
    }
}
