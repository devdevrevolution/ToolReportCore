<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateVarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeParam = $this->route('pdf_template') ?? $this->route('template');
        $templateId = is_object($routeParam) ? $routeParam->id : $routeParam;

        $varParam = $this->route('template_var');
        $templateVarId = is_object($varParam) ? $varParam->id : $varParam;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                Rule::unique('template_vars', 'name')
                    ->where('pdf_template_id', $templateId)
                    ->ignore($templateVarId),
            ],
            'value' => ['sometimes', 'required', 'string'],
            'visibility' => ['sometimes', 'required', 'string', 'in:public,private'],
            'is_required' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The variable name must start with a letter or underscore and contain only letters, numbers, and underscores.',
            'name.unique' => 'A variable with this name already exists for this template.',
            'visibility.in' => 'The visibility must be either public or private.',
        ];
    }
}
