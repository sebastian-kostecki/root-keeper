<?php

namespace App\Http\Requests;

use App\Concerns\SourceDescriptionValidationRules;
use App\Enums\SourceKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SourceStoreRequest extends FormRequest
{
    use SourceDescriptionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(SourceKind::class)],
            'file' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(20 * 1024)],
            ...$this->sourceDescriptionRules(),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'plik',
            'kind' => 'rodzaj',
            ...$this->sourceDescriptionAttributes(),
        ];
    }
}
