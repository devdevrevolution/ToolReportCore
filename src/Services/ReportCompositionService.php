<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Toolreport\Core\Models\ReportComposition;

class ReportCompositionService
{
    /**
     * List compositions with filtering, sorting, and pagination.
     *
     * @param  array{per_page?: int, sort?: string, order?: string, search?: string}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $sort = $filters['sort'] ?? 'created_at';
        if (!in_array($sort, ['name', 'slug', 'created_at', 'updated_at'], true)) {
            $sort = 'created_at';
        }
        $order = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $search = $filters['search'] ?? null;

        return ReportComposition::query()
            ->withCount('pages')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $order)
            ->paginate($perPage);
    }

    /**
     * Create a composition with optional pages.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $pages
     */
    public function create(array $data, array $pages = []): ReportComposition
    {
        $composition = ReportComposition::create($data);

        if (!empty($pages)) {
            $composition->pages()->createMany($pages);
        }

        $composition->load('pages.template');

        return $composition;
    }

    /**
     * Update a composition and optionally replace its pages.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $pages  null means no change to pages
     */
    public function update(ReportComposition $composition, array $data, ?array $pages): ReportComposition
    {
        DB::transaction(function () use ($composition, $data, $pages) {
            $updatableFields = ['name', 'slug', 'description', 'page', 'is_active'];

            if (!empty(array_intersect(array_keys($data), $updatableFields))) {
                $composition->update($data);
            }

            if ($pages !== null) {
                $composition->pages()->delete();
                $composition->pages()->createMany($pages);
            }
        });

        $composition->fresh();
        $composition->load('pages.template');

        return $composition;
    }

    /**
     * Delete a composition.
     */
    public function delete(ReportComposition $composition): ?bool
    {
        return $composition->delete();
    }
}
