<?php

namespace App\Support\Import;

/**
 * What the author decided about the places of the repo, one answer per name. Sure matches need no answer.
 *
 * The planner checks that the maps cover exactly the names the verdicts asked about and point at records of the tree.
 */
final readonly class PlaceDecisions
{
    /** Add the place to the tree. */
    public const NEW = 'new';

    /** Leave the place out; its text stays in the note. */
    public const NONE = 'none';

    /**
     * @param  array<string, int|string>  $localities  key of the name => id of a locality, `NEW` or `NONE`
     * @param  array<string, true>  $aliases  key of the name => true when the spelling is remembered as a variant of the chosen locality
     * @param  array<string, int|string>  $cemeteries  {@see cemeteryKey()} => id of a cemetery, `NEW` or `NONE`
     */
    public function __construct(
        public array $localities = [],
        public array $aliases = [],
        public array $cemeteries = [],
    ) {}

    /**
     * The key of a cemetery in the maps: the key of its locality name and the key of its own name (empty for none).
     */
    public static function cemeteryKey(string $localityKey, ?string $cemeteryKey): string
    {
        return $localityKey.'|'.($cemeteryKey ?? '');
    }
}
