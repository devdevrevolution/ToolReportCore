<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Toolreport\Core\Models\PdfTemplate;

class PdfTemplateService
{
    /**
     * List templates with optional active filter and pagination.
     *
     * @param  array{per_page?: int, active?: bool}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return PdfTemplate::query()
            ->withCount('documents')
            ->when($filters['active'] ?? false, fn ($q) => $q->active())
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a new template.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PdfTemplate
    {
        return PdfTemplate::create($data);
    }

    /**
     * Show a template with document count.
     */
    public function show(PdfTemplate $template): PdfTemplate
    {
        $template->loadCount('documents');

        return $template;
    }

    /**
     * Update a template.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PdfTemplate $template, array $data): PdfTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    /**
     * Delete a template.
     */
    public function delete(PdfTemplate $template): ?bool
    {
        return $template->delete();
    }

    /**
     * Duplicate a template with a modified name and slug.
     */
    public function duplicate(PdfTemplate $template): PdfTemplate
    {
        $duplicate = $template->replicate();
        $duplicate->name = $template->name . ' (Copy)';
        $duplicate->slug = $template->slug . '-copy-' . time();
        $duplicate->save();

        return $duplicate;
    }
}
