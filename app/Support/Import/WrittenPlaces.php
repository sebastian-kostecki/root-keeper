<?php

namespace App\Support\Import;

/**
 * The ids the write gave to the places the plan adds, by the key the plan names them with.
 *
 * A place of the plan that nothing in the write used is not created and has no id here.
 */
final readonly class WrittenPlaces
{
    /**
     * @param  array<string, int>  $localities  key of the name => id of the locality the write added
     * @param  array<string, int>  $cemeteries  {@see PlaceResolver::newCemeteryKey()} => id of the cemetery the write added
     */
    public function __construct(
        public array $localities = [],
        public array $cemeteries = [],
    ) {}

    /**
     * The locality a planned place stands in: the one the plan names or the one the write added.
     */
    public function localityId(PlannedPlace $place): ?int
    {
        return $place->localityId ?? ($place->newLocality === null ? null : ($this->localities[$place->newLocality] ?? null));
    }

    /**
     * The cemetery a planned place names: the one the plan names or the one the write added.
     */
    public function cemeteryId(PlannedPlace $place): ?int
    {
        return $place->cemeteryId ?? ($place->newCemetery === null ? null : ($this->cemeteries[$place->newCemetery] ?? null));
    }

    /**
     * A token of the memory of an import with the id the write gave: `N:<key>` becomes `L:<id>` or `C:<id>`, and `null`
     * when the place was not added. Any other token stays as it is.
     */
    public function token(?string $token): ?string
    {
        if ($token === null || ! str_starts_with($token, 'N:')) {
            return $token;
        }

        $key = substr($token, 2);

        return match (true) {
            isset($this->cemeteries[$key]) => 'C:'.$this->cemeteries[$key],
            isset($this->localities[$key]) => 'L:'.$this->localities[$key],
            default => null,
        };
    }
}
