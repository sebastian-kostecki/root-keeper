<?php

namespace App\Actions\Import;

use App\Enums\EventType;
use App\Enums\RepoKeyKind;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\RepoSnapshot;
use App\Models\Source;
use App\Models\Tree;
use App\Support\Import\RemovalImpact;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoRecord;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClassifyRepoRecords
{
    private const KNOWN = 'known';

    private const NEW = 'new';

    private const DELETED = 'deleted';

    private const REMOVED = 'removed';

    private const GONE = 'gone';

    /**
     * Sort every key of the repo, of the tree and of the memory of the last import into one group, without writing.
     *
     * A key is in the repo when the reading holds it, in the tree when a record carries it as `repo_id` and in the
     * memory when the last import left a snapshot for it. Only records of the tree are read. A key that names a person
     * whose repo entry was skipped is left out of every group, because that file may be the one that holds it, and so is
     * the key of a wedding the reading dropped with a warning (the repo still has it, only unreadable); when an entry
     * was skipped without a readable id, nothing is removed or forgotten and the removal candidates are held back.
     *
     * A source (`ZR-0012`) names no person, so it is decided apart: known, new, deleted in the app, kept deleted or removed
     * in the repo. A source entry that was skipped is not one removed in the repo. An entry skipped without a readable id
     * (or a whole file of `zrodla/` that could not be read, or a repo without `zrodla/`) holds back only the removal of
     * sources, never of persons, and
     * a skipped person without a readable id never holds back sources.
     *
     * The other keys of a person that is removed in the repo, deleted in the app or kept deleted are decided with the
     * person, so they are members of its entry instead of entries of their own.
     */
    public function handle(Tree $tree, ResearchRepoReading $reading): RepoRecordClasses
    {
        $inRepo = $this->repoKeys($reading);
        $records = $this->records($tree);
        $snapshots = $tree->repoSnapshots()->get()->keyBy('repo_id')->all();
        $skippedIds = array_flip($reading->skippedRepoIds());
        $holdBack = $reading->hasSkippedWithoutId();
        $holdBackSources = $reading->holdsBackSourceRemovals();
        $skippedSources = array_flip($reading->skippedSourceIds());
        $dropped = array_flip($reading->droppedMarriageKeys);

        $keys = array_map(strval(...), array_unique([...array_keys($inRepo), ...array_keys($records), ...array_keys($snapshots)]));
        sort($keys);
        $keys = array_values(array_filter(
            $keys,
            fn (string $key): bool => ! isset($dropped[$key])
                && ! isset($skippedSources[$key])
                && array_intersect_key($skippedIds, array_flip(RepoKeys::personIdsOf($key))) === []
                && ! $this->isBurialWithoutRecord($key, $inRepo, $records, $snapshots),
        ));

        $states = [];
        $others = [];

        foreach ($keys as $key) {
            if (RepoKeys::kindOf($key) === RepoKeyKind::Person) {
                $states[$key] = $this->stateOf(isset($inRepo[$key]), isset($records[$key]), isset($snapshots[$key]));
            } else {
                $others[] = $key;
            }
        }

        [$members, $loose] = $this->groupMembers($others, $states, $inRepo, $records, $snapshots);

        $known = [];
        $new = [];
        $entries = ['deleted' => [], 'removed' => []];
        $kept = [];
        $goneBoth = [];
        $knownSources = [];
        $newSources = [];
        $goneSources = [];

        foreach ($states as $id => $state) {
            switch ($state) {
                case self::KNOWN:
                    if ($records[$id] instanceof Person) {
                        $known[$id] = $records[$id];
                    }

                    break;
                case self::NEW:
                    $new[] = $id;
                    break;
                case self::GONE:
                    $goneBoth[] = $id;
                    break;
                case self::DELETED:
                    $this->classifyDeleted($id, $members[$id] ?? [], $reading, $records, $snapshots, $entries['deleted'], $kept);
                    break;
                case self::REMOVED:
                    $entries['removed'][$id] = $this->removedEntry($tree, $id, $members[$id] ?? [], $records, $snapshots);
                    break;
            }
        }

        foreach ($loose as $key) {
            $isSource = RepoKeys::kindOf($key) === RepoKeyKind::Source;

            switch ($this->stateOf(isset($inRepo[$key]), isset($records[$key]), isset($snapshots[$key]))) {
                case self::KNOWN:
                    if ($isSource && $records[$key] instanceof Source) {
                        $knownSources[$key] = $records[$key];
                    }

                    break;
                case self::NEW:
                    if ($isSource) {
                        $newSources[] = $key;
                    }

                    break;
                case self::GONE:
                    if ($isSource) {
                        $goneSources[] = $key;
                    } else {
                        $goneBoth[] = $key;
                    }

                    break;
                case self::DELETED:
                    $this->classifyDeleted($key, [], $reading, $records, $snapshots, $entries['deleted'], $kept);
                    break;
                case self::REMOVED:
                    $entries['removed'][$key] = $this->removedEntry($tree, $key, [], $records, $snapshots);
                    break;
            }
        }

        ksort($entries['deleted']);
        ksort($entries['removed']);
        ksort($known);
        ksort($knownSources);
        sort($new);
        sort($newSources);
        sort($kept);
        sort($goneBoth);
        sort($goneSources);

        $removed = [];
        $held = [];
        $gone = [...($holdBack ? [] : $goneBoth), ...($holdBackSources ? [] : $goneSources)];
        sort($gone);

        foreach ($entries['removed'] as $entry) {
            if ($entry->kind === RepoKeyKind::Source ? $holdBackSources : $holdBack) {
                $held[] = $entry;
            } else {
                $removed[] = $entry;
            }
        }

        return new RepoRecordClasses(
            knownPersons: $known,
            newPersonIds: $new,
            deletedInApp: array_values($entries['deleted']),
            keptDeleted: $kept,
            removedInRepo: $removed,
            goneBoth: $gone,
            unassessable: $held,
            knownSources: $knownSources,
            newSourceIds: $newSources,
        );
    }

    /**
     * Whether the key is a burial the repo has, whose cemetery the last import did not recognize: the memory holds it
     * without a record (`cmentarz_id` is empty) because the app only keeps a burial with a cemetery. It is not a
     * burial deleted in the app.
     *
     * @param  array<string, true>  $inRepo
     * @param  array<string, Model>  $records
     * @param  array<string, RepoSnapshot>  $snapshots
     */
    private function isBurialWithoutRecord(string $key, array $inRepo, array $records, array $snapshots): bool
    {
        if (! isset($inRepo[$key]) || isset($records[$key]) || ! isset($snapshots[$key]) || RepoKeys::kindOf($key) !== RepoKeyKind::Burial) {
            return false;
        }

        $values = $snapshots[$key]->values;

        return array_key_exists('cmentarz_id', $values) && $values['cmentarz_id'] === null;
    }

    /**
     * Every key the reading holds: each person, its birth, death and burial when the block names them, each parent-child pair
     * whose two ends are in the reading, each wedding and each source with a scan.
     *
     * @return array<string, true>
     */
    private function repoKeys(ResearchRepoReading $reading): array
    {
        $keys = [];

        foreach ($reading->persons as $person) {
            $keys[RepoKeys::person($person->id)] = true;

            if ($person->birth !== null) {
                $keys[RepoKeys::birth($person->id)] = true;
            }

            if ($person->death !== null) {
                $keys[RepoKeys::death($person->id)] = true;
            }

            if ($person->burial !== null) {
                $keys[RepoKeys::burial($person->id)] = true;
            }

            foreach ([$person->fatherId, $person->motherId] as $parentId) {
                if ($parentId !== null && isset($reading->persons[$parentId])) {
                    $keys[RepoKeys::relation($parentId, $person->id)] = true;
                }
            }
        }

        foreach ($reading->marriages as $marriage) {
            $keys[RepoKeys::marriage($marriage->personIds[0], $marriage->personIds[1])] = true;
        }

        foreach (array_keys($reading->sources) as $sourceId) {
            $keys[RepoKeys::source((string) $sourceId)] = true;
        }

        return $keys;
    }

    /**
     * The records of the tree that carry a repo key, by key. A record whose key has another shape than its table is left out.
     *
     * @return array<string, Model>
     */
    private function records(Tree $tree): array
    {
        $records = [];

        foreach ($tree->people()->whereNotNull('repo_id')->get() as $person) {
            if (RepoKeys::kindOf((string) $person->repo_id) === RepoKeyKind::Person) {
                $records[(string) $person->repo_id] = $person;
            }
        }

        foreach ($tree->events()->whereNotNull('repo_id')->get() as $event) {
            if (in_array(RepoKeys::kindOf((string) $event->repo_id), [RepoKeyKind::Birth, RepoKeyKind::Death, RepoKeyKind::Burial, RepoKeyKind::Marriage], true)) {
                $records[(string) $event->repo_id] = $event;
            }
        }

        foreach ($tree->parentChildRelations()->whereNotNull('repo_id')->get() as $relation) {
            if (RepoKeys::kindOf((string) $relation->repo_id) === RepoKeyKind::Relation) {
                $records[(string) $relation->repo_id] = $relation;
            }
        }

        foreach ($tree->sources()->whereNotNull('repo_id')->get() as $source) {
            if (RepoKeys::kindOf((string) $source->repo_id) === RepoKeyKind::Source) {
                $records[(string) $source->repo_id] = $source;
            }
        }

        return $records;
    }

    private function stateOf(bool $inRepo, bool $hasRecord, bool $hasSnapshot): string
    {
        return match (true) {
            $inRepo && $hasRecord => self::KNOWN,
            $inRepo && ! $hasSnapshot => self::NEW,
            $inRepo => self::DELETED,
            $hasRecord => self::REMOVED,
            default => self::GONE,
        };
    }

    /**
     * Attach every key that is not a person to the first person it names that is removed in the repo or deleted in the app.
     *
     * @param  list<string>  $keys
     * @param  array<string, string>  $states  by repo id of a person
     * @param  array<string, true>  $inRepo
     * @param  array<string, Model>  $records
     * @param  array<string, RepoSnapshot>  $snapshots
     * @return array{0: array<string, list<string>>, 1: list<string>} the member keys by owner, then the keys that stand alone
     */
    private function groupMembers(array $keys, array $states, array $inRepo, array $records, array $snapshots): array
    {
        $members = [];
        $loose = [];

        foreach ($keys as $key) {
            $owner = null;

            foreach (RepoKeys::personIdsOf($key) as $id) {
                $state = $states[$id] ?? null;

                if (($state === self::REMOVED && isset($records[$key]))
                    || ($state === self::DELETED && isset($inRepo[$key]) && ! isset($records[$key]) && isset($snapshots[$key]))) {
                    $owner = $id;

                    break;
                }
            }

            if ($owner === null) {
                $loose[] = $key;
            } else {
                $members[$owner][] = $key;
            }
        }

        return [$members, $loose];
    }

    /**
     * A key the repo has and the tree lost goes to the author unless they already left it deleted and the repo has not
     * changed it, nor a member of it, since.
     *
     * @param  list<string>  $memberKeys
     * @param  array<string, Model>  $records
     * @param  array<string, RepoSnapshot>  $snapshots
     * @param  array<string, RepoRecord>  $asked
     * @param  list<string>  $kept
     */
    private function classifyDeleted(string $key, array $memberKeys, ResearchRepoReading $reading, array $records, array $snapshots, array &$asked, array &$kept): void
    {
        $ask = ! $snapshots[$key]->deletion_kept;

        foreach ([$key, ...$memberKeys] as $each) {
            $ask = $ask || $this->differsFromMemory($each, $snapshots[$each], $reading);
        }

        if (! $ask) {
            $kept[] = $key;

            return;
        }

        $asked[$key] = $this->record($key, $records, $snapshots, members: array_map(
            fn (string $member): RepoRecord => $this->record($member, $records, $snapshots),
            $memberKeys,
        ));
    }

    /**
     * Whether the repo values of a key are other than the ones the last import saw.
     */
    private function differsFromMemory(string $key, RepoSnapshot $snapshot, ResearchRepoReading $reading): bool
    {
        $repo = RepoValues::forKey($key, $reading) ?? [];

        foreach (array_unique([...array_keys($repo), ...array_keys($snapshot->values)]) as $field) {
            if (str_ends_with((string) $field, '_id')) {
                continue;
            }

            if (($repo[$field] ?? null) !== ($snapshot->values[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $memberKeys
     * @param  array<string, Model>  $records
     * @param  array<string, RepoSnapshot>  $snapshots
     */
    private function removedEntry(Tree $tree, string $key, array $memberKeys, array $records, array $snapshots): RepoRecord
    {
        return $this->record(
            $key,
            $records,
            $snapshots,
            $this->impactOf($tree, $key, $records[$key]),
            array_map(fn (string $member): RepoRecord => $this->record($member, $records, $snapshots), $memberKeys),
        );
    }

    /**
     * @param  array<string, Model>  $records
     * @param  array<string, RepoSnapshot>  $snapshots
     * @param  list<RepoRecord>  $members
     */
    private function record(string $key, array $records, array $snapshots, ?RemovalImpact $impact = null, array $members = []): RepoRecord
    {
        return new RepoRecord($key, RepoKeys::kindOf($key), $records[$key] ?? null, $snapshots[$key] ?? null, RepoKeys::personIdsOf($key), $impact, $members);
    }

    /**
     * What a removal takes with it. A birth is only detached, so it has no impact; a burial takes nothing else with it; a
     * source takes its links to events and its scan file.
     */
    public function impactOf(Tree $tree, string $key, Model $record): ?RemovalImpact
    {
        return match (true) {
            $record instanceof Person => $this->personImpact($tree, $record),
            $record instanceof ParentChildRelation => new RemovalImpact,
            $record instanceof Source => new RemovalImpact(sourceLinks: DB::table('event_source')->where('source_id', $record->id)->count(), scan: true),
            $record instanceof Event && RepoKeys::kindOf($key) === RepoKeyKind::Death => $this->deathImpact($tree, $record),
            $record instanceof Event && RepoKeys::kindOf($key) === RepoKeyKind::Burial => new RemovalImpact,
            $record instanceof Event && RepoKeys::kindOf($key) === RepoKeyKind::Marriage => new RemovalImpact(sourceLinks: $this->sourceLinks([$record->id])),
            default => null,
        };
    }

    /**
     * What `DeletePerson` removes: every marriage of the person, every event with no other participant, and what hangs off them.
     */
    private function personImpact(Tree $tree, Person $person): RemovalImpact
    {
        $events = $tree->events()
            ->whereHas('participants', fn ($query) => $query->where('people.id', $person->id))
            ->withCount('participants')
            ->get();

        $removed = $events->filter(fn (Event $event): bool => $event->type === EventType::Marriage || $event->participants_count === 1);
        $others = $removed->reject(fn (Event $event): bool => in_array($event->type, [EventType::Marriage, EventType::Burial], true));

        return new RemovalImpact(
            photo: $person->photo_path !== null,
            sourceLinks: $this->sourceLinks($removed->pluck('id')->all()),
            burial: $removed->contains(fn (Event $event): bool => $event->type === EventType::Burial),
            otherEvents: $others->count(),
            residences: $person->residences()->count(),
            relations: $tree->parentChildRelations()
                ->where(fn ($query) => $query->where('parent_id', $person->id)->orWhere('child_id', $person->id))
                ->count(),
            marriages: $events->filter(fn (Event $event): bool => $event->type === EventType::Marriage)->count(),
            gravestones: DB::table('buried_people')->where('person_id', $person->id)->count(),
            proband: $tree->root_person_id === $person->id,
        );
    }

    /**
     * The sources of the death, and the burial that goes with it when the person is no longer dead.
     */
    private function deathImpact(Tree $tree, Event $death): RemovalImpact
    {
        $personIds = $death->participants()->pluck('people.id')->all();
        $burials = $tree->events()
            ->where('type', EventType::Burial)
            ->whereHas('participants', fn ($query) => $query->whereIn('people.id', $personIds))
            ->pluck('id')
            ->all();

        return new RemovalImpact(sourceLinks: $this->sourceLinks([$death->id]), burial: $burials !== []);
    }

    /**
     * @param  array<int, int>  $eventIds  ids of events read through the tree
     */
    private function sourceLinks(array $eventIds): int
    {
        return $eventIds === [] ? 0 : DB::table('event_source')->whereIn('event_id', $eventIds)->count();
    }
}
