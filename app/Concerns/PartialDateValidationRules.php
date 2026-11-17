<?php

namespace App\Concerns;

use App\Enums\DateQualifier;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait PartialDateValidationRules
{
    /**
     * Get the validation rules for a partial date (year/month/day, a date
     * qualifier, the end year of a range and a gazetteer locality) nested under the given prefix, e.g.
     * "birth" or "marriage".
     *
     * @return array<string, list<mixed>>
     */
    protected function partialDateRules(string $prefix): array
    {
        $currentYear = (int) Date::now()->year;

        return [
            "{$prefix}.year" => ['nullable', 'integer', "between:1000,{$currentYear}", "required_with:{$prefix}.month", Rule::requiredIf(fn (): bool => $this->hasQualifiedDate($prefix))],
            "{$prefix}.month" => ['nullable', 'integer', 'between:1,12', "required_with:{$prefix}.day"],
            "{$prefix}.day" => ['nullable', 'integer', 'between:1,31'],
            "{$prefix}.qualifier" => ['nullable', Rule::enum(DateQualifier::class)],
            "{$prefix}.until_year" => ['nullable', 'integer', "between:1000,{$currentYear}", "required_if:{$prefix}.qualifier,range", "prohibited_unless:{$prefix}.qualifier,range"],
            "{$prefix}.locality_id" => ['nullable', 'integer', Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id)],
        ];
    }

    /**
     * Whether the date carries a qualifier other than exact, which only makes sense with a year.
     */
    private function hasQualifiedDate(string $prefix): bool
    {
        $qualifier = $this->input("{$prefix}.qualifier");

        return filled($qualifier) && $qualifier !== DateQualifier::Exact->value;
    }

    /**
     * Reject a range that does not run forward or carries a month or day.
     */
    protected function validateRange(Validator $validator, string $prefix): void
    {
        if ($this->input("{$prefix}.qualifier") !== DateQualifier::Range->value) {
            return;
        }

        $year = $this->input("{$prefix}.year");
        $untilYear = $this->input("{$prefix}.until_year");

        if (is_numeric($year) && is_numeric($untilYear) && ! $validator->errors()->hasAny(["{$prefix}.year", "{$prefix}.until_year"]) && (int) $untilYear <= (int) $year) {
            $validator->errors()->add("{$prefix}.until_year", __('Rok końca zakresu musi być późniejszy niż rok początku.'));
        }

        if (filled($this->input("{$prefix}.month")) || filled($this->input("{$prefix}.day"))) {
            $validator->errors()->add("{$prefix}.month", __('Zakres obejmuje tylko lata, bez miesiąca i dnia.'));
        }
    }

    /**
     * Reject an impossible calendar date (e.g. 31 lutego) for the given date prefix.
     */
    protected function validateFullDate(Validator $validator, string $prefix): void
    {
        $year = $this->input("{$prefix}.year");
        $month = $this->input("{$prefix}.month");
        $day = $this->input("{$prefix}.day");

        if ($year === null || $month === null || $day === null) {
            return;
        }

        if ($validator->errors()->hasAny(["{$prefix}.year", "{$prefix}.month", "{$prefix}.day"])) {
            return;
        }

        if (! checkdate((int) $month, (int) $day, (int) $year)) {
            $validator->errors()->add("{$prefix}.day", __('Podana data jest nieprawidłowa.'));
        }
    }
}
