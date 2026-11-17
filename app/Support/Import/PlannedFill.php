<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;
use App\Enums\ConfidenceLevel;
use App\Enums\Gender;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;

/**
 * A value the repo adds to a field that is empty in the tree.
 */
final readonly class PlannedFill
{
    /**
     * @param  string  $field  first_name, last_name, gender, surname_variants, birth_date, death_date, burial_date, marriage_date, birth_place, death_place, burial_place, marriage_place, birth_confidence, death_confidence, burial_confidence, marriage_confidence, birth_sources, death_sources or marriage_sources; of a source kind, record_type, name, year, parish, archive, signature, url, note or file
     * @param  string|Gender|ResearchDate|PlannedPlace|SurnameVariants|SourceLinks|SourceKind|SourceRecordType|ConfidenceLevel  $value  the repo value
     * @param  string  $label  the Polish name of the field
     * @param  string|null  $appLabel  what the tree held when the plan was made, as the apply step reads it back (a date label for a refinement); null when the field was empty
     */
    public function __construct(
        public string $field,
        public string|Gender|ResearchDate|PlannedPlace|SurnameVariants|SourceLinks|SourceKind|SourceRecordType|ConfidenceLevel $value,
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
            $this->value instanceof SourceLinks => $this->value->label() ?? PlanImport::EMPTY_LABEL,
            $this->value instanceof SourceKind, $this->value instanceof SourceRecordType => $this->value->label(),
            $this->value instanceof ConfidenceLevel => "{$this->value->value} — {$this->value->label()}",
            default => $this->value,
        };
    }
}
