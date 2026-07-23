<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\Ast\BinaryOpNode;
use Toolreport\Core\Expression\Ast\FilterChainNode;
use Toolreport\Core\Expression\Ast\FunctionCallNode;
use Toolreport\Core\Expression\Ast\NumberLiteralNode;
use Toolreport\Core\Expression\Ast\StringLiteralNode;
use Toolreport\Core\Expression\Ast\VariableNode;
use Toolreport\Core\Expression\Parser;
use Toolreport\Core\Expression\Tokenizer;

class ParserTest extends TestCase
{
    private Parser $parser;
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->tokenizer = new Tokenizer();
    }

    private function parse(string $expression): \Toolreport\Core\Expression\Ast\ExpressionNode
    {
        return $this->parser->parse($this->tokenizer->tokenize($expression));
    }

    #[Test]
    public function it_parses_a_simple_variable(): void
    {
        $node = $this->parse('name');

        $this->assertInstanceOf(VariableNode::class, $node);
        $this->assertEquals('name', $node->path);
    }

    #[Test]
    public function it_parses_a_function_call(): void
    {
        $node = $this->parse('SUM(prices)');

        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertEquals('SUM', $node->name);
        $this->assertCount(1, $node->args);
        $this->assertInstanceOf(VariableNode::class, $node->args[0]);
        $this->assertEquals('prices', $node->args[0]->path);
    }

    #[Test]
    public function it_parses_function_with_multiple_args(): void
    {
        $node = $this->parse('ROUND(price, 2)');

        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertEquals('ROUND', $node->name);
        $this->assertCount(2, $node->args);
        $this->assertInstanceOf(VariableNode::class, $node->args[0]);
        $this->assertInstanceOf(NumberLiteralNode::class, $node->args[1]);
        $this->assertEquals(2.0, $node->args[1]->value);
    }

    #[Test]
    public function it_parses_string_literal(): void
    {
        $node = $this->parse("'hello'");

        $this->assertInstanceOf(StringLiteralNode::class, $node);
        $this->assertEquals('hello', $node->value);
    }

    #[Test]
    public function it_parses_number_literal(): void
    {
        $node = $this->parse('42');

        $this->assertInstanceOf(NumberLiteralNode::class, $node);
        $this->assertEquals(42.0, $node->value);
    }

    #[Test]
    public function it_parses_binary_concatenation(): void
    {
        $node = $this->parse("'Total: ' + price");

        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertEquals('+', $node->operator);
        $this->assertInstanceOf(StringLiteralNode::class, $node->left);
        $this->assertInstanceOf(VariableNode::class, $node->right);
    }

    #[Test]
    public function it_parses_pipe_chain(): void
    {
        $node = $this->parse('name | trim | upper');

        $this->assertInstanceOf(FilterChainNode::class, $node);
        $this->assertInstanceOf(VariableNode::class, $node->base);
        $this->assertCount(2, $node->filters);
        $this->assertEquals('trim', $node->filters[0]->name);
        $this->assertEquals('upper', $node->filters[1]->name);
    }

    #[Test]
    public function it_parses_nested_function_calls(): void
    {
        $node = $this->parse('UPPER(ROUND(price, 2))');

        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertEquals('UPPER', $node->name);
        $this->assertCount(1, $node->args);
        $this->assertInstanceOf(FunctionCallNode::class, $node->args[0]);
        $this->assertEquals('ROUND', $node->args[0]->name);
    }

    #[Test]
    public function it_parses_parenthesized_expression(): void
    {
        $node = $this->parse('(name)');

        $this->assertInstanceOf(VariableNode::class, $node);
        $this->assertEquals('name', $node->path);
    }

    #[Test]
    public function it_parses_pipe_binds_tighter_than_concatenation(): void
    {
        // a | upper + b → (a | upper) + b
        $node = $this->parse('name | upper + "!"');

        $this->assertInstanceOf(BinaryOpNode::class, $node);
        $this->assertInstanceOf(FilterChainNode::class, $node->left);
        $this->assertInstanceOf(StringLiteralNode::class, $node->right);
    }

    #[Test]
    public function it_throws_on_mismatched_parens(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parse('SUM(prices');
    }

    #[Test]
    public function it_throws_on_unexpected_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parse('+');
    }

    #[Test]
    public function it_parses_function_with_empty_args(): void
    {
        $node = $this->parse('SUM()');

        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertEquals('SUM', $node->name);
        $this->assertCount(0, $node->args);
    }

    #[Test]
    public function it_parses_function_with_string_args(): void
    {
        $node = $this->parse('currency("$")');

        // currency is a variable (not followed by ( in tokenization... wait, it IS followed by ()
        // Actually "currency" followed by "(" should be tokenized as FUNCTION
        $this->assertInstanceOf(FunctionCallNode::class, $node);
        $this->assertEquals('currency', $node->name);
        $this->assertCount(1, $node->args);
        $this->assertInstanceOf(StringLiteralNode::class, $node->args[0]);
        $this->assertEquals('$', $node->args[0]->value);
    }
}
