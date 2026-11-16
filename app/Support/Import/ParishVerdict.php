<?php

namespace App\Support\Import;

use App\Enums\ParishMatchKind;
use App\Models\Parish;

/**
 * What the tree makes of one parish of the repo once its seat is decided.
 */
final readonly class ParishVerdict
{
    /**
     * @param  int|null  $localityId  the seat, `null` when the seat is new
     * @param  Parish|null  $parish  the parish of a sure verdict
     * @param  array<int, Parish>  $options  the candidates of a choice
     */
    public function __construct(
        public ParishRequest $request,
        public ParishMatchKind $kind,
        public ?int $localityId = null,
        public ?Parish $parish = null,
        public array $options = [],
    ) {}

    public function sourceCount(): int
    {
        return count($this->request->uses);
    }
}
