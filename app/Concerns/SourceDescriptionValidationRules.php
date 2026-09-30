<?php

namespace App\Concerns;

use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

trait SourceDescriptionValidationRules
{
    /**
     * Get the validation rules for the optional source description. The record
     * type and entry number apply to a metrical record only.
     *
     * @return array<string, list<mixed>>
     */
    protected function sourceDescriptionRules(): array
    {
        $currentYear = (int) Date::now()->year;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', "between:1000,{$currentYear}"],
            'provenance' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'note' => ['nullable', 'string', 'max:5000'],
            'record_type' => [Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::MetricalRecord->value), 'nullable', Rule::enum(SourceRecordType::class)],
            'entry_number' => [Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::MetricalRecord->value), 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sourceDescriptionAttributes(): array
    {
        return [
            'name' => 'nazwa',
            'year' => 'rok',
            'provenance' => 'pochodzenie',
            'url' => 'link',
            'note' => 'notatka',
            'record_type' => 'typ aktu',
            'entry_number' => 'numer wpisu',
        ];
    }
}
