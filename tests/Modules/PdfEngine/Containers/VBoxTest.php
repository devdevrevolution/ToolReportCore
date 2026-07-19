<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Containers;

use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Containers\VBox;
use Toolreport\Core\Modules\PdfEngine\Contracts\Component;
use Toolreport\Core\Tests\Modules\PdfEngine\CreatesMockComponents;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class VBoxTest extends TestCase
{
    use CreatesMockComponents;

    // ── Single child ──

    #[Test]
    public function getDimensions_with_single_child_returns_child_dimensions(): void
    {
        $child = $this->mockComponent(100.0, 20.0);
        $vbox = new VBox([$child]);

        $dims = $vbox->getDimensions();

        $this->assertSame(100.0, $dims['w']);
        $this->assertSame(20.0, $dims['h']);
    }

    // ── Multiple children ──

    #[Test]
    public function getDimensions_with_multiple_children_sums_heights_and_takes_max_width(): void
    {
        $child_one = $this->mockComponent(100.0, 20.0);
        $child_two = $this->mockComponent(80.0, 30.0);
        $child_three = $this->mockComponent(120.0, 10.0);
        $vbox = new VBox([$child_one, $child_two, $child_three]);

        $dims = $vbox->getDimensions();

        $this->assertSame(120.0, $dims['w']);
        // heights: 20 + 30 + 10 = 60, no padding (default 0)
        $this->assertSame(60.0, $dims['h']);
    }

    // ── Zero padding ──

    #[Test]
    public function getDimensions_with_zero_padding_excludes_padding(): void
    {
        $child_one = $this->mockComponent(50.0, 15.0);
        $child_two = $this->mockComponent(50.0, 25.0);
        $vbox = new VBox([$child_one, $child_two], 0.0);

        $dims = $vbox->getDimensions();

        $this->assertSame(50.0, $dims['w']);
        $this->assertSame(40.0, $dims['h']);
    }

    // ── No children ──

    #[Test]
    public function getDimensions_with_no_children_returns_zero(): void
    {
        $vbox = new VBox([]);

        $dims = $vbox->getDimensions();

        $this->assertSame(0.0, $dims['w']);
        $this->assertSame(0.0, $dims['h']);
    }

    // ── Fixed dimensions ──

    #[Test]
    public function getDimensions_uses_fixed_width_when_larger_than_natural(): void
    {
        $child_one = $this->mockComponent(50.0, 15.0);
        $child_two = $this->mockComponent(50.0, 25.0);
        $vbox = new VBox([$child_one, $child_two]);
        $vbox->setWidth(120.0);

        $dims = $vbox->getDimensions();

        $this->assertSame(120.0, $dims['w']);
        // 15 + 25 = 40, no padding (default 0)
        $this->assertSame(40.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_uses_fixed_height_when_larger_than_natural(): void
    {
        $child_one = $this->mockComponent(50.0, 15.0);
        $child_two = $this->mockComponent(50.0, 25.0);
        $vbox = new VBox([$child_one, $child_two]);
        $vbox->setHeight(80.0);

        $dims = $vbox->getDimensions();

        $this->assertSame(50.0, $dims['w']);
        $this->assertSame(80.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_uses_natural_dimensions_when_fixed_are_smaller(): void
    {
        $child_one = $this->mockComponent(100.0, 20.0);
        $child_two = $this->mockComponent(100.0, 30.0);
        $vbox = new VBox([$child_one, $child_two]);
        $vbox->setWidth(50.0);
        $vbox->setHeight(30.0);

        $dims = $vbox->getDimensions();

        // Natural is larger, so fixed values are ignored
        $this->assertSame(100.0, $dims['w']);
        // 20 + 30 = 50, no padding (default 0)
        $this->assertSame(50.0, $dims['h']);
    }

    // ── Render ──

    #[Test]
    public function render_positions_children_at_correct_y_offsets(): void
    {
        $child_one = $this->mockComponent(100.0, 20.0);
        $child_two = $this->mockComponent(100.0, 30.0);

        $vbox = new VBox([$child_one, $child_two], 5.0);

        $pdf = $this->createMock(Tcpdf::class);

        // Child 1 should render at (10, 20)
        $child_one
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 20.0);

        // Child 2 should render at (10, 20 + 20 + 5 = 45)
        $child_two
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 45.0);

        $vbox->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_with_single_child_renders_at_given_position(): void
    {
        $child = $this->mockComponent(50.0, 10.0);
        $vbox = new VBox([$child]);

        $pdf = $this->createMock(Tcpdf::class);

        $child
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 5.0, 15.0);

        $vbox->render($pdf, 5.0, 15.0);
    }

    #[Test]
    public function render_with_no_children_does_nothing(): void
    {
        $vbox = new VBox([]);
        $pdf = $this->createMock(Tcpdf::class);

        // No exception should be thrown
        $vbox->render($pdf, 0.0, 0.0);
        $this->expectNotToPerformAssertions();
    }

}
