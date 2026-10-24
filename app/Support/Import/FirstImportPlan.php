<?php

namespace App\Support\Import;

final readonly class FirstImportPlan
{
    /**
     * @param  list<PlannedPerson>  $persons
     * @param  list<PlannedRelation>  $relations
     * @param  list<PlannedMarriage>  $marriages
     * @param  list<ImportIssue>  $skippedRelations
     * @param  array<string, int>  $datesByQualifier  keyed by DateQualifier value, only qualifiers that occur
     */
    public function __construct(
        public array $persons,
        public array $relations,
        public array $marriages,
        public array $skippedRelations,
        public int $births,
        public int $deaths,
        public int $weddings,
        public array $datesByQualifier,
        public int $undatedEvents,
        public NoteValueCounts $noteValues,
        public ?ResearchPerson $probandHint,
        public ?string $blockedReason,
    ) {}
}
