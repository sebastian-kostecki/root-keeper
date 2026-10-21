<?php

namespace App\Concerns;

use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Models\Parish;
use App\Models\Tree;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ParishValidationRules
{
    /**
     * Get the validation rules for a parish or office. The seat, kind, religion and dedication (letter case and
     * surrounding spaces ignored, a missing religion equals a missing religion) must be unique in the user's
     * tree; when updating, the edited parish is ignored. The seat is required and must be a locality of the user's tree.
     *
     * @return array<string, list<mixed>>
     */
    protected function parishRules(?Parish $ignoring = null): array
    {
        return [
            'kind' => ['required', Rule::enum(ParishKind::class)],
            'locality_id' => [
                'required',
                'integer',
                Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id),
            ],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'dedication' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('kind') === ParishKind::Other->value),
                $this->uniqueParishRule($ignoring),
            ],
            'clear_name' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated request as the details the store and update actions take; a blank dedication is null.
     *
     * @return array{locality_id: int, kind: ParishKind, religion: ?Religion, dedication: ?string, clears_name: bool}
     */
    public function parishDetails(): array
    {
        $dedication = trim($this->string('dedication')->toString());

        return [
            'locality_id' => $this->integer('locality_id'),
            'kind' => $this->enum('kind', ParishKind::class) ?? ParishKind::Parish,
            'religion' => $this->enum('religion', Religion::class),
            'dedication' => $dedication === '' ? null : $dedication,
            'clears_name' => $this->boolean('clear_name'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function parishAttributes(): array
    {
        return [
            'kind' => 'rodzaj',
            'locality_id' => 'miejscowość siedziby',
            'religion' => 'wyznanie',
            'dedication' => 'wezwanie / wyróżnik',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function parishMessages(): array
    {
        return [
            'dedication.required' => __('Podaj wezwanie lub wyróżnik dla rodzaju „inny”.'),
        ];
    }

    /**
     * The uniqueness of the seat, kind, religion and dedication in the tree. Implicit, so that a missing dedication
     * (null after the empty string is converted) is checked as well.
     */
    private function uniqueParishRule(?Parish $ignoring): ValidationRule
    {
        $tree = $this->user()->tree;
        $localityId = $this->input('locality_id');
        $kind = $this->input('kind');
        $religion = $this->input('religion');

        return new class($tree, $localityId, is_string($kind) ? $kind : null, is_string($religion) ? $religion : null, $ignoring) implements ValidationRule
        {
            public bool $implicit = true;

            public function __construct(private Tree $tree, private mixed $localityId, private ?string $kind, private ?string $religion, private ?Parish $ignoring) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! is_scalar($this->localityId) || ! ctype_digit((string) $this->localityId) || $this->kind === null || ParishKind::tryFrom($this->kind) === null) {
                    return;
                }

                $dedication = is_string($value) ? trim($value) : '';

                $query = $this->tree->parishes()
                    ->where('locality_id', (int) $this->localityId)
                    ->where('kind', $this->kind)
                    ->whereRaw("coalesce(religion, '') = coalesce(?, '')", [$this->religion])
                    ->whereRaw("lower(coalesce(dedication, '')) = lower(?)", [$dedication]);

                if ($this->ignoring !== null) {
                    $query->whereKeyNot($this->ignoring->id);
                }

                $existing = $query->with('locality')->first();

                if ($existing !== null) {
                    $fail(__('Ta parafia / urząd już istnieje: „:label”.', ['label' => $existing->label()]));
                }
            }
        };
    }
}
