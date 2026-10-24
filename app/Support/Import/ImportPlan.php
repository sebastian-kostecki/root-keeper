<?php

namespace App\Support\Import;

final readonly class ImportPlan
{
    /**
     * @param  list<PlannedPerson>  $persons  persons added as new
     * @param  list<PlannedMatch>  $matched  persons of the tree that the repo person is matched to
     * @param  list<ImportIssue>  $skippedPersons
     * @param  list<PlannedRelation>  $relations  new pairs and existing ones that only get the repo id
     * @param  list<ImportIssue>  $skippedRelations
     * @param  list<PlannedMarriage>  $marriages  new weddings and existing ones that only get the repo id
     * @param  list<ImportIssue>  $skippedMarriages
     * @param  list<ImportIssue>  $skippedValues  repo values left out, e.g. a gender that would give a child two parents of one sex
     * @param  list<ValueConflict>  $conflicts  every conflict of the matches and the weddings
     * @param  int  $births  births written: of new persons and the missing ones of matched persons
     * @param  int  $weddings  new weddings
     * @param  array<string, int>  $datesByQualifier  keyed by DateQualifier value, only qualifiers that occur
     * @param  array<string, array<string, string|null>>  $snapshots  repo key => the repo values to remember after the write
     * @param  list<string>  $forgottenSnapshots  repo keys whose memory the write removes
     */
    public function __construct(
        public array $persons,
        public array $matched,
        public array $skippedPersons,
        public array $relations,
        public array $skippedRelations,
        public array $marriages,
        public array $skippedMarriages,
        public array $skippedValues,
        public array $conflicts,
        public int $births,
        public int $deaths,
        public int $weddings,
        public array $datesByQualifier,
        public int $undatedEvents,
        public NoteValueCounts $noteValues,
        public ?ResearchPerson $probandHint,
        public array $snapshots,
        public array $forgottenSnapshots,
        public ?string $blockedReason,
    ) {}

    /**
     * Values the repo adds to empty fields of matched persons and existing weddings.
     */
    public function fillCount(): int
    {
        $fills = 0;

        foreach ($this->matched as $match) {
            $fills += count($match->fills);
        }

        foreach ($this->marriages as $marriage) {
            $fills += $marriage->dateFill === null ? 0 : 1;
        }

        return $fills;
    }
}
