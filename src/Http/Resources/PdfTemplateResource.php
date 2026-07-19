<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdfTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $page = $this->page ?? [];

        // v3: extract children from bands for backward-compatible consumption
        if (!isset($page['children']) || empty($page['children'])) {
            $bandChildren = [];
            $bands = $page['bands'] ?? [];
            $margins = $page['margins'] ?? $page['margin'] ?? ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10];
            $marginTop = (float) ($margins['top'] ?? 10);

            foreach ($bands as $band) {
                // We use the Model's logic to get the band offset
                $bandTop = $this->getBandYPos($band['id']);
                $bandOffset = $bandTop - $marginTop;

                foreach ($band['children'] ?? $band['elements'] ?? [] as $el) {
                    // Convert relative Y to relative-to-content Y for the compat layer
                    $el['y'] = (float) ($el['y'] ?? 0) + $bandOffset;
                    // X is already relative to content area
                    $bandChildren[] = $el;
                }
            }
            if (!empty($bandChildren)) {
                $page['children'] = $bandChildren;
            }
        }

        // Fall back to legacy config.children (previously `elements`)
        if (!isset($page['children'])) {
            $page['children'] = $this->config['children'] ?? $this->config['elements'] ?? [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'engine' => $this->engine ?? 'dompdf',
            'page' => $page,
            'config' => $this->when(
                $request->route()?->getActionMethod() !== 'index',
                [
                    'datasources' => $this->config['datasources'] ?? [],
                    'discoveredFields' => $this->config['discoveredFields'] ?? [],
                ],
            ),
            'is_active' => $this->is_active,
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}