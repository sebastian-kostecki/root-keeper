<?php

namespace App\Actions\Import;

use App\Enums\EventType;
use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\Source;
use App\Models\Tree;
use App\Support\Import\ImportDecisions;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\ResearchSource;
use App\Support\Import\SourceMatch;
use App\Support\Import\SourceMatches;

class MatchResearchSources
{
    /**
     * List, for each source of the repo that has no record in the tree and no memory, the manual sources that may hold
     * the same scan, without writing.
     *
     * The events are the ones the persons' `zrodla` name: found by the repo id of the event, or through the tree person
     * a repo person was matched to. A candidate is a source of the tree without a repo id that is linked to one of
     * those events, is not a gravestone and has an empty or the same record type and year. Nothing outside the tree
     * takes part.
     */
    public function handle(Tree $tree, ResearchRepoReading $reading, RepoRecordClasses $classes, ImportDecisions $decisions): SourceMatches
    {
        $pending = $this->pending($tree, $reading);

        if ($pending === []) {
            return new SourceMatches;
        }

        $people = $this->plannedPeople($tree, $reading, $classes, $decisions);
        $eventIdsBySource = $this->eventIdsBySource($tree, $reading, $people, $pending);
        $allEventIds = [];

        foreach ($eventIdsBySource as $eventIds) {
            array_push($allEventIds, ...array_keys($eventIds));
        }

        if ($allEventIds === []) {
            return new SourceMatches;
        }

        $manual = $tree->sources()
            ->whereNull('repo_id')
            ->where('kind', '!=', SourceKind::Gravestone)
            ->whereHas('events', fn ($query) => $query->whereIn('events.id', $allEventIds))
            ->with(['events' => fn ($query) => $query->whereIn('events.id', $allEventIds)])
            ->orderBy('id')
            ->get();

        $candidatesBySource = [];

        foreach ($pending as $repoId => $source) {
            foreach ($manual as $candidate) {
                if ($this->compatible($source, $candidate) && $candidate->events->contains(fn (Event $event): bool => isset($eventIdsBySource[$repoId][$event->id]))) {
                    $candidatesBySource[$repoId][] = $candidate;
                }
            }
        }

        if ($candidatesBySource === []) {
            return new SourceMatches;
        }

        $events = $tree->events()->whereIn('id', $allEventIds)->orderBy('id')->get();
        $matches = [];

        foreach ($candidatesBySource as $repoId => $candidates) {
            $cited = array_values($events->filter(fn (Event $event): bool => isset($eventIdsBySource[$repoId][$event->id]))->all());
            $matches[$repoId] = new SourceMatch($pending[$repoId], $candidates, $cited);
        }

        return new SourceMatches($matches);
    }

    /**
     * The sources of the repo without a record and without memory in the tree.
     *
     * @return array<string, ResearchSource>
     */
    private function pending(Tree $tree, ResearchRepoReading $reading): array
    {
        $taken = array_fill_keys([
            ...$tree->sources()->whereNotNull('repo_id')->pluck('repo_id')->all(),
            ...$tree->repoSnapshots()->pluck('repo_id')->all(),
        ], true);
        $pending = [];

        foreach ($reading->sources as $repoId => $source) {
            if (! isset($taken[(string) $repoId])) {
                $pending[(string) $repoId] = $source;
            }
        }

        ksort($pending);

        return $pending;
    }

    /**
     * The tree person of each repo person the import plans: known by its repo id or matched by the author. A person left
     * out or new has no events in the tree.
     *
     * @return array<string, array{id: int, matched: bool}>
     */
    private function plannedPeople(Tree $tree, ResearchRepoReading $reading, RepoRecordClasses $classes, ImportDecisions $decisions): array
    {
        $ids = [];

        foreach ($classes->knownPersons as $repoId => $person) {
            $ids[(string) $repoId] = ['id' => $person->id, 'matched' => false];
        }

        foreach ($decisions->identity->matched as $repoId => $personId) {
            $ids[(string) $repoId] = ['id' => $personId, 'matched' => true];
        }

        foreach ($decisions->identity->skipped as $repoId) {
            unset($ids[$repoId]);
        }

        $inTree = $tree->people()->whereIn('id', array_column($ids, 'id'))->pluck('id')->flip();

        return array_filter(
            array_intersect_key($ids, $reading->persons),
            fn (array $person): bool => $inTree->has($person['id']),
        );
    }

    /**
     * The events of the tree each pending source is cited on.
     *
     * @param  array<string, array{id: int, matched: bool}>  $people
     * @param  array<string, ResearchSource>  $pending
     * @return array<string, array<int, true>> repo id of the source => ids of the events
     */
    private function eventIdsBySource(Tree $tree, ResearchRepoReading $reading, array $people, array $pending): array
    {
        $cited = [];
        $keys = [];
        $personIds = array_column($people, 'id');

        foreach ($reading->persons as $repoId => $person) {
            if (! isset($people[(string) $repoId])) {
                continue;
            }

            foreach ([[RepoKeys::birth((string) $repoId), EventType::Birth, $person->birth], [RepoKeys::death((string) $repoId), EventType::Death, $person->death]] as [$key, $type, $event]) {
                if ($event !== null && $this->cites($event, $pending)) {
                    $cited[] = [$key, $type, [(string) $repoId], $event];
                    $keys[] = $key;
                }
            }
        }

        foreach ($reading->marriages as $marriage) {
            if (isset($people[$marriage->personIds[0]], $people[$marriage->personIds[1]]) && $this->cites($marriage->event, $pending)) {
                $key = RepoKeys::marriage(...$marriage->personIds);
                $cited[] = [$key, EventType::Marriage, $marriage->personIds, $marriage->event];
                $keys[] = $key;
            }
        }

        if ($cited === []) {
            return [];
        }

        $byKey = $tree->events()->whereIn('repo_id', $keys)->get()->keyBy('repo_id');
        $byPerson = $tree->events()
            ->whereIn('type', [EventType::Birth, EventType::Death, EventType::Marriage])
            ->whereHas('participants', fn ($query) => $query->whereIn('people.id', $personIds))
            ->with('participants:id')
            ->get();

        $result = [];

        foreach ($cited as [$key, $type, $repoPersonIds, $event]) {
            $found = [];

            if ($byKey->has($key)) {
                $found[] = $byKey->get($key);
            }

            $matchedPerson = array_filter($repoPersonIds, fn (string $repoId): bool => $people[$repoId]['matched']);

            if ($matchedPerson !== []) {
                $wanted = array_map(fn (string $repoId): int => $people[$repoId]['id'], $repoPersonIds);

                foreach ($byPerson as $candidate) {
                    $participants = $candidate->participants->pluck('id')->all();

                    if ($candidate->type === $type && array_diff($wanted, $participants) === []) {
                        $found[] = $candidate;
                    }
                }
            }

            foreach ($found as $appEvent) {
                foreach ($event->sources as $link) {
                    if (isset($pending[$link->id])) {
                        $result[$link->id][$appEvent->id] = true;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, ResearchSource>  $pending
     */
    private function cites(ResearchEvent $event, array $pending): bool
    {
        foreach ($event->sources as $link) {
            if (isset($pending[$link->id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * An empty record type or year in the tree agrees with anything; a filled one has to equal the repo's.
     */
    private function compatible(ResearchSource $source, Source $candidate): bool
    {
        return ($candidate->record_type === null || $source->recordType === null || $candidate->record_type === $source->recordType)
            && ($candidate->year === null || $source->year === null || $candidate->year === $source->year);
    }
}
