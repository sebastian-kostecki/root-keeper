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
     * @param  list<int>  $holders  ids of the localities whose name or variant already has the key
     */
    public function __construct(
        public string $key,
        public string $name,
        public PlaceMatchKind $kind,
        public array $uses,
        public ?Locality $locality = null,
        public array $candidates = [],
        public array $holders = [],
    ) {}

    /**
     * Whether a locality or a variant of the tree already has the key, so that the name cannot be added as a new
     * locality.
     */
    public function keyTaken(): bool
    {
        return $this->holders !== [];
    }

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
