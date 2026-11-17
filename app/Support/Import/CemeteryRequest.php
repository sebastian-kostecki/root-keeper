<?php

namespace App\Support\Import;

/**
 * A cemetery the repo names for burials: the locality of the cemetery and, when the block gives it, the cemetery name.
 */
final readonly class CemeteryRequest
{
    /**
     * @param  string  $localityKey  the key of the cemetery's locality name
     * @param  string|null  $cemeteryKey  the key of the cemetery name, `null` when the block gives none
     * @param  string|null  $name  the spelling of the cemetery name
     * @param  list<PlaceUse>  $uses  the burials
     */
    public function __construct(
        public string $localityKey,
        public ?string $cemeteryKey,
        public ?string $name,
        public array $uses,
    ) {}

    public function decisionKey(): string
    {
        return PlaceDecisions::cemeteryKey($this->localityKey, $this->cemeteryKey);
    }
}
