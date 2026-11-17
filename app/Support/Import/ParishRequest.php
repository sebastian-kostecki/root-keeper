<?php

namespace App\Support\Import;

use App\Enums\ParishKind;
use App\Enums\Religion;

/**
 * A parish or office the sources of the repo name: its seat, kind, religion and dedication. Sources that name the same
 * four things are one question.
 */
final readonly class ParishRequest
{
    /**
     * @param  string  $localityKey  the key of the seat's name
     * @param  string|null  $dedicationKey  the key of the dedication, `null` when the block gives none
     * @param  string|null  $dedication  the spelling of the dedication
     * @param  list<PlaceUse>  $uses  the sources; the repo id of a use is the id of the source
     */
    public function __construct(
        public string $localityKey,
        public ?Religion $religion,
        public ParishKind $kind,
        public ?string $dedicationKey,
        public ?string $dedication,
        public array $uses,
    ) {}

    public function decisionKey(): string
    {
        return PlaceDecisions::parishKey($this->localityKey, $this->religion, $this->kind, $this->dedicationKey);
    }
}
