<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine\Containers;

use Com\Tecnick\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Modules\PdfEngine\Containers\HBox;
use Toolreport\Core\Tests\Modules\PdfEngine\CreatesMockComponents;
use Toolreport\Core\Tests\Modules\PdfEngine\TestCase;

class HBoxTest extends TestCase
{
    use CreatesMockComponents;

    // ── Single child ──

    #[Test]
    public function getDimensions_with_single_child_returns_child_dimensions(): void
    {
        $child = $this->mockComponent(100.0, 20.0);
        $hbox = new HBox([$child]);

        $dims = $hbox->getDimensions();

        $this->assertSame(100.0, $dims['w']);
        $this->assertSame(20.0, $dims['h']);
    }

    // ── Multiple children ──

    #[Test]
    public function getDimensions_with_multiple_children_sums_widths_and_takes_max_height(): void
    {
        $child_one = $this->mockComponent(100.0, 20.0);
        $child_two = $this->mockComponent(80.0, 30.0);
        $child_three = $this->mockComponent(60.0, 15.0);
        $hbox = new HBox([$child_one, $child_two, $child_three]);

        $dims = $hbox->getDimensions();

        $this->assertSame(240.0, $dims['w']);
        $this->assertSame(30.0, $dims['h']);
    }

    // ── No children ──

    #[Test]
    public function getDimensions_with_no_children_returns_zero(): void
    {
        $hbox = new HBox([]);

        $dims = $hbox->getDimensions();

        $this->assertSame(0.0, $dims['w']);
        $this->assertSame(0.0, $dims['h']);
    }

    // ── Fixed width ──

    #[Test]
    public function getDimensions_uses_fixed_width_when_larger_than_natural(): void
    {
        $child_one = $this->mockComponent(50.0, 20.0);
        $child_two = $this->mockComponent(30.0, 10.0);
        $hbox = new HBox([$child_one, $child_two]);
        $hbox->setWidth(120.0);

        $dims = $hbox->getDimensions();

        $this->assertSame(120.0, $dims['w']);
        $this->assertSame(20.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_uses_natural_width_when_fixed_is_smaller(): void
    {
        $child_one = $this->mockComponent(50.0, 20.0);
        $child_two = $this->mockComponent(30.0, 10.0);
        $hbox = new HBox([$child_one, $child_two]);
        $hbox->setWidth(60.0);

        $dims = $hbox->getDimensions();

        // Natural is 80, fixed is 60 but smaller → use natural
        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(20.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_uses_fixed_height_when_larger_than_natural(): void
    {
        $child_one = $this->mockComponent(50.0, 20.0);
        $child_two = $this->mockComponent(30.0, 10.0);
        $hbox = new HBox([$child_one, $child_two]);
        $hbox->setHeight(40.0);

        $dims = $hbox->getDimensions();

        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(40.0, $dims['h']);
    }

    #[Test]
    public function getDimensions_uses_natural_height_when_fixed_is_smaller(): void
    {
        $child_one = $this->mockComponent(50.0, 20.0);
        $child_two = $this->mockComponent(30.0, 10.0);
        $hbox = new HBox([$child_one, $child_two]);
        $hbox->setHeight(5.0);

        $dims = $hbox->getDimensions();

        // Natural max height is 20, fixed is smaller → use natural
        $this->assertSame(80.0, $dims['w']);
        $this->assertSame(20.0, $dims['h']);
    }

    // ── Render ──

    #[Test]
    public function render_positions_children_at_correct_x_offsets(): void
    {
        $child_one = $this->mockComponent(100.0, 20.0);
        $child_two = $this->mockComponent(80.0, 30.0);
        $child_three = $this->mockComponent(60.0, 15.0);

        $hbox = new HBox([$child_one, $child_two, $child_three]);

        $pdf = $this->createMock(Tcpdf::class);

        // Child 1 at x=10
        $child_one
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 20.0);

        // Child 2 at x=10+100=110
        $child_two
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 110.0, 20.0);

        // Child 3 at x=110+80=190
        $child_three
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 190.0, 20.0);

        $hbox->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_keeps_natural_widths_when_fixed_width_larger(): void
    {
        $child_one = $this->mockComponent(50.0, 20.0);
        $child_two = $this->mockComponent(30.0, 10.0);

        $hbox = new HBox([$child_one, $child_two]);
        $hbox->setWidth(120.0);

        $pdf = $this->createMock(Tcpdf::class);

        // Natural = 80, Fixed = 120 — children keep their natural widths.
        // Extra space is empty (not distributed).
        // Child1 at x=10, Child2 at x=10+50=60
        $child_one
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 10.0, 20.0);

        $child_two
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 60.0, 20.0);

        $hbox->render($pdf, 10.0, 20.0);
    }

    #[Test]
    public function render_with_single_child_renders_at_given_position(): void
    {
        $child = $this->mockComponent(50.0, 10.0);
        $hbox = new HBox([$child]);

        $pdf = $this->createMock(Tcpdf::class);

        $child
            ->expects($this->once())
            ->method('render')
            ->with($pdf, 5.0, 15.0);

        $hbox->render($pdf, 5.0, 15.0);
    }

    #[Test]
    public function render_with_no_children_does_nothing(): void
    {
        $hbox = new HBox([]);
        $pdf = $this->createMock(Tcpdf::class);

        $hbox->render($pdf, 0.0, 0.0);
        $this->expectNotToPerformAssertions();
    }
}
