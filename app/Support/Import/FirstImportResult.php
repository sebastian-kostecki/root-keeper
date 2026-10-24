<?php

namespace App\Support\Import;

final readonly class FirstImportResult
{
    /**
     * @param  int  $events  every event written: births, deaths and weddings
     */
    public function __construct(
        public int $persons,
        public int $births,
        public int $deaths,
        public int $marriages,
        public int $relations,
        public int $events,
    ) {}
}
