<?php

namespace App\Support\Import;

/**
 * What clearing a tree of its people and facts removes: the counts shown before the question and after the removal,
 * and whether the tree loses its proband.
 */
final readonly class TreeClearance
{
    public function __construct(
        public int $persons,
        public int $events,
        public int $relations,
        public int $residences,
        public int $surnameVariants,
        public int $photos,
        public int $sourceLinks,
        public int $buriedLinks,
        public int $snapshots,
        public bool $hasProband,
    ) {}

    public function hasRecords(): bool
    {
        return $this->persons + $this->events > 0;
    }
}
