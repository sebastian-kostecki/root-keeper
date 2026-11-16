<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;
use App\Enums\Gender;

/**
 * A value the repo adds to a field that is empty in the tree.
 */
final readonly class PlannedFill
{
    /**
     * @param  string  $field  first_name, last_name, gender, surname_variants, birth_date, death_date, burial_date, marriage_date, birth_place, death_place, burial_place or marriage_place
     * @param  string|Gender|ResearchDate|PlannedPlace|SurnameVariants  $value  the repo value
     * @param  string  $label  the Polish name of the field
     * @param  string|null  $appLabel  what the tree held when the plan was made, as the apply step reads it back (a date label for a refinement); null when the field was empty
     */
    public function __construct(
        public string $field,
        public string|Gender|ResearchDate|PlannedPlace|SurnameVariants $value,
        public string $label,
        public ?string $appLabel = null,
    ) {}

    public function valueLabel(): string
    {
        return match (true) {
            $this->value instanceof Gender => $this->value->label(),
            $this->value instanceof ResearchDate => $this->value->label(),
            $this->value instanceof PlannedPlace => $this->value->label,
            $this->value instanceof SurnameVariants => $this->value->label() ?? PlanImport::EMPTY_LABEL,
            default => $this->value,
        };
    }
}
