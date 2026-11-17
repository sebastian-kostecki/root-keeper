<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;
use App\Models\Person;

/**
 * The surname variants of a person in the one form the import compares, remembers and writes: trimmed, spaces
 * collapsed, without empty entries and without repeats that differ only in case (the first one stays), in repo order.
 * Case is folded by simple case mapping, like PostgreSQL `lower()` behind the unique index on the variants.
 */
final readonly class SurnameVariants
{
    /**
     * @param  list<string>  $names
     */
    private function __construct(public array $names) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromResearch(ResearchPerson $person): self
    {
        return self::normalize($person->surnameVariants);
    }

    public static function fromPerson(Person $person): self
    {
        return self::normalize($person->surnameVariants->pluck('name')->all());
    }

    /**
     * The form compared by the three-value rule and kept in the memory of the last import: the names joined by a
     * new line, `null` for an empty list.
     */
    public function text(): ?string
    {
        return $this->names === [] ? null : implode("\n", $this->names);
    }

    /**
     * The form shown to the author and read back by the guard: the names joined by a comma, `null` for an empty list.
     */
    public function label(): ?string
    {
        return $this->names === [] ? null : implode(', ', $this->names);
    }

    /**
     * @param  array<array-key, string|null>  $names
     */
    private static function normalize(array $names): self
    {
        $seen = [];
        $kept = [];

        foreach ($names as $name) {
            $clean = PlanImport::cleanText($name);

            if ($clean === null) {
                continue;
            }

            $key = mb_convert_case($clean, MB_CASE_LOWER_SIMPLE);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $kept[] = $clean;
        }

        return new self($kept);
    }
}
