<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pdf_template_id' => $this->pdf_template_id,
            'name' => $this->name,
            // Mask the value for private/secret variables
            'value' => $this->visibility === 'private'
                ? ($this->value !== null && $this->value !== '' ? '***' : '')
                : $this->value,
            'visibility' => $this->visibility,
            'is_required' => $this->is_required,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
