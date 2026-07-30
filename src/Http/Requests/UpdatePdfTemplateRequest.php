<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePdfTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('pdf_template') ?? $this->route('template');

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('pdf_templates', 'slug')->ignore($templateId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'engine' => ['nullable', 'string'],
            'page' => ['sometimes', 'required', 'array'],
            'page.width' => ['sometimes', 'required', 'numeric', 'min:10', 'max:1000'],
            'page.height' => ['sometimes', 'required', 'numeric', 'min:10', 'max:1000'],
            'page.orientation' => ['sometimes', 'required', 'string', 'in:portrait,landscape'],
            'page.paper_size' => ['nullable', 'string'],
            'page.margins' => ['nullable', 'array'],

            // Bands (optional — backward compatible)
            'page.bands' => ['nullable', 'array'],
            'page.bands.*.id' => ['required', 'string', 'max:50'],
            'page.bands.*.type' => ['required', 'string', 'in:title,pageHeader,columnHeader,detail,summary,pageFooter,columnFooter'],
            'page.bands.*.anchor' => ['required', 'string', 'in:top,bottom,fill'],
            'page.bands.*.label' => ['required', 'string', 'max:100'],
            'page.bands.*.height' => ['required', 'numeric', 'min:5', 'max:2000'],
            'page.bands.*.enabled' => ['nullable', 'boolean'],
            'page.bands.*.datasourceId' => ['nullable', 'string', 'max:255'],
            'page.bands.*.collectionPath' => ['nullable', 'string', 'max:1000'],
            'page.bands.*.summaryPosition' => ['nullable', 'string', 'in:afterDetail,pageBottom'],

            // children live inside bands (v3+)
            // Legacy flat page.children (previously `elements`) also accepted for backward compat
            'page.children' => ['nullable', 'array'],
            'page.elements' => ['nullable', 'array'], // legacy alias

            // Per-band children validation
            'page.bands.*.children' => ['nullable', 'array'],
            'page.bands.*.elements' => ['nullable', 'array'], // legacy alias
            'page.bands.*.children.*.id' => ['nullable', 'string', 'max:255'],
        ];

        $rules['page.bands.*.children.*.x'] = ['nullable', 'numeric', 'min:0'];
        $rules['page.bands.*.children.*.y'] = ['nullable', 'numeric', 'min:0'];
        $rules['page.bands.*.children.*.width'] = ['nullable', 'numeric', 'min:0'];
        $rules['page.bands.*.children.*.height'] = ['nullable', 'numeric', 'min:0'];
        $rules['page.bands.*.children.*.node'] = ['nullable', 'array'];

        $rules['config'] = ['nullable', 'array'];
        $rules['is_active'] = ['nullable', 'boolean'];

        return $rules;
    }
}
