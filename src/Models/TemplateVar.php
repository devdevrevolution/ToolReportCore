<?php

declare(strict_types=1);

namespace Toolreport\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVar extends Model
{
    use HasFactory;

    protected $table = 'template_vars';

    protected $fillable = [
        'pdf_template_id',
        'name',
        'value',
        'visibility',
        'is_required',
        'description',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PdfTemplate::class, 'pdf_template_id');
    }

    /**
     * Scope: public variables (client can send/override).
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope: private variables (server-only, secrets).
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('visibility', 'private');
    }

    /**
     * Scope: required variables (only applies to public).
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }
}
