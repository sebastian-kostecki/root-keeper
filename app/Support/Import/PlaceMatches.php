<?php

namespace App\Support\Import;

final readonly class PlaceMatches
{
    /**
     * @param  array<string, PlaceVerdict>  $localities  keyed by the key of the name
     * @param  array<string, CemeteryRequest>  $cemeteryRequests  keyed by {@see PlaceDecisions::cemeteryKey()}
     * @param  array<string, ParishRequest>  $parishRequests  keyed by {@see PlaceDecisions::parishKey()}
     */
    public function __construct(
        public array $localities = [],
        public array $cemeteryRequests = [],
        public array $parishRequests = [],
    ) {}
}
