<?php

namespace App\Support\Import;

use App\Enums\Gender;

/**
 * A value the repo adds to a field that is empty in the tree.
 */
final readonly class PlannedFill
{
    /**
     * @param  string  $field  first_name, last_name, gender, birth_date, death_date or marriage_date
     * @param  string|Gender|ResearchDate  $value  the repo value
     * @param  string  $label  the Polish name of the field
     * @param  string|null  $appLabel  what the tree held when the plan was made, as the apply step reads it back (a date label for a refinement); null when the field was empty
     */
    public function __construct(
        public string $field,
        public string|Gender|ResearchDate $value,
        public string $label,
        public ?string $appLabel = null,
    ) {}

    public function valueLabel(): string
    {
        return match (true) {
            $this->value instanceof Gender => $this->value->label(),
            $this->value instanceof ResearchDate => $this->value->label(),
            default => $this->value,
        };
    }
}
