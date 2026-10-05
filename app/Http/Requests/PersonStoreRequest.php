<?php

namespace App\Http\Requests;

use App\Concerns\PartialDateValidationRules;
use App\Enums\Gender;
use App\Enums\Religion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PersonStoreRequest extends FormRequest
{
    use PartialDateValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $deathRules = array_map(
            fn (array $rule): array => [...$rule, 'prohibited_unless:is_deceased,1,true'],
            $this->partialDateRules('death'),
        );

        return [
            'first_name' => ['nullable', 'required_without:last_name', 'string', 'max:255'],
            'last_name' => ['nullable', 'required_without:first_name', 'string', 'max:255'],
            'maiden_name' => ['nullable', 'string', 'max:255', Rule::prohibitedIf(fn (): bool => blank($this->input('last_name')))],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'residence' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'is_deceased' => ['sometimes', 'boolean'],

            ...$this->partialDateRules('birth'),
            ...$deathRules,
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maiden_name.prohibited' => 'Gdy znasz tylko jedno nazwisko, wpisz je w pole „Nazwisko”.',
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
            'first_name' => 'imię',
            'last_name' => 'nazwisko',
            'maiden_name' => 'nazwisko rodowe',
            'gender' => 'płeć',
            'residence' => 'miejsce zamieszkania',
            'religion' => 'wyznanie',
            'is_deceased' => 'zmarły/a',
            'birth.year' => 'rok urodzenia',
            'birth.month' => 'miesiąc urodzenia',
            'birth.day' => 'dzień urodzenia',
            'birth.is_approximate' => 'przybliżona data urodzenia',
            'birth.place' => 'miejsce urodzenia',
            'death.year' => 'rok zgonu',
            'death.month' => 'miesiąc zgonu',
            'death.day' => 'dzień zgonu',
            'death.is_approximate' => 'przybliżona data zgonu',
            'death.place' => 'miejsce zgonu',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateFullDate($validator, 'birth');
            $this->validateFullDate($validator, 'death');
        });
    }
}
