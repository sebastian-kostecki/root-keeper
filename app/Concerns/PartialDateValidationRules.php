<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait PartialDateValidationRules
{
    /**
     * Get the validation rules for a partial date (year/month/day, an
     * approximate flag and a gazetteer locality) nested under the given prefix, e.g.
     * "birth" or "marriage".
     *
     * @return array<string, list<mixed>>
     */
    protected function partialDateRules(string $prefix): array
    {
        $currentYear = (int) Date::now()->year;

        return [
            "{$prefix}.year" => ['nullable', 'integer', "between:1000,{$currentYear}", "required_with:{$prefix}.month", "required_if_accepted:{$prefix}.is_approximate"],
            "{$prefix}.month" => ['nullable', 'integer', 'between:1,12', "required_with:{$prefix}.day"],
            "{$prefix}.day" => ['nullable', 'integer', 'between:1,31'],
            "{$prefix}.is_approximate" => ['sometimes', 'boolean'],
            "{$prefix}.locality_id" => ['nullable', 'integer', Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id)],
        ];
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
