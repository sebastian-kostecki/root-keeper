<?php

namespace App\Actions\Import;

use App\Enums\EventType;
use App\Enums\RepoKeyKind;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\RepoSnapshot;
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
        $dropped = array_flip($reading->droppedMarriageKeys);

        $keys = array_map(strval(...), array_unique([...array_keys($inRepo), ...array_keys($records), ...array_keys($snapshots)]));
        sort($keys);
        $keys = array_values(array_filter(
            $keys,
            fn (string $key): bool => ! isset($dropped[$key]) && array_intersect_key($skippedIds, array_flip(RepoKeys::personIdsOf($key))) === [],
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
            switch ($this->stateOf(isset($inRepo[$key]), isset($records[$key]), isset($snapshots[$key]))) {
                case self::GONE:
                    $goneBoth[] = $key;
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
        sort($new);
        sort($kept);
        sort($goneBoth);

        return new RepoRecordClasses(
            knownPersons: $known,
            newPersonIds: $new,
            deletedInApp: array_values($entries['deleted']),
            keptDeleted: $kept,
            removedInRepo: $holdBack ? [] : array_values($entries['removed']),
            goneBoth: $holdBack ? [] : $goneBoth,
            unassessable: $holdBack ? array_values($entries['removed']) : [],
        );
    }

    /**
     * Every key the reading holds: each person, its birth and death when the block names them, each parent-child pair
     * whose two ends are in the reading and each wedding.
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

            foreach ([$person->fatherId, $person->motherId] as $parentId) {
                if ($parentId !== null && isset($reading->persons[$parentId])) {
                    $keys[RepoKeys::relation($parentId, $person->id)] = true;
                }
            }
        }

        foreach ($reading->marriages as $marriage) {
            $keys[RepoKeys::marriage($marriage->personIds[0], $marriage->personIds[1])] = true;
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
            if (in_array(RepoKeys::kindOf((string) $event->repo_id), [RepoKeyKind::Birth, RepoKeyKind::Death, RepoKeyKind::Marriage], true)) {
                $records[(string) $event->repo_id] = $event;
            }
        }

        foreach ($tree->parentChildRelations()->whereNotNull('repo_id')->get() as $relation) {
            if (RepoKeys::kindOf((string) $relation->repo_id) === RepoKeyKind::Relation) {
                $records[(string) $relation->repo_id] = $relation;
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
     * What a removal takes with it. A birth is only detached, so it has no impact.
     */
    private function impactOf(Tree $tree, string $key, Model $record): ?RemovalImpact
    {
        return match (true) {
            $record instanceof Person => $this->personImpact($tree, $record),
            $record instanceof ParentChildRelation => new RemovalImpact,
            $record instanceof Event && RepoKeys::kindOf($key) === RepoKeyKind::Death => $this->deathImpact($tree, $record),
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
