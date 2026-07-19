<?php

declare(strict_types=1);

namespace Toolreport\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Toolreport\Core\Database\Factories\ReportCompositionFactory;

class ReportComposition extends Model
{
    use HasFactory;

    protected $table = 'report_compositions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'page',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ReportCompositionFactory::new();
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CompositionPage::class, 'report_composition_id')
            ->orderBy('sort_order');
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            PdfTemplate::class,
            'composition_pages',
            'report_composition_id',
            'pdf_template_id'
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PdfDocument::class, 'report_composition_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full composition config merging page settings with ordered template configs.
     * Used by the generation flow.
     */
    public function getFullConfig(): array
    {
        $config = [
            'page' => $this->page,
            'templates' => [],
        ];

        foreach ($this->pages as $page) {
            $template = $page->template;
            if ($template) {
                $config['templates'][] = [
                    'sort_order' => $page->sort_order,
                    'template' => $template,
                    'config' => $template->getFullConfig(),
                ];
            }
        }

        return $config;
    }
}
