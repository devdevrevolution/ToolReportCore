<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePdfTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pdf_templates,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'engine' => ['nullable', 'string', 'in:dompdf,pdf-engine'],
            'page' => ['required', 'array'],
            'page.width' => ['required', 'numeric', 'min:10', 'max:1000'],
            'page.height' => ['required', 'numeric', 'min:10', 'max:1000'],
            'page.orientation' => ['required', 'string', 'in:portrait,landscape'],
            'page.paper_size' => ['nullable', 'string'],
            'page.margins' => ['nullable', 'array'],
            'page.margins.top' => ['nullable', 'numeric', 'min:0'],
            'page.margins.right' => ['nullable', 'numeric', 'min:0'],
            'page.margins.bottom' => ['nullable', 'numeric', 'min:0'],
            'page.margins.left' => ['nullable', 'numeric', 'min:0'],

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

        // Engine-specific child field validation
        if ($this->input('engine') === 'pdf-engine') {
            $rules['page.bands.*.children.*.x'] = ['nullable', 'numeric', 'min:0'];
            $rules['page.bands.*.children.*.y'] = ['nullable', 'numeric', 'min:0'];
            $rules['page.bands.*.children.*.width'] = ['nullable', 'numeric', 'min:0'];
            $rules['page.bands.*.children.*.height'] = ['nullable', 'numeric', 'min:0'];
            $rules['page.bands.*.children.*.node'] = ['nullable', 'array'];
        } else {
            $rules['page.bands.*.children.*.type'] = ['required', 'string', 'in:text,image,table,line,rectangle,barcode,page_number,container'];
            $rules['page.bands.*.children.*.x'] = ['required', 'numeric', 'min:0'];
            $rules['page.bands.*.children.*.y'] = ['required', 'numeric', 'min:0'];
            $rules['page.bands.*.children.*.width'] = ['required', 'numeric', 'min:1'];
            $rules['page.bands.*.children.*.height'] = ['required', 'numeric', 'min:1'];
            $rules['page.bands.*.children.*.content'] = ['nullable', 'array'];
            $rules['page.bands.*.children.*.styles'] = ['nullable', 'array'];
            $rules['page.bands.*.children.*.locked'] = ['nullable', 'boolean'];
            $rules['page.bands.*.children.*.visible'] = ['nullable', 'boolean'];
            $rules['page.bands.*.children.*.rotation'] = ['nullable', 'numeric', 'min:0', 'max:360'];
        }

        $rules['config'] = ['nullable', 'array'];
        $rules['is_active'] = ['nullable', 'boolean'];

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'page.bands.*.type.in' => 'Invalid band type. Supported: title, pageHeader, columnHeader, detail, summary, pageFooter, columnFooter.',
            'page.bands.*.anchor.in' => 'Invalid band anchor. Supported: top, bottom, fill.',
        ];
        // The type.in message is only relevant for dompdf
        if ($this->input('engine') !== 'pdf-engine') {
            $messages['page.bands.*.children.*.type.in'] = 'Invalid element type. Supported: text, image, table, line, rectangle, barcode, page_number, container.';
        }
        return $messages;
    }
}
