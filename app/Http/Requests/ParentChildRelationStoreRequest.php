<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
            'role' => ['nullable', Rule::in(['father', 'mother', 'parent'])],
        ];
    }

    /**
     * Check a link made from a tree slot against the sex the slot expects.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $expected = match ($this->input('role')) {
                'father' => Gender::Male,
                'mother' => Gender::Female,
                default => null,
            };

            if ($expected === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $parent = $this->user()->tree->people()->whereKey($this->integer('parent_id'))->first();

            if ($parent?->gender !== $expected) {
                $validator->errors()->add('relation', 'Wybrana osoba nie pasuje do tego miejsca na drzewie.');
            }
        });
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
