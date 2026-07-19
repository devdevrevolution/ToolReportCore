<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdfDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_composition_id' => $this->report_composition_id,
            'pdf_template_id' => $this->pdf_template_id,
            'title' => $this->title,
            'status' => $this->status,
            'file_size' => $this->file_size,
            'error_message' => $this->error_message,
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
