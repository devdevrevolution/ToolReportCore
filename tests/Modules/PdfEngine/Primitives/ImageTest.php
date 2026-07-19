<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Primitives;

use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Toolreport\Core\Modules\PdfEngine\Primitives\Image;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class ImageTest extends TestCase
{
    // ── getDimensions ──

    #[Test]
    public function getDimensions_returns_defaults_when_no_dimensions_set(): void
    {
        $image = new Image();
        $dims = $image->getDimensions();

        $this->assertSame(0.0, $dims['w']);
        $this->assertSame(0.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_returns_width_height_plus_margin(): void
    {
        $image = new Image();
        $image->setWidth(50);
        $image->setHeight(30);
        $image->setMargin(['top' => 2, 'right' => 3, 'bottom' => 2, 'left' => 3]);

        $dims = $image->getDimensions();

        $this->assertSame(56.0, $dims['w']); // 50 + 3 + 3
        $this->assertSame(34.0, $dims['h']); // 30 + 2 + 2
    }

    #[Test]
    public function getDimensions_with_only_width_uses_zero_for_height(): void
    {
        $image = new Image();
        $image->setWidth(80);

        $dims = $image->getDimensions();

        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(0.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_with_only_height_uses_zero_for_width(): void
    {
        $image = new Image();
        $image->setHeight(60);

        $dims = $image->getDimensions();

        $this->assertSame(0.0, $dims['w']);
        $this->assertSame(60.0, $dims['h']);
    }

    // ── max dimensions ──

    #[Test]
    public function explicitWidthIsRespectedOverMaxWidth(): void
    {
        $image = new Image();
        $image->setWidth(50);
        $image->setMaxWidth(100);

        $dims = $image->getDimensions();

        $this->assertSame(50.0, $dims['w']);
    }

    #[Test]
    public function explicitHeightIsRespectedOverMaxHeight(): void
    {
        $image = new Image();
        $image->setHeight(30);
        $image->setMaxHeight(100);

        $dims = $image->getDimensions();

        $this->assertSame(30.0, $dims['h']);
    }

    #[Test]
    public function maxWidthIsUsedWhenExplicitWidthIsNotSet(): void
    {
        $image = new Image();
        $image->setMaxWidth(80);

        $dims = $image->getDimensions();

        $this->assertSame(80.0, $dims['w']);
    }

    #[Test]
    public function maxHeightIsUsedWhenExplicitHeightIsNotSet(): void
    {
        $image = new Image();
        $image->setMaxHeight(60);

        $dims = $image->getDimensions();

        $this->assertSame(60.0, $dims['h']);
    }

    #[Test]
    public function explicitWidthIsCappedByMaxWidth(): void
    {
        $image = new Image();
        $image->setWidth(100);
        $image->setMaxWidth(50);

        $dims = $image->getDimensions();

        $this->assertSame(50.0, $dims['w']);
    }

    #[Test]
    public function explicitHeightIsCappedByMaxHeight(): void
    {
        $image = new Image();
        $image->setHeight(100);
        $image->setMaxHeight(40);

        $dims = $image->getDimensions();

        $this->assertSame(40.0, $dims['h']);
    }

    // ── Setters ──

    #[Test]
    public function setOpacityClampsAboveOneToOne(): void
    {
        $image = new Image();
        $image->setOpacity(1.5); // Clamped internally; no exception
        $dims = $image->getDimensions(); // Smoke test
        $this->assertSame(0.0, $dims['w']);
    }

    #[Test]
    public function setOpacityClampsBelowZeroToZero(): void
    {
        $image = new Image();
        $image->setOpacity(-0.5); // Clamped internally
        $dims = $image->getDimensions();
        $this->assertSame(0.0, $dims['w']);
    }

    #[Test]
    public function setObjectFitAcceptsValidValues(): void
    {
        $image = new Image();
        $image->setObjectFit('cover');
        $image->setObjectFit('contain');
        $image->setObjectFit('fill');
        $image->setObjectFit('none');
        $this->assertTrue(true); // No exception
    }

    #[Test]
    public function setBorderRadiusAcceptsValue(): void
    {
        $image = new Image();
        $image->setBorderRadius(5.0);
        $this->assertTrue(true); // No exception
    }

    // ── Data setters ──

    #[Test]
    public function globalDataAndLocalDataAreSettable(): void
    {
        $image = new Image();
        $image->setGlobalData(['company' => 'Acme']);
        $image->setLocalData(['logo' => 'acme.png']);
        // No exception means success
        $this->assertTrue(true);
    }

    // ── Margin defaults ──

    #[Test]
    public function defaultMarginIsAllZeros(): void
    {
        $image = new Image();
        $image->setWidth(100);
        $image->setHeight(50);

        $dims = $image->getDimensions();

        $this->assertSame(100.0, $dims['w']);
        $this->assertSame(50.0, $dims['h']);
    }

    #[Test]
    public function partialMarginDefaultsMissingKeysToZero(): void
    {
        $image = new Image();
        $image->setWidth(40);
        $image->setHeight(20);
        $image->setMargin(['top' => 5]); // Only top set

        $dims = $image->getDimensions();

        $this->assertSame(40.0, $dims['w']); // 40 + 0 + 0
        $this->assertSame(25.0, $dims['h']); // 20 + 5 + 0
    }

    // ── render: early returns ──

    #[Test]
    public function render_doesNothingWhenUrlIsEmpty(): void
    {
        $image = new Image();
        // No URL set — render should be a no-op
        $pdf = $this->createMock(\Com\Tecnick\Pdf\Tcpdf::class);
        $image->render($pdf, 10.0, 20.0);
        $this->assertTrue(true);
    }

    #[Test]
    public function render_doesNothingWhenUrlStillContainsPlaceholders(): void
    {
        $image = new Image();
        $image->setUrl('{{logo_url}}');
        // No data set → placeholder remains unresolved
        $pdf = $this->createMock(\Com\Tecnick\Pdf\Tcpdf::class);
        $image->render($pdf, 10.0, 20.0);
        $this->assertTrue(true);
    }

    // ── render: objectFit ──

    #[Test]
    public function render_square_image_in_square_box_ignores_object_fit(): void
    {
        $image = new Image();
        $image->setUrl('https://example.com/square.png');
        $image->setWidth(30);
        $image->setHeight(30);
        $image->setObjectFit('contain');

        [$pdf, $capture] = $this->createMockWithImageCapture(96, 96);

        $image->render($pdf, 10.0, 20.0);

        $this->assertNotNull($capture->value);
        // 96px @ 96dpi → 25.4mm; box 30×30 → contain fills the square box
        $this->assertEqualsWithDelta(30.0, $capture->value[3], 0.001);
        $this->assertEqualsWithDelta(30.0, $capture->value[4], 0.001);
    }

    #[Test]
    public function render_square_image_cover_in_wide_box_covers_without_stretch(): void
    {
        $image = new Image();
        $image->setUrl('https://example.com/square.png');
        $image->setWidth(60);
        $image->setHeight(30);
        $image->setObjectFit('cover');

        [$pdf, $capture] = $this->createMockWithImageCapture(96, 96);

        $image->render($pdf, 10.0, 20.0);

        $this->assertNotNull($capture->value);
        // Square image 25.4mm; box 60×30 → cover scales to 60×60 (clipped vertically)
        $this->assertEqualsWithDelta(60.0, $capture->value[3], 0.001);
        $this->assertEqualsWithDelta(60.0, $capture->value[4], 0.001);
    }

    #[Test]
    public function render_square_image_contain_in_wide_box_fits_without_stretch(): void
    {
        $image = new Image();
        $image->setUrl('https://example.com/square.png');
        $image->setWidth(60);
        $image->setHeight(30);
        $image->setObjectFit('contain');

        [$pdf, $capture] = $this->createMockWithImageCapture(96, 96);

        $image->render($pdf, 10.0, 20.0);

        $this->assertNotNull($capture->value);
        // Square image 25.4mm; box 60×30 → contain scales to 30×30 (letterboxed)
        $this->assertEqualsWithDelta(30.0, $capture->value[3], 0.001);
        $this->assertEqualsWithDelta(30.0, $capture->value[4], 0.001);
    }

    #[Test]
    public function render_square_image_fill_in_wide_box_stretches(): void
    {
        $image = new Image();
        $image->setUrl('https://example.com/square.png');
        $image->setWidth(60);
        $image->setHeight(30);
        $image->setObjectFit('fill');

        [$pdf, $capture] = $this->createMockWithImageCapture(96, 96);

        $image->render($pdf, 10.0, 20.0);

        $this->assertNotNull($capture->value);
        // fill → exact box dimensions
        $this->assertSame(60.0, $capture->value[3]);
        $this->assertSame(30.0, $capture->value[4]);
    }

    /**
     * @return array{0: \Com\Tecnick\Pdf\Tcpdf, 1: stdClass}
     */
    private function createMockWithImageCapture(int $pxWidth, int $pxHeight): array
    {
        $pdf = $this->createMock(\Com\Tecnick\Pdf\Tcpdf::class);
        $page = $this->createMock(\Com\Tecnick\Pdf\Page\Page::class);
        $pdf->page = $page;

        $capture = new stdClass();
        $capture->value = null;

        $imageImport = $this->createMock(\Com\Tecnick\Pdf\Image\Import::class);
        $imageImport->method('add')->willReturn(1);
        $imageImport->method('getKey')->willReturn('key');
        $imageImport->method('getImageDimensionsByKey')->willReturn([
            'width' => $pxWidth,
            'height' => $pxHeight,
        ]);
        $imageImport->method('getSetImage')->willReturnCallback(function (...$args) use ($capture): string {
            $capture->value = $args;
            return 'img ';
        });

        $pdf->image = $imageImport;

        $page->method('addContent');
        $page->method('getPage')->willReturn(['pheight' => 297.0]);
        $pdf->method('toUnit')->willReturnArgument(0);

        return [$pdf, $capture];
    }
}
