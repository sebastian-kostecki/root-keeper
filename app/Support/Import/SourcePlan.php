<?php

namespace App\Support\Import;

/**
 * What an import does with the sources of the repo: the new ones, the ones joined to a manual source, the known ones
 * with their changes, the restored ones and the memory to leave, plus the sets of ids that decide which links of events
 * the plan compares.
 */
final readonly class SourcePlan
{
    /**
     * @param  list<PlannedSource>  $new  sources added with their scan
     * @param  list<PlannedSourceMerge>  $merges  manual sources that become the source of the repo
     * @param  list<PlannedSourceUpdate>  $updates  known sources with something to write
     * @param  list<PlannedRestoration>  $restorations  sources deleted in the app that come back
     * @param  list<PlannedChange>  $changes  every repo change of a known source nobody touched in the app
     * @param  list<ValueConflict>  $conflicts
     * @param  array<string, array<string, string|null>>  $snapshots  repo key => the repo values to remember after the write; `parafia_id` is a token, see {@see ResolvedPlace}
     * @param  int  $kept  fields changed by hand while the repo did not move, left alone
     * @param  array<string, true>  $available  ids of the sources that have a record after the import: known, new, joined and restored
     * @param  array<string, true>  $unavailable  ids left out this run: skipped by the reading, left deleted, detached or removed
     * @param  array<string, true>  $skipped  ids the reading skipped, and ids of sources of the tree held back from removal because an entry or a file of zrodla/ could not be read: this run cannot tell what the repo says about them
     * @param  array<string, true>  $apart  ids joined or restored in this run: their links are written with the source, so the three values of an event leave them out
     */
    public function __construct(
        public array $new = [],
        public array $merges = [],
        public array $updates = [],
        public array $restorations = [],
        public array $changes = [],
        public array $conflicts = [],
        public array $snapshots = [],
        public int $kept = 0,
        public array $available = [],
        public array $unavailable = [],
        public array $skipped = [],
        public array $apart = [],
    ) {}

    /**
     * Whether a link to the source can be written: the source has a record after the import and is not left out.
     */
    public function linkable(string $id): bool
    {
        return isset($this->available[$id]) && ! isset($this->unavailable[$id]);
    }

    /**
     * Whether the three values of an event take the link into account.
     */
    public function comparable(string $id): bool
    {
        return $this->linkable($id) && ! isset($this->apart[$id]);
    }

    public function isSkipped(string $id): bool
    {
        return isset($this->skipped[$id]);
    }
}
