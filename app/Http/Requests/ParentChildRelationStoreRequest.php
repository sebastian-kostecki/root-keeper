<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParentChildRelationStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id)],
            'child_id' => ['required', 'integer', 'different:parent_id', Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id)],
        ];
    }

    /**
     * Get custom error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'child_id.different' => 'Osoba nie może być własnym rodzicem.',
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
            'parent_id' => 'rodzic',
            'child_id' => 'dziecko',
        ];
    }
}
