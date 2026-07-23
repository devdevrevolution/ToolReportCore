<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\Token;
use Toolreport\Core\Expression\Tokenizer;
use Toolreport\Core\Expression\TokenType;

class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }

    #[Test]
    public function it_tokenizes_a_simple_variable(): void
    {
        $tokens = $this->tokenizer->tokenize('name');

        $this->assertCount(2, $tokens); // name + EOF
        $this->assertEquals(TokenType::VARIABLE, $tokens[0]->type);
        $this->assertEquals('name', $tokens[0]->value);
        $this->assertEquals(TokenType::EOF, $tokens[1]->type);
    }

    #[Test]
    public function it_tokenizes_a_function_call(): void
    {
        $tokens = $this->tokenizer->tokenize('SUM(prices)');

        $this->assertCount(5, $tokens); // SUM ( prices ) EOF
        $this->assertEquals(TokenType::FUNCTION, $tokens[0]->type);
        $this->assertEquals('SUM', $tokens[0]->value);
        $this->assertEquals(TokenType::LPAREN, $tokens[1]->type);
        $this->assertEquals(TokenType::VARIABLE, $tokens[2]->type);
        $this->assertEquals('prices', $tokens[2]->value);
        $this->assertEquals(TokenType::RPAREN, $tokens[3]->type);
    }

    #[Test]
    public function it_tokenizes_a_number_literal(): void
    {
        $tokens = $this->tokenizer->tokenize('42');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::NUMBER, $tokens[0]->type);
        $this->assertEquals('42', $tokens[0]->value);
    }

    #[Test]
    public function it_tokenizes_a_decimal_number(): void
    {
        $tokens = $this->tokenizer->tokenize('3.14');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::NUMBER, $tokens[0]->type);
        $this->assertEquals('3.14', $tokens[0]->value);
    }

    #[Test]
    public function it_tokenizes_a_single_quoted_string(): void
    {
        $tokens = $this->tokenizer->tokenize("'hello'");

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::STRING, $tokens[0]->type);
        $this->assertEquals('hello', $tokens[0]->value);
    }

    #[Test]
    public function it_tokenizes_a_double_quoted_string(): void
    {
        $tokens = $this->tokenizer->tokenize('"hello"');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::STRING, $tokens[0]->type);
        $this->assertEquals('hello', $tokens[0]->value);
    }

    #[Test]
    public function it_tokenizes_operators(): void
    {
        $tokens = $this->tokenizer->tokenize('|');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::OPERATOR, $tokens[0]->type);
        $this->assertEquals('|', $tokens[0]->value);

        $tokens = $this->tokenizer->tokenize('+');
        $this->assertEquals(TokenType::OPERATOR, $tokens[0]->type);
        $this->assertEquals('+', $tokens[0]->value);
    }

    #[Test]
    public function it_tokenizes_parentheses_and_commas(): void
    {
        $tokens = $this->tokenizer->tokenize('()');

        $this->assertCount(3, $tokens);
        $this->assertEquals(TokenType::LPAREN, $tokens[0]->type);
        $this->assertEquals(TokenType::RPAREN, $tokens[1]->type);

        $tokens = $this->tokenizer->tokenize(',');
        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::COMMA, $tokens[0]->type);
    }

    #[Test]
    public function it_tokenizes_a_concatenated_expression(): void
    {
        $tokens = $this->tokenizer->tokenize("'Total: ' + price");

        // 'Total: ' + price EOF
        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::STRING, $tokens[0]->type);
        $this->assertEquals(TokenType::OPERATOR, $tokens[1]->type);
        $this->assertEquals('+', $tokens[1]->value);
        $this->assertEquals(TokenType::VARIABLE, $tokens[2]->type);
    }

    #[Test]
    public function it_tokenizes_a_pipe_chain(): void
    {
        $tokens = $this->tokenizer->tokenize('name | trim | upper');

        // name | trim | upper EOF
        $this->assertCount(6, $tokens);
        $this->assertEquals(TokenType::VARIABLE, $tokens[0]->type);
        $this->assertEquals(TokenType::OPERATOR, $tokens[1]->type);
        $this->assertEquals(TokenType::VARIABLE, $tokens[2]->type);
        $this->assertEquals('trim', $tokens[2]->value);
        $this->assertEquals(TokenType::OPERATOR, $tokens[3]->type);
        $this->assertEquals(TokenType::VARIABLE, $tokens[4]->type);
        $this->assertEquals('upper', $tokens[4]->value);
    }

    #[Test]
    public function it_handles_whitespace(): void
    {
        $tokens = $this->tokenizer->tokenize('  name  |  upper  ');

        // name | upper EOF
        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::VARIABLE, $tokens[0]->type);
        $this->assertEquals('name', $tokens[0]->value);
        $this->assertEquals(TokenType::OPERATOR, $tokens[1]->type);
        $this->assertEquals(TokenType::VARIABLE, $tokens[2]->type);
    }

    #[Test]
    public function it_throws_on_unterminated_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unterminated string');

        $this->tokenizer->tokenize("'hello");
    }

    #[Test]
    public function it_tracks_position(): void
    {
        $tokens = $this->tokenizer->tokenize('name + 42');

        $this->assertEquals(0, $tokens[0]->position); // name
        $this->assertEquals(5, $tokens[1]->position); // +
        $this->assertEquals(7, $tokens[2]->position); // 42
    }

    #[Test]
    public function it_tokenizes_function_with_multiple_args(): void
    {
        $tokens = $this->tokenizer->tokenize('ROUND(price, 2)');

        // ROUND ( price , 2 ) EOF
        $this->assertCount(7, $tokens);
        $this->assertEquals(TokenType::FUNCTION, $tokens[0]->type);
        $this->assertEquals('ROUND', $tokens[0]->value);
        $this->assertEquals(TokenType::LPAREN, $tokens[1]->type);
        $this->assertEquals(TokenType::VARIABLE, $tokens[2]->type);
        $this->assertEquals(TokenType::COMMA, $tokens[3]->type);
        $this->assertEquals(TokenType::NUMBER, $tokens[4]->type);
        $this->assertEquals('2', $tokens[4]->value);
        $this->assertEquals(TokenType::RPAREN, $tokens[5]->type);
    }

    #[Test]
    public function it_tokenizes_variable_with_bracket_notation(): void
    {
        $tokens = $this->tokenizer->tokenize('[].name');

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::VARIABLE, $tokens[0]->type);
        $this->assertEquals('[].name', $tokens[0]->value);
    }
}
