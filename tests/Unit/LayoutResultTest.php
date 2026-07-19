<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\DataTransferObjects\LayoutResult;
use Toolreport\Core\Tests\TestCase;

class LayoutResultTest extends TestCase
{
    #[Test]
    public function body_content_extracts_inner_html_from_body_tag(): void
    {
        $layout = new LayoutResult(
            html: '<!DOCTYPE html><html><head><style>body { margin: 0; }</style></head><body><div class="pdf-band">Content here</div></body></html>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $body = $layout->bodyContent();

        $this->assertStringContainsString('<div class="pdf-band">Content here</div>', $body);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('<head>', $body);
        $this->assertStringNotContainsString('</head>', $body);
        $this->assertStringNotContainsString('<body>', $body);
    }

    #[Test]
    public function head_style_extracts_css_from_style_tag(): void
    {
        $layout = new LayoutResult(
            html: '<!DOCTYPE html><html><head><style>body { margin: 0; } .pdf-element { position: absolute; }</style></head><body>Content</body></html>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $style = $layout->headStyle();

        $this->assertStringContainsString('body { margin: 0; }', $style);
        $this->assertStringContainsString('.pdf-element { position: absolute; }', $style);
        $this->assertStringNotContainsString('<style>', $style);
        $this->assertStringNotContainsString('</style>', $style);
    }

    #[Test]
    public function body_content_returns_full_html_when_no_body_tag(): void
    {
        $layout = new LayoutResult(
            html: '<div>Plain content</div>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $body = $layout->bodyContent();

        $this->assertEquals('<div>Plain content</div>', $body);
    }

    #[Test]
    public function head_style_returns_empty_string_when_no_style_tag(): void
    {
        $layout = new LayoutResult(
            html: '<div>No style here</div>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10]],
        );

        $this->assertEquals('', $layout->headStyle());
    }

    #[Test]
    public function margins_returns_configured_margins(): void
    {
        $layout = new LayoutResult(
            html: '<div>Test</div>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297, 'margins' => ['top' => 15, 'right' => 20, 'bottom' => 15, 'left' => 20]],
        );

        $margins = $layout->margins();

        $this->assertEquals(15, $margins['top']);
        $this->assertEquals(20, $margins['right']);
        $this->assertEquals(15, $margins['bottom']);
        $this->assertEquals(20, $margins['left']);
    }

    #[Test]
    public function margins_returns_defaults_when_missing(): void
    {
        $layout = new LayoutResult(
            html: '<div>Test</div>',
            title: 'Test',
            paperSize: 'a4',
            orientation: 'portrait',
            page: ['width' => 210, 'height' => 297],
        );

        $margins = $layout->margins();

        $this->assertEquals(10, $margins['top']);
        $this->assertEquals(10, $margins['right']);
        $this->assertEquals(10, $margins['bottom']);
        $this->assertEquals(10, $margins['left']);
    }
}