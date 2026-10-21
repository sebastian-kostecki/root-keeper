<?php

namespace App\Concerns;

use App\Enums\LocalityKind;
use App\Models\Locality;
use App\Support\Coordinates;
use Closure;
use Illuminate\Validation\Rule;

trait LocalityValidationRules
{
    /**
     * Get the validation rules for a locality. The name must be unique in the user's tree
     * regardless of letter case; when updating, the edited locality is ignored.
     *
     * @return array<string, list<mixed>>
     */
    protected function localityRules(?Locality $ignoring = null): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($ignoring): void {
                    $query = $this->user()->tree->localities()->whereRaw('lower(name) = lower(?)', [$value]);

                    if ($ignoring !== null) {
                        $query->whereKeyNot($ignoring->id);
                    }

                    if ($query->exists()) {
                        $fail(__('Miejscowość o tej nazwie jest już w spisie.'));
                    }
                },
            ],
            'kind' => ['nullable', Rule::enum(LocalityKind::class)],
            'coordinates' => [
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || Coordinates::parse($value) === null) {
                        $fail(__('Wpisz współrzędne jako „50.0614, 19.9366” (szerokość, długość).'));
                    }
                },
            ],
            'historical_affiliation' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_defunct' => ['sometimes', 'boolean'],
            'defunct_note' => ['nullable', 'string', 'max:255', 'prohibited_unless:is_defunct,1,true'],
        ];
    }

    /**
     * The validated request as the details the store and update actions take; an empty field is null
     * and the pasted coordinates become a pair of numbers.
     *
     * @return array{name: string, description: ?string, kind: ?LocalityKind, latitude: ?float, longitude: ?float, historical_affiliation: ?string, is_defunct: bool, defunct_note: ?string}
     */
    public function localityDetails(): array
    {
        $coordinates = $this->filled('coordinates') ? Coordinates::parse($this->string('coordinates')->toString()) : null;

        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'kind' => $this->enum('kind', LocalityKind::class),
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'historical_affiliation' => $this->filled('historical_affiliation') ? $this->string('historical_affiliation')->toString() : null,
            'is_defunct' => $this->boolean('is_defunct'),
            'defunct_note' => $this->filled('defunct_note') ? $this->string('defunct_note')->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function localityAttributes(): array
    {
        return [
            'name' => 'nazwa',
            'kind' => 'rodzaj',
            'coordinates' => 'współrzędne',
            'historical_affiliation' => 'przynależność historyczna',
            'description' => 'opis',
            'is_defunct' => 'nie istnieje dziś',
            'defunct_note' => 'opis zaniku',
        ];
    }
}
