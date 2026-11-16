<?php

namespace App\Support\Import;

use App\Enums\ParishKind;
use App\Enums\Religion;

/**
 * A parish or office the import adds: its seat, kind, religion and dedication.
 */
final readonly class PlannedParish
{
    /**
     * @param  int|null  $localityId  a locality of the tree
     * @param  string|null  $newLocality  the key of the name of a new locality, when the seat is new
     */
    public function __construct(
        public ParishKind $kind,
        public ?Religion $religion,
        public ?string $dedication,
        public ?int $localityId = null,
        public ?string $newLocality = null,
    ) {}
}
