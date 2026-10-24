<?php

namespace App\Support\Import;

final readonly class FirstImportResult
{
    /**
     * @param  int  $persons  persons added as new
     * @param  int  $births  births written: of new persons and the missing ones of matched persons
     * @param  int  $deaths  deaths written, counted the same way
     * @param  int  $marriages  new weddings
     * @param  int  $relations  new parent-child pairs
     * @param  int  $events  every event written: births, deaths and weddings
     * @param  int  $matched  persons of the tree that got a repo id
     * @param  int  $fills  values the repo added to empty fields
     * @param  int  $takenFromRepo  conflicts the author settled in favour of the repo
     * @param  int  $markedRelations  existing pairs that only got a repo id
     * @param  int  $markedMarriages  existing weddings that only got a repo id
     */
    public function __construct(
        public int $persons,
        public int $births,
        public int $deaths,
        public int $marriages,
        public int $relations,
        public int $events,
        public int $matched = 0,
        public int $fills = 0,
        public int $takenFromRepo = 0,
        public int $markedRelations = 0,
        public int $markedMarriages = 0,
    ) {}
}
