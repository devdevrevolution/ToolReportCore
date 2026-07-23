<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit\Expression\Functions;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Toolreport\Core\Expression\ExpressionEvaluator;
use Toolreport\Core\Expression\FunctionRegistry;

class TextTest extends TestCase
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
    public function it_converts_to_uppercase(): void
    {
        $this->assertEquals('HELLO', $this->eval('UPPER(name)', ['name' => 'hello']));
    }

    #[Test]
    public function it_converts_to_lowercase(): void
    {
        $this->assertEquals('hello', $this->eval('LOWER(name)', ['name' => 'HELLO']));
    }

    #[Test]
    public function it_trims_whitespace(): void
    {
        $this->assertEquals('hello', $this->eval('TRIM(name)', ['name' => '  hello  ']));
    }

    #[Test]
    public function it_extracts_substring(): void
    {
        $this->assertEquals('Hello', $this->eval('SUBSTR(text, 0, 5)', ['text' => 'Hello World']));
    }

    #[Test]
    public function it_replaces_strings(): void
    {
        $this->assertEquals('hello world', $this->eval('REPLACE(text, "_", " ")', ['text' => 'hello_world']));
    }

    #[Test]
    public function it_concats_values(): void
    {
        $this->assertEquals('Hello World', $this->eval('CONCAT(a, " ", b)', ['a' => 'Hello', 'b' => 'World']));
    }

    #[Test]
    public function it_extracts_left(): void
    {
        $this->assertEquals('Hel', $this->eval('LEFT(text, 3)', ['text' => 'Hello']));
    }

    #[Test]
    public function it_extracts_right(): void
    {
        $this->assertEquals('llo', $this->eval('RIGHT(text, 3)', ['text' => 'Hello']));
    }

    #[Test]
    public function it_extracts_mid(): void
    {
        $this->assertEquals('llo', $this->eval('MID(text, 2, 3)', ['text' => 'Hello']));
    }

    #[Test]
    public function it_returns_length(): void
    {
        $this->assertEquals('5', $this->eval('LEN(text)', ['text' => 'Hello']));
    }

    #[Test]
    public function it_returns_zero_for_null_length(): void
    {
        $this->assertEquals('0', $this->eval('LEN(text)', ['text' => null]));
    }

    #[Test]
    public function it_handles_empty_string(): void
    {
        $this->assertEquals('', $this->eval('UPPER(name)', ['name' => '']));
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        $this->assertEquals('HÉLLO', $this->eval('UPPER(name)', ['name' => 'héllo']));
    }
}
