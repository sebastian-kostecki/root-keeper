<?php

namespace App\Support\Import;

/**
 * How many places and burials the plan adds to the tree and how many values from the blocks go into the person note
 * instead of a column. `$surnameVariants` counts the variants written for new and first-time matched persons.
 */
final readonly class NoteValueCounts
{
    public function __construct(
        public int $newLocalities = 0,
        public int $newCemeteries = 0,
        public int $aliases = 0,
        public int $placedEvents = 0,
        public int $unplacedEvents = 0,
        public int $burials = 0,
        public int $levels = 0,
        public int $eventNotes = 0,
        public int $surnameVariants = 0,
    ) {}
}
