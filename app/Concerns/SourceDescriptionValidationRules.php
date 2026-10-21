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
     * type and parish apply to a metrical record only; the cemetery and the buried
     * people to a gravestone only.
     *
     * @return array<string, list<mixed>>
     */
    protected function sourceDescriptionRules(): array
    {
        $currentYear = (int) Date::now()->year;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', "between:1000,{$currentYear}"],
            'archive' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'note' => ['nullable', 'string', 'max:5000'],
            'record_type' => [Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::MetricalRecord->value), 'nullable', Rule::enum(SourceRecordType::class)],
            'parish_id' => [
                Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::MetricalRecord->value),
                'nullable',
                'integer',
                Rule::exists('parishes', 'id')->where('tree_id', $this->user()->tree->id),
            ],
            'cemetery_id' => [
                Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::Gravestone->value),
                'nullable',
                'integer',
                Rule::exists('cemeteries', 'id')->where('tree_id', $this->user()->tree->id),
            ],
            'buried_person_ids' => [
                Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::Gravestone->value),
                'nullable',
                'array',
            ],
            'buried_person_ids.*' => [
                Rule::excludeIf(fn (): bool => $this->input('kind') !== SourceKind::Gravestone->value),
                'integer',
                'distinct',
                Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id),
            ],
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
            'archive' => 'archiwum',
            'url' => 'link',
            'note' => 'notatka',
            'record_type' => 'typ aktu',
            'parish_id' => 'parafia / urząd',
            'cemetery_id' => 'cmentarz',
            'buried_person_ids' => 'osoby pochowane',
            'buried_person_ids.*' => 'osoby pochowane',
        ];
    }
}
