<?php

namespace App\Support\Import;

use App\Enums\ParishKind;
use App\Enums\Religion;

final readonly class ResearchParish
{
    /**
     * @param  string  $locality  the seat of the parish, in the nominative
     */
    public function __construct(
        public string $locality,
        public ?Religion $religion = null,
        public ParishKind $kind = ParishKind::Parish,
        public ?string $dedication = null,
    ) {}
}
