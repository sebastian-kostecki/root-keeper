<?php

namespace App\Support\Import;

final readonly class PlannedMarriage
{
    /**
     * @param  int|null  $existingEventId  id of the wedding already in the tree, which only gets the repo id; null for a new one
     * @param  PlannedFill|null  $dateFill  the date the repo adds to the existing wedding
     * @param  ValueConflict|null  $dateConflict  the date that differs in the existing wedding
     * @param  PlannedChange|null  $dateChange  the date the repo changed since the last import in a wedding nobody touched
     * @param  bool  $known  the existing wedding already carries this repo id, so only the date and the place can change
     * @param  PlannedPlace|null  $place  where a new wedding stands
     * @param  PlannedFill|null  $placeFill  the place the repo adds to the existing wedding
     * @param  ValueConflict|null  $placeConflict  the place that differs in the existing wedding
     * @param  PlannedChange|null  $placeChange  the place the repo changed since the last import in a wedding nobody touched
     */
    public function __construct(
        public ResearchMarriage $marriage,
        public string $repoId,
        public ?int $existingEventId = null,
        public ?PlannedFill $dateFill = null,
        public ?ValueConflict $dateConflict = null,
        public ?PlannedChange $dateChange = null,
        public bool $known = false,
        public ?PlannedPlace $place = null,
        public ?PlannedFill $placeFill = null,
        public ?ValueConflict $placeConflict = null,
        public ?PlannedChange $placeChange = null,
    ) {}
}
