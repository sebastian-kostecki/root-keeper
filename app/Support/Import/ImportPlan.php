<?php

namespace App\Support\Import;

use App\Models\RepoSnapshot;

final readonly class ImportPlan
{
    /**
     * @param  list<PlannedPerson>  $persons  persons added as new
     * @param  list<PlannedMatch>  $matched  persons of the tree that the repo person is matched to
     * @param  list<ImportIssue>  $skippedPersons
     * @param  list<PlannedRelation>  $relations  new pairs and existing ones that only get the repo id, and the changed pairs a re-import restores
     * @param  list<ImportIssue>  $skippedRelations
     * @param  list<PlannedMarriage>  $marriages  new weddings, existing ones that get the repo id and known ones whose date changes
     * @param  list<ImportIssue>  $skippedMarriages
     * @param  list<ImportIssue>  $skippedValues  repo values left out, e.g. a gender that would give a child two parents of one sex
     * @param  list<ValueConflict>  $conflicts  every conflict of the matches, the known persons and the weddings
     * @param  int  $births  births written: of new and restored persons and the missing ones of matched and known persons
     * @param  int  $weddings  new weddings
     * @param  array<string, int>  $datesByQualifier  keyed by DateQualifier value, only qualifiers that occur
     * @param  array<string, array<string, string|null>>  $snapshots  repo key => the repo values to remember after the write; the ids of a recognized place (`miejsce_id`, `cmentarz_id`) are tokens (see {@see ResolvedPlace}), and a burial whose cemetery is not recognized has an entry with `cmentarz_id` empty but no record
     * @param  list<string>  $forgottenSnapshots  repo keys whose memory the write removes
     * @param  list<PlannedUpdate>  $updates  known persons with something to write
     * @param  list<PlannedChange>  $changes  every repo change nobody touched in the app, of the known persons and weddings
     * @param  list<PlannedRemoval>  $removals  records removed in the repo, deleted or detached; births are in `$detachedBirths`
     * @param  list<PlannedRestoration>  $restorations  records deleted in the app that come back
     * @param  list<PlannedRemoval>  $detachedBirths  births the repo no longer has, only detached from the repo
     * @param  list<ImportIssue>  $unassessable  records that could be removed in the repo, left alone because an entry of the repo was skipped without a readable id
     * @param  int  $keptManual  fields changed by hand in the app while the repo did not move, left alone
     * @param  array<string, array<string, string|null>>  $keptDeletedSnapshots  repo key => the repo values to remember for a record the author left deleted
     * @param  array<string, string>  $seenSnapshots  repo key => `RepoSnapshot::fingerprint()` of the memory entry the plan saw, for every key the write touches that had one
     * @param  array<string, string>  $newLocalities  key of the name => the name of each locality the plan adds, only the ones a plan item uses; the write decides after the rejected changes and conflicts
     * @param  array<string, PlannedAlias>  $aliases  key of the spelling => the variant to remember, always saved
     * @param  array<string, PlannedCemetery>  $newCemeteries  {@see PlaceDecisions::cemeteryKey()} => each cemetery the plan adds, only the ones a plan item uses
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
        public array $updates = [],
        public array $changes = [],
        public array $removals = [],
        public array $restorations = [],
        public array $detachedBirths = [],
        public array $unassessable = [],
        public int $keptManual = 0,
        public array $keptDeletedSnapshots = [],
        public array $seenSnapshots = [],
        public array $newLocalities = [],
        public array $aliases = [],
        public array $newCemeteries = [],
    ) {}

    /**
     * Values the repo adds to empty fields of matched and known persons and of existing weddings.
     */
    public function fillCount(): int
    {
        $fills = 0;

        foreach ($this->matched as $match) {
            $fills += count($match->fills);
        }

        foreach ($this->updates as $update) {
            $fills += count($update->fills);
        }

        foreach ($this->marriages as $marriage) {
            $fills += ($marriage->dateFill === null ? 0 : 1) + ($marriage->placeFill === null ? 0 : 1);
        }

        return $fills;
    }

    /**
     * The keys whose memory entry the write would add or change: no stored entry, another deletion flag or other values.
     *
     * @param  array<string, RepoSnapshot>  $stored  the memory of the tree by repo key
     * @return list<string>
     */
    public function changedSnapshots(array $stored): array
    {
        $changed = [];

        foreach ([[$this->snapshots, false], [$this->keptDeletedSnapshots, true]] as [$entries, $deletionKept]) {
            foreach ($entries as $key => $values) {
                $old = $stored[$key] ?? null;

                if ($old === null || $old->deletion_kept !== $deletionKept || ! self::sameValues($old->values, $values)) {
                    $changed[] = (string) $key;
                }
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, string|null>  $first
     * @param  array<string, string|null>  $second
     */
    private static function sameValues(array $first, array $second): bool
    {
        ksort($first);
        ksort($second);

        return $first === $second;
    }
}
