<?php

namespace App\Support\Import;

use App\Enums\PlaceMatchKind;
use App\Models\Locality;

/**
 * What the tree makes of one place name of the repo (all spellings with the same key together).
 */
final readonly class PlaceVerdict
{
    /**
     * @param  string  $key  the key of the name, see {@see PlaceName::key()}
     * @param  string  $name  the spelling to show and to add to the list when the place is new
     * @param  Locality|null  $locality  the locality of a sure verdict
     * @param  list<Locality>  $candidates  the localities of a doubtful verdict, the closest first
     * @param  list<PlaceUse>  $uses  the events that name the place
     */
    public function __construct(
        public string $key,
        public string $name,
        public PlaceMatchKind $kind,
        public array $uses,
        public ?Locality $locality = null,
        public array $candidates = [],
    ) {}

    public function eventCount(): int
    {
        return count($this->uses);
    }

    /**
     * @return list<string>
     */
    public function repoIds(): array
    {
        return array_values(array_unique(array_map(fn (PlaceUse $use): string => $use->repoId, $this->uses)));
    }
}
