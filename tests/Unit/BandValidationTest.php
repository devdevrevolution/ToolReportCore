<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Exceptions\InvalidLayoutException;
use Toolreport\Core\Layout\LayoutEngine;
use Toolreport\Core\Layout\Renderers\TextElementRenderer;
use Toolreport\Core\Tests\TestCase;

class BandValidationTest extends TestCase
{
    private LayoutEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new LayoutEngine();
        $this->engine->registerRenderers([
            new TextElementRenderer(),
        ]);
    }

    private function baseConfig(): array
    {
        return [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
        ];
    }

    #[Test]
    public function it_throws_overflow_exception_when_band_heights_exceed_printable_area(): void
    {
        $config = $this->baseConfig();
        // Printable area = 297 - 10 - 10 = 277mm
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 200, 'elements' => []],
            ['type' => 'detail', 'height' => 100, 'elements' => []],
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('Band heights exceed printable area');

        $this->engine->render($config);
    }

    #[Test]
    public function overflow_exception_message_contains_totals(): void
    {
        $config = $this->baseConfig();
        // 300mm total, 277mm printable, 23mm overflow
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 300, 'elements' => []],
        ];

        try {
            $this->engine->render($config);
            $this->fail('Expected InvalidLayoutException was not thrown');
        } catch (InvalidLayoutException $e) {
            $this->assertStringContainsString('300mm', $e->getMessage());
            $this->assertStringContainsString('277mm', $e->getMessage());
            $this->assertStringContainsString('23mm', $e->getMessage());
        }
    }

    #[Test]
    public function it_passes_validation_when_bands_fit_within_printable_area(): void
    {
        $config = $this->baseConfig();
        // Printable area = 277mm, bands total = 60mm
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 30, 'elements' => [
                ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Header'], 'styles' => []],
            ]],
            ['type' => 'detail', 'height' => 10, 'elements' => []],
            ['type' => 'footer', 'height' => 20, 'elements' => [
                ['type' => 'text', 'x' => 10, 'y' => 0, 'width' => 190, 'height' => 10, 'content' => ['text' => 'Footer'], 'styles' => []],
            ]],
        ];

        // Should not throw — this is a valid layout
        $result = $this->engine->render($config);
        $this->assertEquals(2, $result->elementCount);
    }

    #[Test]
    public function it_passes_validation_when_bands_exactly_fill_printable_area(): void
    {
        $config = $this->baseConfig();
        // Printable area = 277mm, bands total = 277mm
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 277, 'elements' => []],
        ];

        // Should not throw — exact fit
        $result = $this->engine->render($config);
        $this->assertEquals(0, $result->elementCount);
    }

    #[Test]
    public function it_throws_for_negative_band_height(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            ['type' => 'detail', 'height' => -5, 'elements' => []],
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('Invalid band configuration');

        $this->engine->render($config);
    }

    #[Test]
    public function it_throws_for_zero_band_height(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 0, 'elements' => []],
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('Invalid band configuration');

        $this->engine->render($config);
    }

    #[Test]
    public function it_throws_for_missing_band_height(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            ['type' => 'header', 'elements' => []],
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('Invalid band configuration');

        $this->engine->render($config);
    }

    #[Test]
    public function it_throws_for_non_numeric_band_height(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 'abc', 'elements' => []],
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('Invalid band configuration');

        $this->engine->render($config);
    }

    #[Test]
    public function it_includes_band_index_in_invalid_height_message(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 30, 'elements' => []],
            ['type' => 'detail', 'height' => -5, 'elements' => []],
        ];

        try {
            $this->engine->render($config);
            $this->fail('Expected InvalidLayoutException was not thrown');
        } catch (InvalidLayoutException $e) {
            $this->assertStringContainsString('index 1', $e->getMessage());
        }
    }

    #[Test]
    public function it_skips_validation_for_v1_v2_templates_without_bands(): void
    {
        // v1/v2 template with flat elements — no bands
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'elements' => [
                ['type' => 'text', 'x' => 10, 'y' => 10, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Hello'], 'styles' => []],
            ],
        ];

        // Should not throw — no bands means no band validation
        $result = $this->engine->render($config);
        $this->assertEquals(1, $result->elementCount);
    }

    #[Test]
    public function it_skips_validation_for_empty_bands_array(): void
    {
        $config = $this->baseConfig();
        $config['page']['bands'] = [];
        $config['elements'] = [
            ['type' => 'text', 'x' => 10, 'y' => 10, 'width' => 190, 'height' => 15, 'content' => ['text' => 'Hello'], 'styles' => []],
        ];

        // Should not throw — empty bands array falls back to elements
        $result = $this->engine->render($config);
        $this->assertEquals(1, $result->elementCount);
    }

    #[Test]
    public function it_calculates_printable_area_with_asymmetric_margins(): void
    {
        $config = [
            'page' => [
                'width' => 210,
                'height' => 297,
                'orientation' => 'portrait',
                'margins' => ['top' => 25, 'right' => 15, 'bottom' => 20, 'left' => 15],
            ],
        ];

        // Printable area = 297 - 25 - 20 = 252mm
        // Bands total = 260mm → should throw overflow
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 260, 'elements' => []],
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('Band heights exceed printable area');

        $this->engine->render($config);
    }

    #[Test]
    public function it_validates_all_bands_in_sequence(): void
    {
        $config = $this->baseConfig();
        // Multiple valid bands followed by an invalid one
        $config['page']['bands'] = [
            ['type' => 'header', 'height' => 30, 'elements' => []],
            ['type' => 'detail', 'height' => 10, 'elements' => []],
            ['type' => 'footer', 'height' => 0, 'elements' => []],  // invalid: zero
        ];

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('index 2');

        $this->engine->render($config);
    }
}