<?php

namespace App\Actions\Import;

use App\Actions\Events\LinkSourceToEvent;
use App\Actions\Events\UpdateEventSourceEntryNumber;
use App\Actions\Sources\ReplaceSourceFile;
use App\Actions\Sources\StoreSource;
use App\Actions\Sources\UpdateSource;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Models\Event;
use App\Models\Source;
use App\Models\Tree;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\CopiedFiles;
use App\Support\Import\ImportPlan;
use App\Support\Import\PlannedPlace;
use App\Support\Import\PlannedRemoval;
use App\Support\Import\PlannedSource;
use App\Support\Import\PlannedSourceMerge;
use App\Support\Import\PlannedSourceUpdate;
use App\Support\Import\RepoKeys;
use App\Support\Import\ResearchSource;
use App\Support\Import\SourceLinks;
use App\Support\Import\SourceWriteResult;
use App\Support\Import\WrittenPlaces;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @phpstan-type Description array{name?: string|null, record_type?: string|null, parish_id?: int|null, cemetery_id?: int|null, buried_person_ids?: list<int>|null, year?: int|null, archive?: string|null, signature?: string|null, url?: string|null, note?: string|null}
 * @phpstan-type LinkMap array<string, array<string, string|null>>
 */
class WriteResearchSources
{
    private const SOURCE_FIELDS = ['birth_sources', 'death_sources', 'marriage_sources'];

    public function __construct(
        private StoreSource $storeSource,
        private UpdateSource $updateSource,
        private ReplaceSourceFile $replaceSourceFile,
        private LinkSourceToEvent $linkSource,
        private UpdateEventSourceEntryNumber $updateEntryNumber,
    ) {}

    /**
     * Write the sources of the plan into the tree: add the new and the restored ones with a copy of their scan, join the
     * manual ones to the repo (the scan of the app stays, the empty fields of the description are filled), and take the
     * approved changes of the known ones, a new scan included. The links to events are written later by {@see link()},
     * when every event of the write exists.
     *
     * A manual or known source always goes through {@see UpdateSource} with its whole current description and the buried
     * people it has, changed only in the fields the plan gives, because that action empties every field it is not given.
     * The parish comes from a token the caller checked under the lock of the tree. Every file the write copies is noted
     * in `$copied`, so that the caller can delete it when the transaction rolls back; the files a replaced scan leaves
     * are deleted after the commit.
     *
     * @throws RuntimeException when a source of the plan is not what the plan saw
     */
    public function handle(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions, WrittenPlaces $written, CopiedFiles $copied): SourceWriteResult
    {
        $created = 0;
        $merged = 0;
        $changed = 0;
        $replaced = 0;

        foreach ([...$plan->newSources, ...$this->restoredSources($plan)] as $planned) {
            $this->create($tree, $planned, $written, $copied);
            $created++;
        }

        foreach ($plan->mergedSources as $merge) {
            $this->merge($tree, $merge, $written);
            $merged++;
        }

        foreach ($plan->sourceUpdates as $update) {
            [$didChange, $didReplace] = $this->update($tree, $update, $approvals, $resolutions, $written, $copied);
            $changed += $didChange ? 1 : 0;
            $replaced += $didReplace ? 1 : 0;
        }

        return new SourceWriteResult($created, $merged, $changed, $replaced);
    }

    /**
     * Delete a source the repo dropped. Its links to events go with it by cascade; the scan and the thumbnail are deleted
     * after the commit, so that a rolled back import keeps them.
     *
     * @return bool whether a source was deleted
     */
    public function remove(Tree $tree, PlannedRemoval $removal): bool
    {
        $source = $tree->sources()->whereKey($removal->recordId)->where('repo_id', $removal->key)->first();

        if ($source === null) {
            return false;
        }

        $files = array_values(array_filter([$source->path, $source->thumbnail_path]));
        $source->delete();

        $this->deleteAfterCommit($files);

        return true;
    }

    /**
     * Check, under the lock of the tree and before anything is written, that the sources, the files of the repo and the
     * events the plan links are as the plan saw them.
     *
     * @throws RuntimeException
     */
    public function verify(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): void
    {
        $ids = [
            ...array_map(fn (PlannedSourceUpdate $update): int => $update->sourceId, $plan->sourceUpdates),
            ...array_map(fn (PlannedSourceMerge $merge): int => $merge->sourceId, $plan->mergedSources),
        ];

        /** @var array<int, string|null> $repoIds */
        $repoIds = $ids === [] ? [] : $tree->sources()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('repo_id', 'id')->all();

        foreach ($plan->sourceUpdates as $update) {
            if (($repoIds[$update->sourceId] ?? null) !== $update->source->id) {
                throw new RuntimeException("Źródło {$update->source->id} (#{$update->sourceId}) zmieniło się po planowaniu; nic nie zostało zapisane.");
            }
        }

        foreach ($plan->mergedSources as $merge) {
            if (! array_key_exists($merge->sourceId, $repoIds) || $repoIds[$merge->sourceId] !== null) {
                throw new RuntimeException("Źródło #{$merge->sourceId}, które plan łączy z {$merge->source->id}, zmieniło się po planowaniu; nic nie zostało zapisane.");
            }
        }

        foreach ($this->scansToCopy($plan, $approvals, $resolutions) as $research) {
            $path = $research->scanAbsolutePath;

            if (! is_file($path) || hash_file('sha256', $path) !== $research->scanHash) {
                throw new RuntimeException("Plik skanu {$research->scanPath} ({$research->id}): repo zmieniło się w trakcie importu; nic nie zostało zapisane.");
            }
        }

        [$ensure, $exact, $merged] = $this->linkPlan($plan, $approvals, $resolutions);
        $keys = array_values(array_unique([...array_keys($ensure), ...array_keys($exact), ...array_keys($merged)]));

        foreach ($keys === [] ? [] : $tree->events()->whereIn('repo_id', $keys)->get() as $event) {
            if (! $event->type->acceptsSources()) {
                throw new RuntimeException("Wydarzenie {$event->repo_id} nie przyjmuje źródeł; nic nie zostało zapisane.");
            }
        }
    }

    /**
     * Write the links of events to the sources of the repo, as the plan and the author's answers give them: the events the
     * write created get all their links, an event with an approved change, a fill or a conflict settled for the repo gets
     * exactly the links of the repo (links to sources with a repo id the repo no longer names go, numbers are corrected),
     * and a source joined or restored in this run is linked as the repo says. Links to manual sources are never touched.
     *
     * @return array{0: int, 1: int} the links added and the links removed
     *
     * @throws RuntimeException when an event or a source the plan links is not in the tree, or the event takes no sources
     */
    public function link(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        [$ensure, $exact, $joined] = $this->linkPlan($plan, $approvals, $resolutions);
        $keys = array_values(array_unique([...array_keys($ensure), ...array_keys($exact), ...array_keys($joined)]));

        if ($keys === []) {
            return [0, 0];
        }

        $events = $tree->events()->whereIn('repo_id', $keys)->get()->keyBy('repo_id');
        $sources = $tree->sources()->whereNotNull('repo_id')->get()->keyBy('repo_id');
        $byId = $sources->keyBy('id');
        $apart = $this->apartIds($plan);

        foreach ($keys as $key) {
            $event = $events->get($key) ?? throw new RuntimeException("Wydarzenie {$key}, do którego import dopina źródło, nie istnieje w drzewie; nic nie zostało zapisane.");

            if (! $event->type->acceptsSources()) {
                throw new RuntimeException("Wydarzenie {$key} nie przyjmuje źródeł; nic nie zostało zapisane.");
            }

            $event->setRelation('tree', $tree);
        }

        $added = 0;
        $removed = 0;

        foreach ($ensure as $key => $links) {
            foreach ($links as $id => $entry) {
                $added += $this->attach($events[$key], $this->sourceFor($sources, $id), $entry);
            }
        }

        foreach ($joined as $key => $links) {
            foreach ($links as $id => $entry) {
                $event = $events[$key];
                $source = $this->sourceFor($sources, $id);
                $current = $this->currentEntries($event);

                if (! array_key_exists($source->id, $current)) {
                    $added += $this->attach($event, $source, $entry);
                } elseif (PlanImport::cleanText($current[$source->id]) === null && $entry !== null) {
                    $this->updateEntryNumber->handle($event, $source, $entry);
                }
            }
        }

        foreach ($exact as $key => $desired) {
            $event = $events[$key];
            $current = $this->currentEntries($event);

            foreach ($desired as $id => $entry) {
                $source = $this->sourceFor($sources, $id);

                if (! array_key_exists($source->id, $current)) {
                    $added += $this->attach($event, $source, $entry);
                } elseif (PlanImport::cleanText($current[$source->id]) !== $entry) {
                    $this->updateEntryNumber->handle($event, $source, $entry);
                }
            }

            $detach = [];

            foreach (array_keys($current) as $sourceId) {
                $repoId = $byId->get($sourceId)?->repo_id;

                if ($repoId === null || array_key_exists($repoId, $desired) || isset($apart[$repoId]) || isset($plan->skippedSources[$repoId])) {
                    continue;
                }

                $detach[] = $sourceId;
            }

            if ($detach !== []) {
                $event->sources()->detach($detach);
                $removed += count($detach);
            }
        }

        return [$added, $removed];
    }

    /**
     * The sources the write copies a scan for: new, restored and joined ones (the repo scan is remembered by its hash) and
     * the known ones whose new scan the author takes.
     *
     * @return list<ResearchSource>
     */
    private function scansToCopy(ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        $scans = [
            ...array_map(fn (PlannedSource $planned): ResearchSource => $planned->source, [...$plan->newSources, ...$this->restoredSources($plan)]),
            ...array_map(fn (PlannedSourceMerge $merge): ResearchSource => $merge->source, $plan->mergedSources),
        ];

        foreach ($plan->sourceUpdates as $update) {
            if (array_key_exists('file', $this->valuesOf($update, $approvals, $resolutions))) {
                $scans[] = $update->source;
            }
        }

        return $scans;
    }

    /**
     * @return list<PlannedSource>
     */
    private function restoredSources(ImportPlan $plan): array
    {
        $sources = [];

        foreach ($plan->restorations as $restoration) {
            if ($restoration->source !== null) {
                $sources[] = $restoration->source;
            }
        }

        return $sources;
    }

    /**
     * The ids of the sources joined or restored in this run: their links come with the source, not with the three values
     * of an event.
     *
     * @return array<string, true>
     */
    private function apartIds(ImportPlan $plan): array
    {
        return array_fill_keys([
            ...array_map(fn (PlannedSourceMerge $merge): string => $merge->source->id, $plan->mergedSources),
            ...array_map(fn (PlannedSource $planned): string => $planned->source->id, $this->restoredSources($plan)),
        ], true);
    }

    /**
     * The links the write puts on events, by repo key of the event and repo id of the source.
     *
     * `ensure` holds the links of the events the write creates and of the restored sources: added when missing, never
     * changed. `exact` holds the events whose links the author takes from the repo: the whole set of links to sources the
     * three values compare. `joined` holds the links of the manual sources joined in this run: added when missing, the
     * number set only when the existing link has none.
     *
     * @return array{0: LinkMap, 1: LinkMap, 2: LinkMap}
     */
    private function linkPlan(ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        $apart = $this->apartIds($plan);
        $ensure = [];
        $exact = [];
        $joined = [];

        $add = function (array &$target, ?string $key, array $links): void {
            if ($key === null || $links === []) {
                return;
            }

            $target[$key] ??= [];

            foreach ($links as $link) {
                $target[$key][$link->id] = PlanImport::cleanText($link->entry);
            }
        };

        foreach ([...$plan->persons, ...array_filter(array_map(fn ($restoration) => $restoration->person, $plan->restorations))] as $planned) {
            $add($ensure, $planned->birthRepoId, $planned->birthSources);
            $add($ensure, $planned->deathRepoId, $planned->deathSources);
        }

        foreach ([...$plan->matched, ...$plan->updates] as $planned) {
            if ($planned->createBirth) {
                $add($ensure, $planned->birthRepoId, $planned->birthSources);
            }

            if ($planned->createDeath) {
                $add($ensure, $planned->deathRepoId, $planned->deathSources);
            }
        }

        foreach ($plan->restorations as $restoration) {
            if ($restoration->event !== null) {
                $add($ensure, $restoration->key, $restoration->sources);
            }

            if ($restoration->source !== null) {
                foreach ($restoration->source->entries as $eventKey => $entry) {
                    $ensure[$eventKey][$restoration->key] = PlanImport::cleanText($entry);
                }
            }
        }

        foreach ($plan->mergedSources as $merge) {
            foreach ($merge->entries as $eventKey => $entry) {
                $joined[$eventKey][$merge->source->id] = PlanImport::cleanText($entry);
            }
        }

        $take = function (string $key, mixed $value) use (&$exact, $apart): void {
            $links = $value instanceof SourceLinks ? $value->links : [];
            $exact[$key] ??= [];

            foreach ($links as $link) {
                if (! isset($apart[$link->id])) {
                    $exact[$key][$link->id] = PlanImport::cleanText($link->entry);
                }
            }
        };
        $eventKeyOf = fn (string $itemKey): string => substr($itemKey, 0, -strlen('/zrodla'));

        foreach ($plan->matched as $match) {
            foreach ($match->fills as $fill) {
                if (in_array($fill->field, self::SOURCE_FIELDS, true)) {
                    $take($this->eventKey($fill->field === 'birth_sources' ? $match->birthRepoId : $match->deathRepoId, $match->person->id), $fill->value);
                }
            }

            foreach ($match->conflicts as $conflict) {
                if (in_array($conflict->field, self::SOURCE_FIELDS, true) && $resolutions->takesRepo($conflict->key)) {
                    $take($eventKeyOf($conflict->key), $conflict->repoValue);
                }
            }
        }

        foreach ($plan->updates as $update) {
            foreach ($update->fills as $fill) {
                if (in_array($fill->field, self::SOURCE_FIELDS, true)) {
                    $take($fill->field === 'birth_sources' ? ($update->birthRepoId ?? RepoKeys::birth($update->person->id)) : ($update->deathRepoId ?? RepoKeys::death($update->person->id)), $fill->value);
                }
            }

            foreach ($update->changes as $change) {
                if (in_array($change->field, self::SOURCE_FIELDS, true) && ! $approvals->rejects($change->key)) {
                    $take($eventKeyOf($change->key), $change->repoValue);
                }
            }

            foreach ($update->conflicts as $conflict) {
                if (in_array($conflict->field, self::SOURCE_FIELDS, true) && $resolutions->takesRepo($conflict->key)) {
                    $take($eventKeyOf($conflict->key), $conflict->repoValue);
                }
            }
        }

        foreach ($plan->marriages as $planned) {
            if ($planned->existingEventId === null) {
                $add($ensure, $planned->repoId, $planned->sources);

                continue;
            }

            if ($planned->sourceFill !== null) {
                $take($planned->repoId, $planned->sourceFill->value);
            }

            if ($planned->sourceConflict !== null && $resolutions->takesRepo($planned->sourceConflict->key)) {
                $take($planned->repoId, $planned->sourceConflict->repoValue);
            }

            if ($planned->sourceChange !== null && ! $approvals->rejects($planned->sourceChange->key)) {
                $take($planned->repoId, $planned->sourceChange->repoValue);
            }
        }

        return [$ensure, $exact, $joined];
    }

    /**
     * @throws RuntimeException
     */
    private function eventKey(?string $key, string $personId): string
    {
        return $key ?? throw new RuntimeException("Plan dopina źródła do wydarzenia osoby {$personId}, które nie ma klucza repo; nic nie zostało zapisane.");
    }

    /**
     * Add a new source or bring a deleted one back: the scan is copied from the repo as a stream, with its thumbnail, and
     * the description, the signature, the parish and the repo id go on the row.
     */
    private function create(Tree $tree, PlannedSource $planned, WrittenPlaces $written, CopiedFiles $copied): Source
    {
        $research = $planned->source;
        $isRecord = $research->kind === SourceKind::MetricalRecord;
        $parishId = null;

        if ($isRecord && $planned->parish !== null) {
            $parishId = $written->parishId($planned->parish) ?? throw new RuntimeException("Parafia {$planned->parish->label} nie należy do drzewa; nic nie zostało zapisane.");
        }

        $source = $this->storeSource->handle($tree, new File($research->scanAbsolutePath), $research->kind, [
            'name' => PlanImport::cleanText($research->name),
            'record_type' => $isRecord ? $research->recordType?->value : null,
            'parish_id' => $parishId,
            'year' => $research->year,
            'archive' => PlanImport::cleanText($research->archive),
            'signature' => PlanImport::cleanText($research->signature),
            'url' => PlanImport::cleanText($research->url),
            'note' => PlanImport::cleanText($research->note),
            'repo_id' => $research->id,
        ]);
        $copied->add($source->path, $source->thumbnail_path);

        return $source;
    }

    /**
     * Join a manual source to the repo: the repo id and the values of the description the source lacks. The scan of the
     * app and every other field stay.
     */
    private function merge(Tree $tree, PlannedSourceMerge $merge, WrittenPlaces $written): void
    {
        $source = $tree->sources()->whereKey($merge->sourceId)->whereNull('repo_id')->first()
            ?? throw new RuntimeException("Źródło #{$merge->sourceId}, które plan łączy z {$merge->source->id}, zmieniło się po planowaniu; nic nie zostało zapisane.");

        if ($merge->fills !== []) {
            $values = [];

            foreach ($merge->fills as $fill) {
                $values[$fill->field] = $fill->value;
            }

            [$kind, $description] = $this->applyValues($source, $values, $written);
            $this->updateSource->handle($source, $kind, $description);
        }

        $source->update(['repo_id' => $merge->source->id]);
    }

    /**
     * Write the approved changes, the fills and the conflicts settled for the repo into a known source.
     *
     * @return array{0: bool, 1: bool} whether the source changed and whether its scan was replaced
     */
    private function update(Tree $tree, PlannedSourceUpdate $update, ChangeApprovals $approvals, ConflictResolutions $resolutions, WrittenPlaces $written, CopiedFiles $copied): array
    {
        $values = $this->valuesOf($update, $approvals, $resolutions);

        if ($values === []) {
            return [false, false];
        }

        $source = $tree->sources()->whereKey($update->sourceId)->where('repo_id', $update->source->id)->first()
            ?? throw new RuntimeException("Źródło {$update->source->id} (#{$update->sourceId}) zmieniło się po planowaniu; nic nie zostało zapisane.");
        $source->setRelation('tree', $tree);
        $replaceFile = array_key_exists('file', $values);
        unset($values['file']);

        if ($values !== []) {
            [$kind, $description] = $this->applyValues($source, $values, $written);
            $this->updateSource->handle($source, $kind, $description);
        }

        if ($replaceFile) {
            $old = $this->replaceSourceFile->handle($source, new File($update->source->scanAbsolutePath));
            $copied->add($source->path, $source->thumbnail_path);
            $this->deleteAfterCommit($old);
        }

        return [true, $replaceFile];
    }

    /**
     * The values of a known source the write takes: the fills, the changes the author did not reject and the conflicts
     * settled for the repo, by field.
     *
     * @return array<string, mixed>
     */
    private function valuesOf(PlannedSourceUpdate $update, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        $values = [];

        foreach ($update->fills as $fill) {
            $values[$fill->field] = $fill->value;
        }

        foreach ($update->changes as $change) {
            if (! $approvals->rejects($change->key)) {
                $values[$change->field] = $change->repoValue;
            }
        }

        foreach ($update->conflicts as $conflict) {
            if ($resolutions->takesRepo($conflict->key)) {
                $values[$conflict->field] = $conflict->repoValue;
            }
        }

        return $values;
    }

    /**
     * The kind and the whole description of a source as it is now, with the given values over it.
     *
     * @param  array<string, mixed>  $values  field of the plan => the repo value, `null` clears the field
     * @return array{0: SourceKind, 1: Description}
     *
     * @throws RuntimeException
     */
    private function applyValues(Source $source, array $values, WrittenPlaces $written): array
    {
        $kind = $source->kind;
        $description = [
            'name' => $source->name,
            'record_type' => $source->record_type?->value,
            'parish_id' => $source->parish_id,
            'cemetery_id' => $source->cemetery_id,
            'buried_person_ids' => array_values(array_map(intval(...), $source->buriedPeople()->pluck('people.id')->all())),
            'year' => $source->year,
            'archive' => $source->archive,
            'signature' => $source->signature,
            'url' => $source->url,
            'note' => $source->note,
        ];

        foreach ($values as $field => $value) {
            match (true) {
                $field === 'kind' && $value instanceof SourceKind => $kind = $value,
                $field === 'record_type' && ($value === null || $value instanceof SourceRecordType) => $description['record_type'] = $value?->value,
                in_array($field, ['name', 'archive', 'signature', 'url', 'note'], true) && ($value === null || is_string($value)) => $description[$field] = $value,
                $field === 'year' && ($value === null || is_string($value)) => $description['year'] = $value === null ? null : (int) $value,
                $field === 'parish' && ($value === null || $value instanceof PlannedPlace) => $description['parish_id'] = $value === null
                    ? null
                    : ($written->parishId($value) ?? throw new RuntimeException("Parafia {$value->label} nie należy do drzewa; nic nie zostało zapisane.")),
                default => throw new RuntimeException("Plan ma niewłaściwą wartość pola {$field} dla źródła #{$source->id}."),
            };
        }

        return [$kind, $description];
    }

    /**
     * @param  Collection<string, Source>  $sources  the sources with a repo id by repo id
     *
     * @throws RuntimeException
     */
    private function sourceFor(Collection $sources, string $id): Source
    {
        return $sources->get($id) ?? throw new RuntimeException("Źródła {$id}, które plan dopina do wydarzenia, nie ma w drzewie; nic nie zostało zapisane.");
    }

    /**
     * The entry numbers of the links of an event by id of the source.
     *
     * @return array<int, string|null>
     */
    private function currentEntries(Event $event): array
    {
        return DB::table('event_source')->where('event_id', $event->id)->pluck('entry_number', 'source_id')->all();
    }

    /**
     * Link a source to an event unless it is linked already.
     *
     * @return int 1 when a link was added
     */
    private function attach(Event $event, Source $source, ?string $entry): int
    {
        if (array_key_exists($source->id, $this->currentEntries($event))) {
            return 0;
        }

        $this->linkSource->handle($event, $source->id, $entry);

        return 1;
    }

    /**
     * Delete files once the transaction that stopped needing them has committed; a rollback keeps them.
     *
     * @param  list<string>  $files
     */
    private function deleteAfterCommit(array $files): void
    {
        if ($files === []) {
            return;
        }

        DB::afterCommit(function () use ($files): void {
            if (! Storage::disk('local')->delete($files)) {
                report(new RuntimeException('Unable to delete source file(s) ['.implode(', ', $files).'].'));
            }
        });
    }
}
