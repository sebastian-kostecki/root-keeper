<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PersonChildStoreRequest extends PersonStoreRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $person = $this->route('person');
        $personId = $person instanceof Person ? $person->id : 0;

        return [
            ...parent::rules(),
            'other_parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$personId]),
                Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id),
            ],
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
            ...parent::attributes(),
            'other_parent_id' => 'drugi rodzic',
        ];
    }
}
