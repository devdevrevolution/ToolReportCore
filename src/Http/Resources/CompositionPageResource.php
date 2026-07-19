<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompositionPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $template = $this->whenLoaded('template');

        $templatePage = null;
        $templateName = null;
        $templateSlug = null;

        if ($template) {
            $templateName = $template->name;
            $templateSlug = $template->slug;
            $page = $template->page ?? [];
            $templatePage = [
                'width' => $page['width'] ?? null,
                'height' => $page['height'] ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'pdf_template_id' => $this->pdf_template_id,
            'template_name' => $templateName,
            'template_slug' => $templateSlug,
            'sort_order' => $this->sort_order,
            'page' => $templatePage,
        ];
    }
}
