<?php

namespace App\Support\Import;

/**
 * The place a repo text stands for once the tree and the author's answers are applied.
 */
final readonly class ResolvedPlace
{
    /**
     * @param  string  $token  `L:<id>` for a locality, `C:<id>` for a cemetery, `N:<key>` for a place the plan adds; the form the memory of an import keeps
     * @param  string|null  $localityToken  the locality of a cemetery, `null` for a locality
     */
    public function __construct(
        public string $token,
        public PlannedPlace $place,
        public ?string $localityToken = null,
    ) {}

    public function isNew(): bool
    {
        return str_starts_with($this->token, 'N:');
    }
}
