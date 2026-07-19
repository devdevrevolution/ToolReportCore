<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Primitives;

use Com\Tecnick\Pdf\Page\Page;
use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Engine\FontMetrics;
use Toolreport\Core\Modules\PdfEngine\Primitives\Label;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class LabelTest extends TestCase
{
    private FontMetrics $font_metrics;
    private Label $label;

    protected function setUp(): void
    {
        $this->font_metrics = $this->createMock(FontMetrics::class);
        $this->label = new Label('', $this->font_metrics);
    }

    // ── getDimensions: empty text ──

    #[Test]
    public function getDimensions_returns_line_height_for_empty_text(): void
    {
        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturn(0.0);

        $dims = $this->label->getDimensions();

        $this->assertSame(0.0, $dims['w']);
        $this->assertSame(9.0, $dims['h']);
    }

    // ── getDimensions: single line ──

    #[Test]
    public function getDimensions_returns_correct_width_for_single_line_text(): void
    {
        $label = new Label('Hello World', $this->font_metrics);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Hello World', $this->anything())
            ->willReturn(55.0);

        $dims = $label->getDimensions();

        $this->assertSame(55.0, $dims['w']);
        $this->assertSame(9.0, $dims['h']);
    }

    // ── getDimensions: word-wrapping ──

    #[Test]
    public function getDimensions_wraps_text_when_width_is_set(): void
    {
        $label = new Label('Hello Beautiful World', $this->font_metrics);
        $label->setWidth(30.0);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        // Simulate word wrapping: "Hello Beautiful" > 30, "Hello" fits, "Beautiful" fits,
        // "Hello Beautiful" > 30 → wraps. Then "Beautiful World" > 30, "World" follows.
        $return_map = [
            ['Hello', $this->anything(), 18.0],
            ['Hello Beautiful', $this->anything(), 55.0],
            ['Beautiful', $this->anything(), 35.0],
            ['Beautiful World', $this->anything(), 60.0],
            ['World', $this->anything(), 22.0],
        ];

        // Since getStringWidth may be called multiple times, create a callback stub
        $this->font_metrics
            ->method('getStringWidth')
            ->willReturnCallback(function (string $text, array $font_metrics) use ($return_map): float {
                foreach ($return_map as $entry) {
                    if ($entry[0] === $text) {
                        return $entry[2];
                    }
                }

                return 0.0;
            });

        $dims = $label->getDimensions();

        // 3 lines: "Hello", "Beautiful", "World"
        $this->assertSame(30.0, $dims['w']);
        $this->assertSame(27.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_explicit_width_wins_over_max_width(): void
    {
        $label = new Label('Hello Beautiful World', $this->font_metrics);
        $label->setWidth(30.0);
        $label->setMaxWidth(100.0);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $return_map = [
            ['Hello', $this->anything(), 18.0],
            ['Hello Beautiful', $this->anything(), 55.0],
            ['Beautiful', $this->anything(), 35.0],
            ['Beautiful World', $this->anything(), 60.0],
            ['World', $this->anything(), 22.0],
        ];

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturnCallback(function (string $text, array $font_metrics) use ($return_map): float {
                foreach ($return_map as $entry) {
                    if ($entry[0] === $text) {
                        return $entry[2];
                    }
                }

                return 0.0;
            });

        $dims = $label->getDimensions();

        // Explicit width 30 must be used, not max_width 100
        $this->assertSame(30.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_max_width_used_when_explicit_width_not_set(): void
    {
        $label = new Label('Hello World', $this->font_metrics);
        $label->setMaxWidth(50.0);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturnCallback(function (string $text): float {
                return str_contains($text, ' ') ? 80.0 : 30.0;
            });

        $dims = $label->getDimensions();

        $this->assertSame(50.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_explicit_width_capped_by_max_width(): void
    {
        $label = new Label('Hello World', $this->font_metrics);
        $label->setWidth(80.0);
        $label->setMaxWidth(40.0);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturnCallback(function (string $text): float {
                return str_contains($text, ' ') ? 90.0 : 25.0;
            });

        $dims = $label->getDimensions();

        // Explicit width exceeds max_width, so effective width is capped
        $this->assertSame(40.0, $dims['w']);
    }

    // ── getDimensions: custom font ──

    #[Test]
    public function getDimensions_uses_custom_font_settings(): void
    {
        $label = new Label('Custom', $this->font_metrics);
        $label->setFontFamily('times');
        $label->setFontSize(14);
        $label->setStyle('B');

        $this->font_metrics
            ->expects($this->once())
            ->method('insertFont')
            ->with('times', 'B', 14.0)
            ->willReturn(['out' => '/F2 1 Tf', 'size' => 14, 'ascent' => 900, 'descent' => -250, 'height' => 1150]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(11.5);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturn(40.0);

        $dims = $label->getDimensions();

        $this->assertSame(40.0, $dims['w']);
        $this->assertSame(11.5, $dims['h']);
    }

    // ── Variable interpolation ──

    #[Test]
    public function getDimensions_interpolates_local_variable(): void
    {
        $label = new Label('Hello {{name}}!', $this->font_metrics);
        $label->setLocalData(['name' => 'World']);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Hello World!', $this->anything())
            ->willReturn(55.0);

        $dims = $label->getDimensions();

        $this->assertSame(55.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_falls_back_to_global_data(): void
    {
        $label = new Label('Hello {{name}}!', $this->font_metrics);
        $label->setGlobalData(['name' => 'Glob']);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Hello Glob!', $this->anything())
            ->willReturn(50.0);

        $dims = $label->getDimensions();

        $this->assertSame(50.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_local_overrides_global(): void
    {
        $label = new Label('Hello {{name}}!', $this->font_metrics);
        $label->setLocalData(['name' => 'Local']);
        $label->setGlobalData(['name' => 'Global']);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Hello Local!', $this->anything())
            ->willReturn(52.0);

        $dims = $label->getDimensions();

        $this->assertSame(52.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_keeps_unknown_placeholder_as_literal(): void
    {
        $label = new Label('Hello {{unknown}}!', $this->font_metrics);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Hello {{unknown}}!', $this->anything())
            ->willReturn(70.0);

        $dims = $label->getDimensions();

        $this->assertSame(70.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_resolves_nested_dot_notation(): void
    {
        $label = new Label('{{user.name}}', $this->font_metrics);
        $label->setLocalData(['user' => ['name' => 'Alice']]);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Alice', $this->anything())
            ->willReturn(30.0);

        $dims = $label->getDimensions();

        $this->assertSame(30.0, $dims['w']);
    }

    // ── Render ──

    #[Test]
    public function render_interpolates_local_data_before_rendering(): void
    {
        $label = new Label('Hello {{name}}', $this->font_metrics);
        $label->setLocalData(['name' => 'Local']);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->expects($this->once())
            ->method('getStringWidth')
            ->with('Hello Local', $this->anything())
            ->willReturn(55.0);

        $pdf = $this->createMockWithExtraMethods();
        $text_calls = [];

        $pdf
            ->expects($this->once())
            ->method('addTextCell')
            ->willReturnCallback(function (...$args) use (&$text_calls): void {
                $text_calls[] = $args;
            });

        $label->render($pdf, 10.0, 20.0);

        $this->assertSame('Hello Local', $text_calls[0][0] ?? null);
    }

    #[Test]
    public function render_interpolates_global_data_when_local_is_missing(): void
    {
        $label = new Label('Hello {{name}}', $this->font_metrics);
        $label->setGlobalData(['name' => 'Global']);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->expects($this->once())
            ->method('getStringWidth')
            ->with('Hello Global', $this->anything())
            ->willReturn(60.0);

        $pdf = $this->createMockWithExtraMethods();
        $text_calls = [];

        $pdf
            ->expects($this->once())
            ->method('addTextCell')
            ->willReturnCallback(function (...$args) use (&$text_calls): void {
                $text_calls[] = $args;
            });

        $label->render($pdf, 10.0, 20.0);

        $this->assertSame('Hello Global', $text_calls[0][0] ?? null);
    }

    #[Test]
    public function render_keeps_unknown_placeholders_as_literal(): void
    {
        $label = new Label('Hello {{unknown}}', $this->font_metrics);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->expects($this->once())
            ->method('getStringWidth')
            ->with('Hello {{unknown}}', $this->anything())
            ->willReturn(70.0);

        $pdf = $this->createMockWithExtraMethods();
        $text_calls = [];

        $pdf
            ->expects($this->once())
            ->method('addTextCell')
            ->willReturnCallback(function (...$args) use (&$text_calls): void {
                $text_calls[] = $args;
            });

        $label->render($pdf, 10.0, 20.0);

        $this->assertSame('Hello {{unknown}}', $text_calls[0][0] ?? null);
    }

    #[Test]
    public function render_ignores_named_colors_and_still_renders_text(): void
    {
        $label = new Label('Named Color', $this->font_metrics);
        $label->setColor('red');

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturn(40.0);

        $pdf = $this->createMockWithExtraMethods();

        $pdf
            ->expects($this->once())
            ->method('addTextCell');

        $label->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_calls_addTextCell_for_each_line(): void
    {
        $label = new Label('Line1', $this->font_metrics);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturn(30.0);

        // Build a Tcpdf mock with addMethods for non-existent methods
        $pdf = $this->createMockWithExtraMethods();

        $pdf
            ->expects($this->once())
            ->method('addTextCell');

        $label->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_calls_addTextCell_for_each_line_of_multi_line_text(): void
    {
        $label = new Label('A B C', $this->font_metrics);
        $label->setWidth(20.0);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        // Each single letter fits in 20, but "A B" exceeds it
        $string_width_values = [
            'A' => 5.0,
            'A B' => 25.0,
            'B' => 5.0,
            'B C' => 25.0,
            'C' => 5.0,
        ];

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturnCallback(function (string $text, array $font_metrics) use ($string_width_values): float {
                return $string_width_values[$text] ?? 0.0;
            });

        $pdf = $this->createMockWithExtraMethods();

        // addTextCell should be called once per line: A, B, C
        $pdf
            ->expects($this->exactly(3))
            ->method('addTextCell');

        $label->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_sets_and_restores_font_color(): void
    {
        $label = new Label('Colored', $this->font_metrics);
        $label->setColor('#FF0000');

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturn(35.0);

        $pdf = $this->createMockWithExtraMethods();
        $page = $pdf->page;

        $add_content_calls = [];
        $page
            ->method('addContent')
            ->willReturnCallback(function (string $content) use (&$add_content_calls): void {
                $add_content_calls[] = $content;
            });

        $pdf
            ->expects($this->once())
            ->method('addTextCell');

        $label->render($pdf, 10.0, 20.0);

        $this->assertContains('q', $add_content_calls);
        $this->assertContains('1 0 0 rg', $add_content_calls);
        $this->assertContains('Q', $add_content_calls);
    }

    #[Test]
    public function render_uses_default_font_without_color(): void
    {
        $label = new Label('Plain', $this->font_metrics);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->willReturn(30.0);

        $pdf = $this->createMockWithExtraMethods();
        $page = $pdf->page;

        $add_content_calls = [];
        $page
            ->method('addContent')
            ->willReturnCallback(function (string $content) use (&$add_content_calls): void {
                $add_content_calls[] = $content;
            });

        $pdf
            ->expects($this->once())
            ->method('addTextCell');

        $label->render($pdf, 10.0, 20.0);

        // No color set → no color/graphics-state operators should be emitted
        $this->assertNotContains('q', $add_content_calls);
        $this->assertNotContains('Q', $add_content_calls);
        foreach ($add_content_calls as $content) {
            $this->assertDoesNotMatchRegularExpression('/ rg$/', $content, 'Unexpected font color operator emitted');
        }
    }

    // ── Array [] interpolation ──

    #[Test]
    public function getDimensions_interpolates_array_with_bracket_notation(): void
    {
        $label = new Label('Names: {{results[].name}}', $this->font_metrics);
        $label->setGlobalData([
            'results' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
                ['name' => 'Charlie'],
            ],
        ]);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Names: Alice, Bob, Charlie', $this->anything())
            ->willReturn(120.0);

        $dims = $label->getDimensions();

        $this->assertSame(120.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_interpolates_nested_array_with_bracket_notation(): void
    {
        $label = new Label('Qtys: {{orders[].items[].qty}}', $this->font_metrics);
        $label->setGlobalData([
            'orders' => [
                [
                    'items' => [
                        ['qty' => 2],
                        ['qty' => 5],
                    ],
                ],
                [
                    'items' => [
                        ['qty' => 1],
                    ],
                ],
            ],
        ]);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Qtys: 2, 5, 1', $this->anything())
            ->willReturn(80.0);

        $dims = $label->getDimensions();

        $this->assertSame(80.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_array_notation_returns_placeholder_when_not_found(): void
    {
        $label = new Label('Items: {{missing[].name}}', $this->font_metrics);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Items: {{missing[].name}}', $this->anything())
            ->willReturn(100.0);

        $dims = $label->getDimensions();

        $this->assertSame(100.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_array_notation_local_data_overrides_global(): void
    {
        $label = new Label('{{items[].name}}', $this->font_metrics);
        $label->setLocalData([
            'items' => [['name' => 'Local1'], ['name' => 'Local2']],
        ]);
        $label->setGlobalData([
            'items' => [['name' => 'Global1']],
        ]);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Local1, Local2', $this->anything())
            ->willReturn(80.0);

        $dims = $label->getDimensions();

        $this->assertSame(80.0, $dims['w']);
    }

    #[Test]
    public function getDimensions_interpolates_with_spaces_inside_braces(): void
    {
        $label = new Label('Nombre: {{ results[].name }}', $this->font_metrics);
        $label->setGlobalData([
            'results' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ]);

        $this->font_metrics
            ->method('insertFont')
            ->willReturn(['out' => '/F1 1 Tf', 'size' => 10, 'ascent' => 700, 'descent' => -200, 'height' => 900]);

        $this->font_metrics
            ->method('getLineHeight')
            ->willReturn(9.0);

        $this->font_metrics
            ->method('getStringWidth')
            ->with('Nombre: Alice, Bob', $this->anything())
            ->willReturn(90.0);

        $dims = $label->getDimensions();

        $this->assertSame(90.0, $dims['w']);
    }

    /**
     * Create a Tcpdf mock with a page stub that supports addContent.
     *
     * @return Tcpdf& \PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockWithExtraMethods(): Tcpdf
    {
        $pdf = $this->getMockBuilder(Tcpdf::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addTextCell'])
            ->getMock();

        $page = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addContent', 'getKUnit', 'getPage'])
            ->getMock();

        $page->method('getKUnit')->willReturn(1.0);
        $page->method('getPage')->willReturn(['pheight' => 1000]);

        $pdf->page = $page;

        return $pdf;
    }
}
