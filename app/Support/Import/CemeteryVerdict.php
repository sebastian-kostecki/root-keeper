<?php

namespace App\Support\Import;

use App\Enums\CemeteryMatchKind;
use App\Models\Cemetery;

/**
 * What the tree makes of one cemetery of the repo once its locality is decided.
 */
final readonly class CemeteryVerdict
{
    /**
     * @param  int|null  $localityId  the locality of the cemetery, `null` when the locality is new
     * @param  Cemetery|null  $cemetery  the cemetery of a sure verdict
     * @param  array<int, Cemetery>  $options  the cemeteries of the locality to choose from
     */
    public function __construct(
        public CemeteryRequest $request,
        public CemeteryMatchKind $kind,
        public ?int $localityId = null,
        public ?Cemetery $cemetery = null,
        public array $options = [],
    ) {}

    public function eventCount(): int
    {
        return count($this->request->uses);
    }
}
