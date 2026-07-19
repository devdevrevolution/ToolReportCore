<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'page' => $this->page,
            'is_active' => $this->is_active,
            'page_count' => $this->pages_count ?? $this->pages->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->relationLoaded('pages')) {
            $data['pages'] = CompositionPageResource::collection($this->pages);
        }

        return $data;
    }
}
