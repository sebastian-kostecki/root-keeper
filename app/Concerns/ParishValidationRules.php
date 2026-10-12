<?php

namespace App\Concerns;

use App\Enums\Religion;
use App\Models\Parish;
use Closure;
use Illuminate\Validation\Rule;

trait ParishValidationRules
{
    /**
     * Get the validation rules for a parish. The pair name (letter case ignored) and religion must be
     * unique in the user's tree, a missing religion equals a missing religion; when updating, the edited
     * parish is ignored. The seat must be a locality of the user's tree.
     *
     * @return array<string, list<mixed>>
     */
    protected function parishRules(?Parish $ignoring = null): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($ignoring): void {
                    $religion = $this->input('religion');
                    $religion = is_string($religion) ? $religion : null;

                    $query = $this->user()->tree->parishes()
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->whereRaw("coalesce(religion, '') = coalesce(?, '')", [$religion]);

                    if ($ignoring !== null) {
                        $query->whereKeyNot($ignoring->id);
                    }

                    if ($query->exists()) {
                        $fail(__('Parafia o tej nazwie i wyznaniu już istnieje.'));
                    }
                },
            ],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'locality_id' => [
                'nullable',
                'integer',
                Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function parishAttributes(): array
    {
        return [
            'name' => 'nazwa',
            'religion' => 'wyznanie',
            'locality_id' => 'miejscowość siedziby',
        ];
    }
}
