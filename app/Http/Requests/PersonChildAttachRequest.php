<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonChildAttachRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $treeId = $this->user()->tree->id;
        $person = $this->route('person');
        $personId = $person instanceof Person ? $person->id : 0;

        return [
            'child_id' => [
                'required',
                'integer',
                Rule::notIn([$personId]),
                'different:other_parent_id',
                Rule::exists('people', 'id')->where('tree_id', $treeId),
            ],
            'other_parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$personId]),
                Rule::exists('people', 'id')->where('tree_id', $treeId),
            ],
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
            'child_id.not_in' => 'Osoba nie może być własnym rodzicem.',
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
            'child_id' => 'dziecko',
            'other_parent_id' => 'drugi rodzic',
        ];
    }
}
