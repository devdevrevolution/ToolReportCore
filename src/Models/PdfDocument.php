<?php

declare(strict_types=1);

namespace Toolreport\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Toolreport\Core\Database\Factories\PdfDocumentFactory;

class PdfDocument extends Model
{
    use HasFactory;

    protected $table = 'pdf_documents';

    protected $fillable = [
        'pdf_template_id',
        'report_composition_id',
        'title',
        'data',
        'file_path',
        'file_size',
        'status',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PdfDocumentFactory::new();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PdfTemplate::class, 'pdf_template_id');
    }

    public function composition(): BelongsTo
    {
        return $this->belongsTo(ReportComposition::class, 'report_composition_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'done' && $this->file_path !== null;
    }
}
