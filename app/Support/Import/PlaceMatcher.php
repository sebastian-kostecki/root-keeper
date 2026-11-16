<?php

namespace App\Support\Import;

use App\Enums\PlaceMatchKind;
use App\Models\Locality;
use App\Models\LocalityAlias;
use Illuminate\Support\Str;

/**
 * The rules that sort a place name of the repo against the localities and variants of one tree:
 * - sure: a variant of the tree has the same key (a variant wins over a locality name), otherwise exactly one locality
 *   has it,
 * - doubtful: otherwise, when localities (by name or variant) look similar or several share the key,
 * - new: the rest.
 *
 * The keys of the gazetteer are computed once, and the outcome is kept per key, so that recognizing the same name for
 * many events reads the gazetteer once.
 */
final class PlaceMatcher
{
    /** @var array<int, Locality> */
    private array $byId = [];

    /** @var array<int, string> locality id => key of its name */
    private array $nameKeys = [];

    /** @var list<array{0: int, 1: string}> locality id and key of each variant */
    private array $variantKeys = [];

    /** @var array<string, array{kind: PlaceMatchKind, locality: Locality|null, candidates: list<Locality>, holders: list<int>}> */
    private array $outcomes = [];

    /**
     * @param  iterable<Locality>  $localities  the localities of the tree
     * @param  iterable<LocalityAlias>  $aliases  the variants of the tree
     */
    public function __construct(iterable $localities, iterable $aliases)
    {
        foreach ($localities as $locality) {
            $this->byId[$locality->id] = $locality;
            $this->nameKeys[$locality->id] = (string) PlaceName::key($locality->name);
        }

        foreach ($aliases as $alias) {
            if (isset($this->byId[$alias->locality_id])) {
                $this->variantKeys[] = [$alias->locality_id, (string) PlaceName::key($alias->name)];
            }
        }
    }

    /**
     * The verdict for one place name, all its spellings together.
     *
     * @param  array<string, list<PlaceUse>>  $spellings  spelling => the events that use it
     */
    public function verdict(string $key, array $spellings): PlaceVerdict
    {
        $outcome = $this->outcomes[$key] ??= $this->outcome($key);

        return new PlaceVerdict(
            $key,
            self::chooseSpelling(array_map(count(...), $spellings)),
            $outcome['kind'],
            array_merge(...array_values($spellings)),
            $outcome['locality'],
            $outcome['candidates'],
            $outcome['holders'],
        );
    }

    /**
     * The spelling a new place is added with: one with Polish characters, else the most frequent, else the first
     * alphabetically.
     *
     * @param  array<string, int>  $counts  spelling => events
     */
    public static function chooseSpelling(array $counts): string
    {
        $spellings = array_map(strval(...), array_keys($counts));

        usort($spellings, fn (string $a, string $b): int => [Str::ascii($a) === $a, -$counts[$a], $a] <=> [Str::ascii($b) === $b, -$counts[$b], $b]);

        return $spellings[0];
    }

    /**
     * @return array{kind: PlaceMatchKind, locality: Locality|null, candidates: list<Locality>, holders: list<int>}
     */
    private function outcome(string $key): array
    {
        $viaAlias = [];

        foreach ($this->variantKeys as [$localityId, $variantKey]) {
            if ($variantKey === $key) {
                $viaAlias[$localityId] = $this->byId[$localityId];
            }
        }

        $sameName = array_filter($this->byId, fn (Locality $locality): bool => $this->nameKeys[$locality->id] === $key);
        $holders = array_values(array_unique([...array_keys($viaAlias), ...array_keys($sameName)]));
        sort($holders);

        if (count($viaAlias) === 1) {
            return ['kind' => PlaceMatchKind::Sure, 'locality' => array_values($viaAlias)[0], 'candidates' => [], 'holders' => $holders];
        }

        if ($viaAlias !== []) {
            return ['kind' => PlaceMatchKind::Doubtful, 'locality' => null, 'candidates' => $this->sorted($key, $viaAlias), 'holders' => $holders];
        }

        if (count($sameName) === 1) {
            return ['kind' => PlaceMatchKind::Sure, 'locality' => array_values($sameName)[0], 'candidates' => [], 'holders' => $holders];
        }

        $similar = $sameName;

        foreach ($this->nameKeys as $localityId => $nameKey) {
            if ($this->looksSimilar($key, $nameKey)) {
                $similar[$localityId] = $this->byId[$localityId];
            }
        }

        foreach ($this->variantKeys as [$localityId, $variantKey]) {
            if ($this->looksSimilar($key, $variantKey)) {
                $similar[$localityId] = $this->byId[$localityId];
            }
        }

        if ($similar === []) {
            return ['kind' => PlaceMatchKind::New, 'locality' => null, 'candidates' => [], 'holders' => $holders];
        }

        return ['kind' => PlaceMatchKind::Doubtful, 'locality' => null, 'candidates' => $this->sorted($key, $similar), 'holders' => $holders];
    }

    private function looksSimilar(string $key, string $other): bool
    {
        return $other !== '' && $other !== $key && PlaceName::similar($key, $other);
    }

    /**
     * The candidates closest to the key first, then by name.
     *
     * @param  array<int, Locality>  $candidates
     * @return list<Locality>
     */
    private function sorted(string $key, array $candidates): array
    {
        $candidates = array_values($candidates);
        $distance = fn (Locality $locality): int => levenshtein($key, $this->nameKeys[$locality->id]);

        usort($candidates, fn (Locality $a, Locality $b): int => [$distance($a), $a->name, $a->id] <=> [$distance($b), $b->name, $b->id]);

        return $candidates;
    }
}
