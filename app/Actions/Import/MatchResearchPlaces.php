<?php

namespace App\Actions\Import;

use App\Enums\CemeteryMatchKind;
use App\Enums\PlaceMatchKind;
use App\Enums\RepoKeyKind;
use App\Models\Cemetery;
use App\Models\RepoSnapshot;
use App\Models\Tree;
use App\Support\Import\CemeteryMatches;
use App\Support\Import\CemeteryRequest;
use App\Support\Import\CemeteryVerdict;
use App\Support\Import\ImportDecisions;
use App\Support\Import\PlaceDecisions;
use App\Support\Import\PlaceMatcher;
use App\Support\Import\PlaceMatches;
use App\Support\Import\PlaceName;
use App\Support\Import\PlaceUse;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchRepoReading;

class MatchResearchPlaces
{
    /**
     * Sort the place names of the repo that need a decision into sure, doubtful and new against the localities of the
     * tree, without writing.
     *
     * Only events of the persons the import plans count, minus the ones left deleted, and only when the memory of the
     * last import does not hold the same place text with its recognized id. A name is judged once, whatever its
     * spelling or the number of events, by the rules of {@see PlaceMatcher}.
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

        $matcher = new PlaceMatcher($tree->localities()->orderBy('id')->get(), $tree->localityAliases()->orderBy('id')->get());
        $verdicts = [];

        foreach ($names as $key => $spellings) {
            $verdicts[$key] = $matcher->verdict((string) $key, $spellings);
        }

        $requests = [];

        foreach ($burials as $requestKey => $burial) {
            $requests[$requestKey] = new CemeteryRequest(
                $burial['localityKey'],
                $burial['cemeteryKey'],
                $burial['spellings'] === [] ? null : PlaceMatcher::chooseSpelling($burial['spellings']),
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
     * The events of the planned persons whose place the import may set: birth, death, burial and weddings. A person the
     * author brings back after a deletion in the app is planned too.
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

        foreach ($classes->deletedInApp as $entry) {
            if ($entry->kind === RepoKeyKind::Person && ($decisions->deletions[$entry->key] ?? null) === ImportDecisions::RESTORE) {
                $planned[$entry->key] = true;
            }
        }

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
}
