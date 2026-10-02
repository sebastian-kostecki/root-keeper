<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PersonSpouseAttachRequest extends FormRequest
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
        $treeId = $this->user()->tree->id;
        $person = $this->route('person');
        $personId = $person instanceof Person ? $person->id : 0;

        return [
            'spouse_id' => [
                'required',
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
            'spouse_id.not_in' => 'Osoba nie może poślubić samej siebie.',
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
            'spouse_id' => 'małżonek',
        ];
    }
}
