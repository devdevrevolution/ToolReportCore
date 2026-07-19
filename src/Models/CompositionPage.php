<?php

declare(strict_types=1);

namespace Toolreport\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Toolreport\Core\Database\Factories\CompositionPageFactory;

class CompositionPage extends Model
{
    use HasFactory;

    protected $table = 'composition_pages';

    protected $fillable = [
        'report_composition_id',
        'pdf_template_id',
        'sort_order',
    ];

    protected static function newFactory(): Factory
    {
        return CompositionPageFactory::new();
    }

    public function composition(): BelongsTo
    {
        return $this->belongsTo(ReportComposition::class, 'report_composition_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PdfTemplate::class, 'pdf_template_id');
    }
}
