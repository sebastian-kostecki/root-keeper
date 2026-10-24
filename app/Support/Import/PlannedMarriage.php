<?php

namespace App\Support\Import;

final readonly class PlannedMarriage
{
    /**
     * @param  int|null  $existingEventId  id of the wedding already in the tree, which only gets the repo id; null for a new one
     * @param  PlannedFill|null  $dateFill  the date the repo adds to the existing wedding
     * @param  ValueConflict|null  $dateConflict  the date that differs in the existing wedding
     */
    public function __construct(
        public ResearchMarriage $marriage,
        public string $repoId,
        public ?int $existingEventId = null,
        public ?PlannedFill $dateFill = null,
        public ?ValueConflict $dateConflict = null,
    ) {}
}
