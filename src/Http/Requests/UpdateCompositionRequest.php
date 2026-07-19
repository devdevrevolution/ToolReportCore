<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $compositionId = $this->route('composition');

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('report_compositions', 'slug')->ignore($compositionId),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // Page config rules — only when `page` key is present
        if ($this->has('page')) {
            $rules['page'] = ['sometimes', 'required', 'array'];
            $rules['page.width'] = ['sometimes', 'required', 'numeric', 'min:10', 'max:2000'];
            $rules['page.height'] = ['sometimes', 'required', 'numeric', 'min:10', 'max:2000'];
            $rules['page.margin'] = ['sometimes', 'required', 'array'];
            $rules['page.margin.top'] = ['sometimes', 'required', 'numeric', 'min:0'];
            $rules['page.margin.right'] = ['sometimes', 'required', 'numeric', 'min:0'];
            $rules['page.margin.bottom'] = ['sometimes', 'required', 'numeric', 'min:0'];
            $rules['page.margin.left'] = ['sometimes', 'required', 'numeric', 'min:0'];
        }

        // Pages rules — only when `pages` key is present
        if ($this->has('pages')) {
            $rules['pages'] = ['sometimes', 'required', 'array', 'min:1', 'max:50'];
            $rules['pages.*.pdf_template_id'] = ['required', 'integer', 'exists:pdf_templates,id'];
            $rules['pages.*.sort_order'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->has('pages') || ! $this->has('page')) {
                    return;
                }

                $data = $this->validated();
                $width = $data['page']['width'] ?? null;
                $height = $data['page']['height'] ?? null;

                if ($width === null || $height === null) {
                    return;
                }

                $templateIds = collect($data['pages'] ?? [])
                    ->pluck('pdf_template_id')
                    ->unique();

                $templates = DB::table('pdf_templates')
                    ->whereIn('id', $templateIds)
                    ->get(['id', 'name', 'page']);

                foreach ($data['pages'] as $index => $pageData) {
                    $template = $templates->firstWhere('id', $pageData['pdf_template_id']);

                    if ($template === null) {
                        continue;
                    }

                    $templatePage = is_string($template->page) ? json_decode($template->page, true) : $template->page;
                    $templateWidth = $templatePage['width'] ?? null;
                    $templateHeight = $templatePage['height'] ?? null;

                    $mismatches = [];

                    if ($templateWidth !== null && (float) $templateWidth !== (float) $width) {
                        $mismatches[] = "ancho ({$templateWidth}mm)";
                    }

                    if ($templateHeight !== null && (float) $templateHeight !== (float) $height) {
                        $mismatches[] = "alto ({$templateHeight}mm)";
                    }

                    if (!empty($mismatches)) {
                        $validator->errors()->add(
                            "pages.{$index}.pdf_template_id",
                            "La plantilla '{$template->name}' usa " . implode(' y ', $mismatches)
                            . ", pero la composición espera {$width}mm x {$height}mm."
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la composición es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',

            'slug.required' => 'El slug es obligatorio.',
            'slug.string' => 'El slug debe ser una cadena de texto.',
            'slug.max' => 'El slug no puede exceder los 255 caracteres.',
            'slug.unique' => 'Este slug ya está en uso.',
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones (ej: mi-composicion).',

            'description.string' => 'La descripción debe ser una cadena de texto.',
            'description.max' => 'La descripción no puede exceder los 5000 caracteres.',

            'page.required' => 'La configuración de página es obligatoria.',
            'page.array' => 'La configuración de página debe ser un arreglo.',

            'page.width.required' => 'El ancho de página es obligatorio.',
            'page.width.numeric' => 'El ancho de página debe ser un valor numérico.',
            'page.width.min' => 'El ancho de página debe ser al menos 10 mm.',
            'page.width.max' => 'El ancho de página no puede exceder los 2000 mm.',

            'page.height.required' => 'El alto de página es obligatorio.',
            'page.height.numeric' => 'El alto de página debe ser un valor numérico.',
            'page.height.min' => 'El alto de página debe ser al menos 10 mm.',
            'page.height.max' => 'El alto de página no puede exceder los 2000 mm.',

            'page.margin.required' => 'Los márgenes de página son obligatorios.',
            'page.margin.array' => 'Los márgenes deben ser un arreglo.',
            'page.margin.top.required' => 'El margen superior es obligatorio.',
            'page.margin.top.numeric' => 'El margen superior debe ser numérico.',
            'page.margin.top.min' => 'El margen superior no puede ser negativo.',
            'page.margin.right.required' => 'El margen derecho es obligatorio.',
            'page.margin.right.numeric' => 'El margen derecho debe ser numérico.',
            'page.margin.right.min' => 'El margen derecho no puede ser negativo.',
            'page.margin.bottom.required' => 'El margen inferior es obligatorio.',
            'page.margin.bottom.numeric' => 'El margen inferior debe ser numérico.',
            'page.margin.bottom.min' => 'El margen inferior no puede ser negativo.',
            'page.margin.left.required' => 'El margen izquierdo es obligatorio.',
            'page.margin.left.numeric' => 'El margen izquierdo debe ser numérico.',
            'page.margin.left.min' => 'El margen izquierdo no puede ser negativo.',

            'pages.required' => 'Debe incluir al menos una página en la composición.',
            'pages.array' => 'Las páginas deben ser un arreglo.',
            'pages.min' => 'La composición debe tener al menos 1 página.',
            'pages.max' => 'La composición no puede tener más de 50 páginas.',

            'pages.*.pdf_template_id.required' => 'El ID de la plantilla es obligatorio para cada página.',
            'pages.*.pdf_template_id.integer' => 'El ID de la plantilla debe ser un número entero.',
            'pages.*.pdf_template_id.exists' => 'La plantilla con ID :input no existe.',

            'pages.*.sort_order.required' => 'El orden de cada página es obligatorio.',
            'pages.*.sort_order.integer' => 'El orden debe ser un número entero.',
            'pages.*.sort_order.min' => 'El orden no puede ser negativo.',

            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
