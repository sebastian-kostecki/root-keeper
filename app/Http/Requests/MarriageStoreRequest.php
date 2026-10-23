<?php

namespace App\Http\Requests;

use App\Concerns\PartialDateValidationRules;
use App\Enums\MarriageForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MarriageStoreRequest extends FormRequest
{
    use PartialDateValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id)],
            'spouse_id' => ['required', 'integer', 'different:person_id', Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id)],

            ...$this->partialDateRules('marriage'),
            'marriage.form' => ['nullable', Rule::enum(MarriageForm::class)],
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
            'spouse_id.different' => 'Osoba nie może poślubić samej siebie.',
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
            'marriage.year' => 'rok ślubu',
            'marriage.month' => 'miesiąc ślubu',
            'marriage.day' => 'dzień ślubu',
            'marriage.locality_id' => 'miejsce ślubu',
            'marriage.qualifier' => 'rodzaj daty',
            'marriage.until_year' => 'rok końca zakresu',
            'marriage.form' => 'forma ślubu',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateFullDate($validator, 'marriage');
            $this->validateRange($validator, 'marriage');
        });
    }
}
