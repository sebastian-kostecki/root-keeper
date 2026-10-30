<?php

namespace App\Support\Import;

final readonly class ImportResult
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
     * @param  int  $changesTaken  changes of the repo the author approved and the write took
     * @param  int  $changesRejected  changes of the repo the author rejected
     * @param  int  $removedPersons  persons the repo dropped and the author had removed
     * @param  int  $removedRecords  other records (deaths, pairs, weddings) the repo dropped and the author had removed
     * @param  int  $detached  records the repo dropped that stay in the tree without a repo id
     * @param  int  $restored  records deleted in the app that came back from the repo
     * @param  int  $keptDeleted  keys deleted in the app that the author left deleted
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
        public int $changesTaken = 0,
        public int $changesRejected = 0,
        public int $removedPersons = 0,
        public int $removedRecords = 0,
        public int $detached = 0,
        public int $restored = 0,
        public int $keptDeleted = 0,
    ) {}
}
