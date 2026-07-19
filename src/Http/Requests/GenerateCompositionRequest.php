<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título del documento es obligatorio.',
            'title.string' => 'El título debe ser una cadena de texto.',
            'title.max' => 'El título no puede exceder los 255 caracteres.',
            'data.required' => 'Los datos son obligatorios.',
            'data.array' => 'Los datos deben ser un arreglo.',
        ];
    }
}
