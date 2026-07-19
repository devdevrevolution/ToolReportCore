<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateVarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeParam = $this->route('pdf_template') ?? $this->route('template');
        $templateId = is_object($routeParam) ? $routeParam->id : $routeParam;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                "unique:template_vars,name,NULL,id,pdf_template_id,{$templateId}",
            ],
            'value' => ['required', 'string'],
            'visibility' => ['required', 'string', 'in:public,private'],
            'is_required' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The variable name is required.',
            'name.regex' => 'The variable name must start with a letter or underscore and contain only letters, numbers, and underscores.',
            'name.unique' => 'A variable with this name already exists for this template.',
            'value.required' => 'The variable value is required.',
            'visibility.required' => 'The visibility must be public or private.',
            'visibility.in' => 'The visibility must be either public or private.',
        ];
    }
}
