<?php

namespace App\Support\Import;

/**
 * How many values from the blocks go into the person note instead of a column.
 */
final readonly class NoteValueCounts
{
    public function __construct(
        public int $places = 0,
        public int $burials = 0,
        public int $levels = 0,
        public int $eventNotes = 0,
        public int $surnameVariants = 0,
    ) {}
}
