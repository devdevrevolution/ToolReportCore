<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Engine;

use Com\Tecnick\Pdf\Font\Stack;
use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Engine\FontMetrics;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class FontMetricsTest extends TestCase
{
    private Tcpdf $pdf;
    private Stack $font_stack;
    private FontMetrics $font_metrics;

    protected function setUp(): void
    {
        $this->pdf = $this->createMock(Tcpdf::class);
        $this->font_stack = $this->createMock(Stack::class);
        $this->pdf->font = $this->font_stack;
        $this->pdf->pon = 0;
        $this->font_metrics = new FontMetrics($this->pdf);
    }

    #[Test]
    public function insertFont_returns_font_data_on_first_call(): void
    {
        $expected = [
            'out' => '/F1 1 Tf',
            'size' => 10.0,
            'ascent' => 700,
            'descent' => -200,
            'height' => 900,
        ];

        $this->font_stack
            ->expects($this->once())
            ->method('insert')
            ->with($this->anything(), 'helvetica', '', 10.0)
            ->willReturn($expected);

        $result = $this->font_metrics->insertFont('helvetica', '', 10.0);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function insertFont_caches_repeated_calls(): void
    {
        $expected = [
            'out' => '/F1 1 Tf',
            'size' => 12.0,
            'ascent' => 800,
            'descent' => -200,
            'height' => 1000,
        ];

        $this->font_stack
            ->expects($this->once())
            ->method('insert')
            ->with($this->anything(), 'helvetica', 'B', 12.0)
            ->willReturn($expected);

        // First call invokes font->insert
        $first = $this->font_metrics->insertFont('helvetica', 'B', 12.0);
        // Second call should use cache
        $second = $this->font_metrics->insertFont('helvetica', 'B', 12.0);

        $this->assertSame($expected, $first);
        $this->assertSame($expected, $second);
    }

    #[Test]
    public function insertFont_uses_different_cache_key_for_different_family(): void
    {
        $font_a = ['out' => '/F1 1 Tf', 'size' => 10.0, 'ascent' => 700, 'descent' => -200, 'height' => 900];
        $font_b = ['out' => '/F2 1 Tf', 'size' => 10.0, 'ascent' => 650, 'descent' => -180, 'height' => 830];

        $this->font_stack
            ->expects($this->exactly(2))
            ->method('insert')
            ->willReturnOnConsecutiveCalls($font_a, $font_b);

        $result_a = $this->font_metrics->insertFont('helvetica', '', 10.0);
        $result_b = $this->font_metrics->insertFont('times', '', 10.0);

        $this->assertSame($font_a, $result_a);
        $this->assertSame($font_b, $result_b);
    }

    #[Test]
    public function getStringWidth_uses_font_ord_width_and_unit_conversion(): void
    {
        $font_metrics = ['out' => '/F1 1 Tf'];

        $this->font_stack
            ->expects($this->once())
            ->method('getOrdArrWidth')
            ->with(array_map('mb_ord', preg_split('//u', 'Hello World', -1, PREG_SPLIT_NO_EMPTY)))
            ->willReturn(455.0);

        $this->pdf
            ->expects($this->once())
            ->method('toUnit')
            ->with(455.0)
            ->willReturn(45.5);

        $result = $this->font_metrics->getStringWidth('Hello World', $font_metrics);

        $this->assertSame(45.5, $result);
    }

    #[Test]
    public function getLineHeight_returns_sum_of_ascent_and_descent_when_present(): void
    {
        $font_metrics = [
            'out' => '/F1 1 Tf',
            'size' => 10.0,
            'ascent' => 700,
            'descent' => -200,
        ];

        $this->pdf
            ->expects($this->exactly(2))
            ->method('toUnit')
            ->willReturnCallback(function (float $value): float {
                return match ($value) {
                    700.0 => 7.0,
                    -200.0 => -2.0,
                };
            });

        $result = $this->font_metrics->getLineHeight($font_metrics);

        $this->assertEquals(9.0, $result);
    }

    #[Test]
    public function getLineHeight_returns_fallback_when_ascent_and_descent_are_missing(): void
    {
        $font_metrics = [
            'out' => '/F1 1 Tf',
            'size' => 10.0,
        ];

        $result = $this->font_metrics->getLineHeight($font_metrics);

        $this->assertEquals(12.0, $result);
    }

    #[Test]
    public function getLineHeight_returns_fallback_when_ascent_is_zero(): void
    {
        $font_metrics = [
            'out' => '/F1 1 Tf',
            'size' => 10.0,
            'ascent' => 0,
            'descent' => 0,
        ];

        $this->pdf
            ->method('toUnit')
            ->willReturn(0.0);

        $result = $this->font_metrics->getLineHeight($font_metrics);

        $this->assertEquals(12.0, $result);
    }
}
