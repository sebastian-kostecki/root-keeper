<?php

namespace App\Http\Requests;

use App\Enums\Religion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PersonStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentYear = (int) Date::now()->year;

        return [
            'first_name' => ['required_without:last_name', 'string', 'max:255'],
            'last_name' => ['required_without:first_name', 'string', 'max:255'],
            'residence' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'is_deceased' => ['sometimes', 'boolean'],

            'birth.year' => ['nullable', 'integer', "between:1000,{$currentYear}", 'required_with:birth.month', 'required_if_accepted:birth.is_approximate'],
            'birth.month' => ['nullable', 'integer', 'between:1,12', 'required_with:birth.day'],
            'birth.day' => ['nullable', 'integer', 'between:1,31'],
            'birth.is_approximate' => ['sometimes', 'boolean'],
            'birth.place' => ['nullable', 'string', 'max:255'],

            'death.year' => ['nullable', 'integer', "between:1000,{$currentYear}", 'required_with:death.month', 'required_if_accepted:death.is_approximate', 'prohibited_unless:is_deceased,1'],
            'death.month' => ['nullable', 'integer', 'between:1,12', 'required_with:death.day', 'prohibited_unless:is_deceased,1'],
            'death.day' => ['nullable', 'integer', 'between:1,31', 'prohibited_unless:is_deceased,1'],
            'death.is_approximate' => ['sometimes', 'boolean', 'prohibited_unless:is_deceased,1'],
            'death.place' => ['nullable', 'string', 'max:255', 'prohibited_unless:is_deceased,1'],
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

    /**
     * Reject an impossible calendar date (e.g. 31 lutego) for the given date prefix.
     */
    private function validateFullDate(Validator $validator, string $prefix): void
    {
        $year = $this->input("{$prefix}.year");
        $month = $this->input("{$prefix}.month");
        $day = $this->input("{$prefix}.day");

        if ($year === null || $month === null || $day === null) {
            return;
        }

        if (! checkdate((int) $month, (int) $day, (int) $year)) {
            $validator->errors()->add("{$prefix}.day", 'Podana data jest nieprawidłowa.');
        }
    }
}
