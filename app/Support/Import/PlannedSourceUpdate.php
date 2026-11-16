<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;

/**
 * A source of the tree that an earlier import took from the repo: what this import changes in its description and its scan.
 */
final readonly class PlannedSourceUpdate
{
    /**
     * @param  list<PlannedChange>  $changes  repo changes nobody touched in the app; a new scan (another hash) is one of them, with the field `file`
     * @param  list<PlannedFill>  $fills  values the repo adds to empty fields
     * @param  list<ValueConflict>  $conflicts
     * @param  int  $keptManual  fields changed by hand in the app while the repo did not move, left alone
     */
    public function __construct(
        public ResearchSource $source,
        public int $sourceId,
        public array $changes = [],
        public array $fills = [],
        public array $conflicts = [],
        public int $keptManual = 0,
    ) {}

    /**
     * How a source of the repo is named to the author: its name or the scan file, then the repo id.
     */
    public static function labelOf(ResearchSource $source): string
    {
        return sprintf('%s (%s)', PlanImport::cleanText($source->name) ?? basename($source->scanPath), $source->id);
    }
}
