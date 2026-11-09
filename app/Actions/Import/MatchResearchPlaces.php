<?php

namespace App\Actions\Import;

use App\Enums\CemeteryMatchKind;
use App\Enums\PlaceMatchKind;
use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\LocalityAlias;
use App\Models\RepoSnapshot;
use App\Models\Tree;
use App\Support\Import\CemeteryMatches;
use App\Support\Import\CemeteryRequest;
use App\Support\Import\CemeteryVerdict;
use App\Support\Import\ImportDecisions;
use App\Support\Import\PlaceDecisions;
use App\Support\Import\PlaceMatches;
use App\Support\Import\PlaceName;
use App\Support\Import\PlaceUse;
use App\Support\Import\PlaceVerdict;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Support\Str;

class MatchResearchPlaces
{
    /**
     * Sort the place names of the repo that need a decision into sure, doubtful and new against the localities of the
     * tree, without writing.
     *
     * Only events of the persons the import plans count, minus the ones left deleted, and only when the memory of the
     * last import does not hold the same place text with its recognized id. A name is judged once, whatever its
     * spelling or the number of events:
     * - sure: a variant of the tree has the same key (a variant wins over a locality name), otherwise exactly one
     *   locality has it,
     * - doubtful: otherwise, when localities (by name or variant) look similar or several share the key,
     * - new: the rest.
     *
     * Burials also record which cemetery they name; {@see cemeteries()} settles those once the localities are decided.
     */
    public function handle(Tree $tree, ResearchRepoReading $reading, RepoRecordClasses $classes, ImportDecisions $decisions): PlaceMatches
    {
        /** @var array<string, array<string, string|null>> $memory */
        $memory = $tree->repoSnapshots()->get()->mapWithKeys(fn (RepoSnapshot $snapshot): array => [$snapshot->repo_id => $snapshot->values])->all();
        $left = array_fill_keys($classes->keptDeleted, true);

        foreach ($decisions->deletions as $key => $choice) {
            if ($choice === ImportDecisions::KEEP) {
                $left[$key] = true;
            }
        }

        /** @var array<string, array<string, list<PlaceUse>>> $names key => spelling => uses */
        $names = [];
        /** @var array<string, array{localityKey: string, cemeteryKey: string|null, spellings: array<string, int>, uses: list<PlaceUse>}> $burials */
        $burials = [];

        foreach ($this->plannedEvents($reading, $classes, $decisions) as [$key, $file, $repoId, $event, $isBurial]) {
            $text = PlanImport::cleanText($event->place);
            $localityKey = PlaceName::key($text);

            if ($text === null || $localityKey === null || isset($left[$key])) {
                continue;
            }

            $cemetery = $isBurial ? PlanImport::cleanText($event->cemetery) : null;

            if (! $this->needsDecision($memory[$key] ?? null, $text, $cemetery, $isBurial)) {
                continue;
            }

            $use = new PlaceUse($repoId, $file, $key);
            $names[$localityKey][$text][] = $use;

            if ($isBurial) {
                $cemeteryKey = PlaceName::key($cemetery);
                $requestKey = PlaceDecisions::cemeteryKey($localityKey, $cemeteryKey);
                $burials[$requestKey] ??= ['localityKey' => $localityKey, 'cemeteryKey' => $cemeteryKey, 'spellings' => [], 'uses' => []];
                $burials[$requestKey]['uses'][] = $use;

                if ($cemetery !== null) {
                    $burials[$requestKey]['spellings'][$cemetery] = ($burials[$requestKey]['spellings'][$cemetery] ?? 0) + 1;
                }
            }
        }

        ksort($names);
        ksort($burials);

        $localities = $tree->localities()->orderBy('id')->get();
        $aliases = $tree->localityAliases()->orderBy('id')->get();
        $verdicts = [];

        foreach ($names as $key => $spellings) {
            $verdicts[$key] = $this->verdict($key, $spellings, $localities->values()->all(), $aliases->values()->all());
        }

        $requests = [];

        foreach ($burials as $requestKey => $burial) {
            $requests[$requestKey] = new CemeteryRequest(
                $burial['localityKey'],
                $burial['cemeteryKey'],
                $burial['spellings'] === [] ? null : $this->chooseSpelling($burial['spellings']),
                $burial['uses'],
            );
        }

        return new PlaceMatches($verdicts, $requests);
    }

    /**
     * Settle each cemetery the burials name, given the decisions about the localities (sure localities need none):
     * - none: the locality is left out,
     * - new: the locality is new or has no cemetery yet; the cemetery needs no question,
     * - sure: the name equals exactly one cemetery of the locality, or there is no name and the locality has one cemetery,
     * - choice: every other case, with the cemeteries of the locality to choose from.
     *
     * A cemetery whose locality is neither sure nor decided is left out.
     */
    public function cemeteries(Tree $tree, PlaceMatches $places, PlaceDecisions $decisions): CemeteryMatches
    {
        $cemeteries = $tree->cemeteries()->with('locality')->orderBy('id')->get()->groupBy('locality_id');
        $knownLocalities = $tree->localities()->pluck('id')->flip();
        $matches = [];

        foreach ($places->cemeteryRequests as $requestKey => $request) {
            $verdict = $places->localities[$request->localityKey] ?? null;
            $decision = $verdict === null ? null : ($verdict->kind === PlaceMatchKind::Sure ? $verdict->locality?->id : ($decisions->localities[$request->localityKey] ?? null));

            if ($decision === PlaceDecisions::NONE) {
                $matches[$requestKey] = new CemeteryVerdict($request, CemeteryMatchKind::None);
            } elseif ($decision === PlaceDecisions::NEW) {
                $matches[$requestKey] = new CemeteryVerdict($request, CemeteryMatchKind::New);
            } elseif (is_int($decision) && $knownLocalities->has($decision)) {
                $matches[$requestKey] = $this->cemeteryVerdict($request, $decision, $cemeteries->get($decision)?->values()->all() ?? []);
            }
        }

        return new CemeteryMatches($matches);
    }

    /**
     * @param  array<int, Cemetery>  $inLocality
     */
    private function cemeteryVerdict(CemeteryRequest $request, int $localityId, array $inLocality): CemeteryVerdict
    {
        if ($inLocality === []) {
            return new CemeteryVerdict($request, CemeteryMatchKind::New, $localityId);
        }

        $sure = null;

        if ($request->cemeteryKey === null) {
            $sure = count($inLocality) === 1 ? $inLocality[0] : null;
        } else {
            $same = array_values(array_filter($inLocality, fn (Cemetery $cemetery): bool => PlaceName::key($cemetery->name) === $request->cemeteryKey));
            $sure = count($same) === 1 ? $same[0] : null;
        }

        return $sure === null
            ? new CemeteryVerdict($request, CemeteryMatchKind::Choice, $localityId, null, $inLocality)
            : new CemeteryVerdict($request, CemeteryMatchKind::Sure, $localityId, $sure);
    }

    /**
     * The events of the planned persons whose place the import may set: birth, death, burial and weddings.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: ResearchEvent, 4: bool}> memory key, file, repo id, event, burial?
     */
    private function plannedEvents(ResearchRepoReading $reading, RepoRecordClasses $classes, ImportDecisions $decisions): array
    {
        $planned = array_fill_keys([
            ...array_map(strval(...), array_keys($classes->knownPersons)),
            ...array_map(strval(...), array_keys($decisions->identity->matched)),
            ...$decisions->identity->new,
        ], true);

        foreach ($decisions->identity->skipped as $skipped) {
            unset($planned[$skipped]);
        }

        $events = [];
        $persons = $reading->persons;
        ksort($persons);

        foreach ($persons as $repoId => $person) {
            $repoId = (string) $repoId;

            if (! isset($planned[$repoId])) {
                continue;
            }

            foreach ([[RepoKeys::birth($repoId), $person->birth, false], [RepoKeys::death($repoId), $person->death, false], [RepoKeys::burial($repoId), $person->burial, true]] as [$key, $event, $isBurial]) {
                if ($event !== null) {
                    $events[] = [$key, $person->file, $repoId, $event, $isBurial];
                }
            }
        }

        foreach ($reading->marriages as $marriage) {
            if (isset($planned[$marriage->personIds[0]], $planned[$marriage->personIds[1]])) {
                $events[] = [RepoKeys::marriage(...$marriage->personIds), $marriage->files[0] ?? '', $marriage->personIds[0], $marriage->event, false];
            }
        }

        return $events;
    }

    /**
     * An event needs a decision unless the memory holds the same repo text together with the id it was recognized as.
     *
     * @param  array<string, mixed>|null  $values
     */
    private function needsDecision(?array $values, string $place, ?string $cemetery, bool $burial): bool
    {
        if ($values === null || ! array_key_exists('miejsce', $values) || ! array_key_exists('miejsce_id', $values) || $values['miejsce'] !== $place) {
            return true;
        }

        return $burial && (! array_key_exists('cmentarz', $values) || ! array_key_exists('cmentarz_id', $values) || $values['cmentarz'] !== $cemetery);
    }

    /**
     * The verdict for one place name against the localities and variants of the tree. Public so that the planner can
     * recognize a name the memory of the last import already settled.
     *
     * @param  array<string, list<PlaceUse>>  $spellings
     * @param  array<int, Locality>  $localities
     * @param  array<int, LocalityAlias>  $aliases
     */
    public function verdict(string $key, array $spellings, array $localities, array $aliases): PlaceVerdict
    {
        $uses = array_merge(...array_values($spellings));
        $name = $this->chooseSpelling(array_map(count(...), $spellings));
        $byId = [];

        foreach ($localities as $locality) {
            $byId[$locality->id] = $locality;
        }

        $viaAlias = [];

        foreach ($aliases as $alias) {
            if (PlaceName::key($alias->name) === $key && isset($byId[$alias->locality_id])) {
                $viaAlias[$alias->locality_id] = $byId[$alias->locality_id];
            }
        }

        if (count($viaAlias) === 1) {
            return new PlaceVerdict($key, $name, PlaceMatchKind::Sure, $uses, array_values($viaAlias)[0]);
        }

        if ($viaAlias !== []) {
            return new PlaceVerdict($key, $name, PlaceMatchKind::Doubtful, $uses, null, $this->sorted($key, $viaAlias));
        }

        $sameName = array_filter($localities, fn (Locality $locality): bool => PlaceName::key($locality->name) === $key);

        if (count($sameName) === 1) {
            return new PlaceVerdict($key, $name, PlaceMatchKind::Sure, $uses, array_values($sameName)[0]);
        }

        $similar = [];

        foreach ($sameName as $locality) {
            $similar[$locality->id] = $locality;
        }

        foreach ($localities as $locality) {
            if ($this->looksSimilar($key, PlaceName::key($locality->name))) {
                $similar[$locality->id] = $locality;
            }
        }

        foreach ($aliases as $alias) {
            if (isset($byId[$alias->locality_id]) && $this->looksSimilar($key, PlaceName::key($alias->name))) {
                $similar[$alias->locality_id] = $byId[$alias->locality_id];
            }
        }

        if ($similar === []) {
            return new PlaceVerdict($key, $name, PlaceMatchKind::New, $uses);
        }

        return new PlaceVerdict($key, $name, PlaceMatchKind::Doubtful, $uses, null, $this->sorted($key, $similar));
    }

    private function looksSimilar(string $key, ?string $other): bool
    {
        return $other !== null && $other !== $key && PlaceName::similar($key, $other);
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
        $distance = fn (Locality $locality): int => levenshtein($key, (string) PlaceName::key($locality->name));

        usort($candidates, fn (Locality $a, Locality $b): int => [$distance($a), $a->name, $a->id] <=> [$distance($b), $b->name, $b->id]);

        return $candidates;
    }

    /**
     * The spelling a new place is added with: one with Polish characters, else the most frequent, else the first
     * alphabetically.
     *
     * @param  array<string, int>  $counts  spelling => events
     */
    private function chooseSpelling(array $counts): string
    {
        $spellings = array_keys($counts);

        usort($spellings, fn (string $a, string $b): int => [Str::ascii($a) === $a, -$counts[$a], $a] <=> [Str::ascii($b) === $b, -$counts[$b], $b]);

        return $spellings[0];
    }
}
