<?php

namespace App\Concerns;

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use App\Models\Cemetery;
use App\Models\Tree;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CemeteryValidationRules
{
    /**
     * Get the validation rules for a cemetery. The name (letter case ignored, a missing name equals a missing
     * name) must be unique in its locality; when updating, the edited cemetery is ignored. The locality
     * must belong to the user's tree.
     *
     * @return array<string, list<mixed>>
     */
    protected function cemeteryRules(?Cemetery $ignoring = null): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                $this->uniqueNameInLocalityRule($ignoring),
            ],
            'locality_id' => [
                'required',
                'integer',
                Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id),
            ],
            'manager' => ['nullable', Rule::enum(CemeteryManager::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The validated request as the details the store and update actions take; an empty field is null,
     * a blank name too.
     *
     * @return array{name: ?string, locality_id: int, manager: ?CemeteryManager, religion: ?Religion, description: ?string}
     */
    public function cemeteryDetails(): array
    {
        $name = trim($this->string('name')->toString());

        return [
            'name' => $name === '' ? null : $name,
            'locality_id' => $this->integer('locality_id'),
            'manager' => $this->enum('manager', CemeteryManager::class),
            'religion' => $this->enum('religion', Religion::class),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function cemeteryAttributes(): array
    {
        return [
            'name' => 'nazwa',
            'locality_id' => 'miejscowość',
            'manager' => 'zarządca',
            'religion' => 'wyznanie',
            'description' => 'opis',
        ];
    }

    /**
     * The uniqueness of the name in the chosen locality. Implicit, so that a missing name (null after the empty
     * string is converted) is checked as well.
     */
    private function uniqueNameInLocalityRule(?Cemetery $ignoring): ValidationRule
    {
        $tree = $this->user()->tree;
        $localityId = $this->input('locality_id');

        return new class($tree, $localityId, $ignoring) implements ValidationRule
        {
            public bool $implicit = true;

            public function __construct(private Tree $tree, private mixed $localityId, private ?Cemetery $ignoring) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! is_scalar($this->localityId) || ! ctype_digit((string) $this->localityId)) {
                    return;
                }

                $name = is_string($value) ? trim($value) : '';

                $query = $this->tree->cemeteries()
                    ->where('locality_id', (int) $this->localityId)
                    ->whereRaw("lower(coalesce(name, '')) = lower(?)", [$name]);

                if ($this->ignoring !== null) {
                    $query->whereKeyNot($this->ignoring->id);
                }

                if ($query->exists()) {
                    $fail($name === ''
                        ? __('W tej miejscowości jest już cmentarz bez nazwy — nadaj nazwę.')
                        : __('W tej miejscowości jest już cmentarz o tej nazwie.'));
                }
            }
        };
    }
}
