<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatasourceTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'datasource' => ['required', 'array'],
            'datasource.id' => ['required', 'string', 'uuid'],
            'datasource.url' => ['required', 'string', 'url', 'max:2048'],
            'datasource.method' => ['required', 'string', 'in:GET,POST'],
            'datasource.headers' => ['nullable', 'array'],
            'datasource.headers.*' => ['string'],
            'datasource.auth' => ['nullable', 'array'],
            'datasource.auth.type' => ['required_with:datasource.auth', 'string', 'in:none,bearer'],
            'datasource.auth.token' => ['required_if:datasource.auth.type,bearer', 'string'],
            'datasource.timeout' => ['required', 'integer', 'min:1000', 'max:60000'],
        ];
    }

    public function messages(): array
    {
        return [
            'datasource.required' => 'The datasource configuration is required.',
            'datasource.url.required' => 'The datasource URL is required.',
            'datasource.url.url' => 'The datasource URL must be a valid URL.',
            'datasource.method.in' => 'The HTTP method must be GET or POST.',
            'datasource.timeout.min' => 'The timeout must be at least 1000ms.',
            'datasource.timeout.max' => 'The timeout may not exceed 60000ms.',
            'datasource.auth.token.required_if' => 'A token is required for Bearer authentication.',
        ];
    }
}
