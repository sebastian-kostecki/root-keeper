<?php

namespace App\Concerns;

use App\Models\Locality;
use Closure;

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
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function localityAttributes(): array
    {
        return [
            'name' => 'nazwa',
            'description' => 'opis',
        ];
    }
}
