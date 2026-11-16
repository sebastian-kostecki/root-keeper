<?php

namespace App\Support\Import;

/**
 * Where the place of a repo event goes: a locality or a cemetery of the tree, or a place the plan adds.
 *
 * A new locality is named by the key of its name (a key of {@see ImportPlan::$newLocalities}), a new cemetery by the key
 * {@see PlaceResolver::newCemeteryKey()} gives (a key of {@see ImportPlan::$newCemeteries}). The write replaces both with ids.
 */
final readonly class PlannedPlace
{
    /**
     * @param  string  $label  the place as shown to the author, e.g. "Bukinia (w repo: Bokinia)" or "nowa: Bokinia"
     * @param  int|null  $localityId  a locality of the tree
     * @param  string|null  $newLocality  the key of the name of a new locality
     * @param  int|null  $cemeteryId  a cemetery of the tree
     * @param  string|null  $newCemetery  the key of a new cemetery
     * @param  int|null  $parishId  a parish of the tree
     * @param  string|null  $newParish  the key of a new parish, see {@see PlaceResolver::newParishKey()}
     */
    public function __construct(
        public string $label,
        public ?int $localityId = null,
        public ?string $newLocality = null,
        public ?int $cemeteryId = null,
        public ?string $newCemetery = null,
        public ?int $parishId = null,
        public ?string $newParish = null,
    ) {}
}
