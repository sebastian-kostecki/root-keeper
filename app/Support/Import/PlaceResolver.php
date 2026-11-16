<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;
use App\Enums\CemeteryMatchKind;
use App\Enums\PlaceMatchKind;
use App\Models\Cemetery;
use App\Models\Locality;

/**
 * Turns the place texts of the repo into places of the tree, with the answers the author gave and the memory of the
 * last import. Reads only the records of the tree it was built from. A text the author was not asked about is
 * recognized by the same rules the matcher uses (a sure match), and failing that by the id the last import remembered.
 *
 * A name the answers do not cover counts as "no place". The places it adds are collected in {@see $newLocalities} and
 * {@see $newCemeteries} when a text resolves to them.
 */
final class PlaceResolver
{
    /** @var array<string, string> key of the name => name */
    public array $newLocalities = [];

    /** @var array<string, PlannedCemetery> */
    public array $newCemeteries = [];

    /** @var array<int, list<array{0: Cemetery, 1: string|null}>> locality id => its cemeteries with the key of their name */
    private array $cemeteriesByLocality = [];

    /**
     * @param  PlaceMatcher  $matcher  the rules over the localities and variants of the same tree
     * @param  array<int, Locality>  $localities  by id
     * @param  array<int, Cemetery>  $cemeteries  by id, with the locality loaded
     */
    public function __construct(
        private readonly PlaceMatcher $matcher,
        private readonly array $localities,
        private readonly array $cemeteries,
        private readonly PlaceMatches $places,
        private readonly CemeteryMatches $cemeteryMatches,
        private readonly PlaceDecisions $decisions,
    ) {
        foreach ($cemeteries as $cemetery) {
            $this->cemeteriesByLocality[$cemetery->locality_id][] = [$cemetery, PlaceName::key($cemetery->name)];
        }
    }

    /**
     * @param  string|null  $remembered  the token of the last import for this text, used when the text is not asked about and not recognized by name
     */
    public function locality(?string $text, ?string $remembered): ?ResolvedPlace
    {
        $text = PlanImport::cleanText($text);
        $key = PlaceName::key($text);

        if ($text === null || $key === null) {
            return null;
        }

        $verdict = $this->places->localities[$key] ?? null;

        if ($verdict === null) {
            return $this->known($text) ?? $this->existingFromToken($remembered, 'L:', $text);
        }

        if ($verdict->kind === PlaceMatchKind::Sure && $verdict->locality !== null) {
            return $this->existingLocality($verdict->locality, $text);
        }

        $answer = $this->decisions->localities[$key] ?? PlaceDecisions::NONE;

        if (is_int($answer) && isset($this->localities[$answer])) {
            return $this->existingLocality($this->localities[$answer], $text);
        }

        return $answer === PlaceDecisions::NEW ? $this->newLocality($key, $verdict->name) : null;
    }

    /**
     * @param  string|null  $remembered  the cemetery token of the last import for these texts
     */
    public function cemetery(?string $localityText, ?string $cemeteryText, ?string $remembered): ?ResolvedPlace
    {
        $localityText = PlanImport::cleanText($localityText);
        $localityKey = PlaceName::key($localityText);

        if ($localityText === null || $localityKey === null) {
            return null;
        }

        $cemeteryText = PlanImport::cleanText($cemeteryText);
        $requestKey = PlaceDecisions::cemeteryKey($localityKey, PlaceName::key($cemeteryText));
        $verdict = $this->cemeteryMatches->matches[$requestKey] ?? null;

        if ($verdict === null) {
            return $this->knownCemetery($localityText, $cemeteryText) ?? $this->existingFromToken($remembered, 'C:', $localityText);
        }

        return match ($verdict->kind) {
            CemeteryMatchKind::None => null,
            CemeteryMatchKind::Sure => $verdict->cemetery === null ? null : $this->existingCemetery($verdict->cemetery, $localityText),
            CemeteryMatchKind::New => $this->newCemetery($verdict, $localityKey),
            CemeteryMatchKind::Choice => $this->chosenCemetery($verdict, $requestKey, $localityKey, $localityText),
        };
    }

    /**
     * The locality a text names by a sure match with the tree, whatever the author answered.
     */
    public function known(?string $text): ?ResolvedPlace
    {
        $text = PlanImport::cleanText($text);
        $key = PlaceName::key($text);

        if ($text === null || $key === null) {
            return null;
        }

        $verdict = $this->matcher->verdict($key, [$text => []]);

        return $verdict->kind === PlaceMatchKind::Sure && $verdict->locality !== null ? $this->existingLocality($verdict->locality, $text) : null;
    }

    /**
     * The cemetery two texts name by a sure match with the tree: the only cemetery of a sure locality when the block
     * gives no name, otherwise the only one with the same name.
     */
    public function knownCemetery(?string $localityText, ?string $cemeteryText): ?ResolvedPlace
    {
        $locality = $this->known($localityText);

        if ($locality === null || $locality->place->localityId === null) {
            return null;
        }

        $cemeteryKey = PlaceName::key(PlanImport::cleanText($cemeteryText));
        $inLocality = array_values(array_filter(
            $this->cemeteriesByLocality[$locality->place->localityId] ?? [],
            fn (array $entry): bool => $cemeteryKey === null || $entry[1] === $cemeteryKey,
        ));

        if (count($inLocality) !== 1) {
            return null;
        }

        return $this->existingCemetery($inLocality[0][0], PlanImport::cleanText($localityText) ?? '');
    }

    /**
     * Whether the token names a locality or a cemetery that is still in the tree.
     */
    public function tokenExists(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        return match (true) {
            str_starts_with($token, 'L:') => isset($this->localities[(int) substr($token, 2)]),
            str_starts_with($token, 'C:') => isset($this->cemeteries[(int) substr($token, 2)]),
            default => false,
        };
    }

    /**
     * The name of the place a token names, as shown to the author.
     */
    public function tokenLabel(?string $token): ?string
    {
        if ($token === null || ! $this->tokenExists($token)) {
            return null;
        }

        $id = (int) substr($token, 2);

        return str_starts_with($token, 'L:') ? $this->localities[$id]->name : $this->cemeteries[$id]->label();
    }

    private function existingLocality(Locality $locality, string $text): ResolvedPlace
    {
        $label = PlaceName::key($locality->name) === PlaceName::key($text) ? $locality->name : "{$locality->name} (w repo: {$text})";

        return new ResolvedPlace('L:'.$locality->id, new PlannedPlace($label, localityId: $locality->id));
    }

    private function existingCemetery(Cemetery $cemetery, string $localityText): ResolvedPlace
    {
        $label = PlaceName::key($cemetery->locality->name) === PlaceName::key($localityText) ? $cemetery->label() : "{$cemetery->label()} (w repo: {$localityText})";

        return new ResolvedPlace('C:'.$cemetery->id, new PlannedPlace($label, localityId: $cemetery->locality_id, cemeteryId: $cemetery->id), 'L:'.$cemetery->locality_id);
    }

    private function existingFromToken(?string $token, string $prefix, string $text): ?ResolvedPlace
    {
        if ($token === null || ! str_starts_with($token, $prefix) || ! $this->tokenExists($token)) {
            return null;
        }

        $id = (int) substr($token, 2);

        return $prefix === 'L:' ? $this->existingLocality($this->localities[$id], $text) : $this->existingCemetery($this->cemeteries[$id], $text);
    }

    private function newLocality(string $key, string $name): ResolvedPlace
    {
        $this->newLocalities[$key] = $name;

        return new ResolvedPlace('N:'.$key, new PlannedPlace("nowa: {$name}", newLocality: $key));
    }

    private function newCemetery(CemeteryVerdict $verdict, string $localityKey): ?ResolvedPlace
    {
        $request = $verdict->request;
        $localityId = $verdict->localityId;

        if ($localityId === null) {
            $locality = $this->places->localities[$localityKey] ?? null;

            if ($locality === null) {
                return null;
            }

            $this->newLocalities[$localityKey] = $locality->name;
            $localityName = $locality->name;
        } else {
            $localityName = $this->localities[$localityId]->name ?? '';
        }

        $key = self::newCemeteryKey($localityId, $localityKey, $request->cemeteryKey);
        $this->newCemeteries[$key] ??= new PlannedCemetery($request->name, $localityId, $localityId === null ? $localityKey : null);
        $label = 'nowy: '.($request->name === null ? "cmentarz {$localityName}" : "{$request->name}, {$localityName}");

        return new ResolvedPlace(
            'N:'.$key,
            new PlannedPlace($label, localityId: $localityId, newLocality: $localityId === null ? $localityKey : null, newCemetery: $key),
            $localityId === null ? 'N:'.$localityKey : 'L:'.$localityId,
        );
    }

    /**
     * The key a new cemetery has in the plan: its locality (the id of an existing one, the name key of a new one) and the
     * key of its name. Two spellings of one locality name the same new cemetery, so the write adds it once.
     */
    public static function newCemeteryKey(?int $localityId, string $localityKey, ?string $cemeteryKey): string
    {
        return ($localityId === null ? $localityKey : "#{$localityId}").'|'.($cemeteryKey ?? '');
    }

    private function chosenCemetery(CemeteryVerdict $verdict, string $requestKey, string $localityKey, string $localityText): ?ResolvedPlace
    {
        $answer = $this->decisions->cemeteries[$requestKey] ?? PlaceDecisions::NONE;

        if (is_int($answer)) {
            foreach ($verdict->options as $option) {
                if ($option->id === $answer) {
                    return $this->existingCemetery($option, $localityText);
                }
            }
        }

        return $answer === PlaceDecisions::NEW ? $this->newCemetery($verdict, $localityKey) : null;
    }
}
