<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PersonChildStoreRequest extends PersonStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('person'));
    }

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
