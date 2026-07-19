<?php

declare(strict_types=1);

namespace Toolreport\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Toolreport\Core\Database\Factories\PdfTemplateFactory;

class PdfTemplate extends Model
{
    use HasFactory;

    protected $table = 'pdf_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'page',
        'config',
        'is_active',
        'engine',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
            'engine' => 'string',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PdfDocument::class, 'pdf_template_id');
    }

    public function templateVars(): HasMany
    {
        return $this->hasMany(TemplateVar::class);
    }

    public function compositions(): BelongsToMany
    {
        return $this->belongsToMany(
            ReportComposition::class,
            'composition_pages',
            'pdf_template_id',
            'report_composition_id'
        );
    }

    protected static function newFactory(): Factory
    {
        return PdfTemplateFactory::new();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this template uses the pdf-engine (composite designer).
     */
    public function isPdfEngine(): bool
    {
        return $this->engine === 'pdf-engine';
    }

    /**
     * Check if this template uses the default DomPDF engine.
     */
    public function isDomPdf(): bool
    {
        return ($this->engine ?? 'dompdf') === 'dompdf';
    }

    /**
     * Get the absolute Y position (from page top, in mm) of a band.
     */
    public function getBandYPos(string $bandId): float
    {
        $bands = $this->page['bands'] ?? [];
        $margins = $this->page['margins'] ?? $this->page['margin'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
        $contentTop = (float) ($margins['top'] ?? 10);
        $contentBottom = (float) (($this->page['height'] ?? 297) - ($margins['bottom'] ?? 10));

        $foundBand = null;
        foreach ($bands as $b) {
            if ($b['id'] === $bandId) {
                $foundBand = $b;
                break;
            }
        }

        if (!$foundBand) {
            return $contentTop;
        }

        $anchor = $foundBand['anchor'] ?? 'top';

        if ($anchor === 'top') {
            $y = $contentTop;
            foreach ($bands as $b) {
                if (($b['anchor'] ?? 'top') !== 'top') {
                    continue;
                }
                if ($b['id'] === $bandId) {
                    return $y;
                }
                $y += (float) ($b['height'] ?? 0);
            }
        }

        if ($anchor === 'fill') {
            $topHeight = 0;
            foreach ($bands as $b) {
                if (($b['anchor'] ?? 'top') === 'top') {
                    $topHeight += (float) ($b['height'] ?? 0);
                }
            }
            return $contentTop + $topHeight;
        }

        if ($anchor === 'bottom') {
            $bottomBands = array_values(array_filter($bands, fn ($b) => ($b['anchor'] ?? '') === 'bottom'));
            $fromBottom = $contentBottom;
            for ($i = count($bottomBands) - 1; $i >= 0; $i--) {
                $b = $bottomBands[$i];
                $fromBottom -= (float) ($b['height'] ?? 0);
                if ($b['id'] === $bandId) {
                    return $fromBottom;
                }
            }
        }

        return $contentTop;
    }

    /**
     * Get the full layout config combining page and children.
     * This is what the LayoutEngine expects (coordinates relative to content area).
     *
     * Handles three storage formats:
     * - v3 (current): children relative to Band (Y) and Content (X)
     * - v2: page.children (flat list, absolute Y/X) — previously `elements`
     * - v1 (legacy): config.children (flat list, absolute Y/X) — previously `elements`
     */
    public function getFullConfig(): array
    {
        $margins = $this->page['margins'] ?? $this->page['margin'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
        $marginTop = (float) ($margins['top'] ?? 10);
        $marginLeft = (float) ($margins['left'] ?? 10);

        // v3 with bands: preserve band structure, skip flattening
        $bands = $this->page['bands'] ?? [];
        if (!empty($bands)) {
            // Band-based templates: keep bands intact, no flattened children
            // The LayoutEngine will iterate bands with a currentY cursor
            return [
                'page' => $this->page,
                'children' => [],
            ];
        }

        // v2 / v1 fallback: convert absolute to relative-to-content
        $rawChildren = $this->page['children'] ?? $this->page['elements'] ?? $this->config['children'] ?? $this->config['elements'] ?? $this->config;
        if (!is_array($rawChildren)) {
            $rawChildren = [];
        }

        $children = [];
        foreach ($rawChildren as $el) {
            $el['x'] = (float) ($el['x'] ?? 0) - $marginLeft;
            $el['y'] = (float) ($el['y'] ?? 0) - $marginTop;
            $children[] = $el;
        }

        return [
            'page' => $this->page,
            'children' => $children,
        ];
    }
}
