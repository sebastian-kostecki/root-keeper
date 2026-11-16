<?php

namespace App\Actions\Import;

use App\Enums\RepoKeyKind;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Enums\ThreeWayOutcome;
use App\Models\Event;
use App\Models\EventSource;
use App\Models\RepoSnapshot;
use App\Models\Source;
use App\Models\Tree;
use App\Support\Import\ImportDecisions;
use App\Support\Import\PlaceResolver;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedFill;
use App\Support\Import\PlannedRestoration;
use App\Support\Import\PlannedSource;
use App\Support\Import\PlannedSourceMerge;
use App\Support\Import\PlannedSourceUpdate;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\ResearchSource;
use App\Support\Import\ResearchSourceLink;
use App\Support\Import\ResolvedPlace;
use App\Support\Import\SourceDecisions;
use App\Support\Import\SourceLinks;
use App\Support\Import\SourcePlan;
use App\Support\Import\ThreeWayValue;
use App\Support\Import\ValueConflict;

/**
 * @phpstan-type Item PlannedChange|PlannedFill|ValueConflict
 */
class PlanResearchSources
{
    /**
     * Decide what an import does with the sources of the repo, without writing anything.
     *
     * A source of the repo with no record and no memory is added, or joined to the manual source the author chose; one the
     * author deleted in the app comes back or stays deleted as decided; a known one is compared field by field with the
     * three values (the repo now, the repo at the last import, the app now), the parish by token and the scan by its hash.
     * The ids of this run that must not take part in the links of events (skipped, left deleted, detached, removed) are
     * collected here, so that the events are planned against the same sets. Every read goes through the tree.
     *
     * @param  array<string, RepoSnapshot>  $memory  the memory of the tree by repo key
     * @param  ImportDecisions  $import  the answers about the records the repo dropped and the ones deleted in the app
     */
    public function handle(Tree $tree, ResearchRepoReading $reading, RepoRecordClasses $classes, SourceDecisions $decisions, PlaceResolver $places, array $memory, ImportDecisions $import): SourcePlan
    {
        $skipped = array_fill_keys($reading->skippedSourceIds(), true);
        $unavailable = $skipped;
        $restored = [];

        foreach ($classes->keptDeleted as $key) {
            if (RepoKeys::kindOf($key) === RepoKeyKind::Source) {
                $unavailable[$key] = true;
            }
        }

        foreach ($classes->removedInRepo as $entry) {
            if ($entry->kind === RepoKeyKind::Source) {
                $unavailable[$entry->key] = true;
            }
        }

        foreach ($classes->deletedInApp as $entry) {
            if ($entry->kind !== RepoKeyKind::Source) {
                continue;
            }

            if (($import->deletions[$entry->key] ?? null) === ImportDecisions::RESTORE) {
                $restored[$entry->key] = $entry;
            } else {
                $unavailable[$entry->key] = true;
            }
        }

        $available = array_fill_keys([...array_keys($classes->knownSources), ...$classes->newSourceIds, ...array_keys($restored)], true);
        $apart = array_fill_keys([...array_keys($decisions->merges), ...array_keys($restored)], true);

        $manual = $tree->sources()->whereNull('repo_id')->whereIn('id', array_values($decisions->merges))->get()->keyBy('id');
        $snapshots = [];
        $new = [];
        $merges = [];

        foreach ($classes->newSourceIds as $id) {
            $research = $reading->sources[$id] ?? null;

            if ($research === null) {
                continue;
            }

            $resolved = $places->parish($research->parish, null);
            $snapshots[$id] = $this->memoryOf($research, $resolved);
            $target = isset($decisions->merges[$id]) ? $manual->get($decisions->merges[$id]) : null;

            if ($target === null) {
                $new[] = new PlannedSource($research, $resolved?->place);
            } else {
                $merges[] = new PlannedSourceMerge($research, $target->id, $this->mergeFills($research, $target, $resolved), $resolved?->place);
            }
        }

        $restorations = [];

        foreach ($restored as $id => $entry) {
            $research = $reading->sources[$id] ?? null;

            if ($research === null) {
                continue;
            }

            $resolved = $places->parish($research->parish, $this->rememberedParish($research, $memory[$id] ?? $entry->snapshot));
            $snapshots[$id] = $this->memoryOf($research, $resolved);
            $restorations[] = new PlannedRestoration(
                $id,
                RepoKeyKind::Source,
                PlannedSourceUpdate::labelOf($research),
                source: new PlannedSource($research, $resolved?->place),
            );
        }

        $updates = [];
        $changes = [];
        $conflicts = [];
        $kept = 0;

        foreach ($classes->knownSources as $id => $source) {
            $research = $reading->sources[$id] ?? null;

            if ($research === null) {
                continue;
            }

            $compared = $this->compareKnown($research, $source, $memory[$id] ?? null, $places);
            $snapshots[$id] = $compared['values'];
            $kept += $compared['kept'];
            array_push($changes, ...$compared['changes']);
            array_push($conflicts, ...$compared['conflicts']);

            if ($compared['changes'] !== [] || $compared['fills'] !== [] || $compared['conflicts'] !== []) {
                $updates[] = new PlannedSourceUpdate($research, $source->id, $compared['changes'], $compared['fills'], $compared['conflicts'], $compared['kept']);
            }
        }

        return new SourcePlan($new, $merges, $updates, $restorations, $changes, $conflicts, $snapshots, $kept, $available, $unavailable, $skipped, $apart);
    }

    /**
     * Compare the links an event of the repo names with the links the event has in the tree, as one value: the sources
     * the repo confirms the event with now (R), the ones it named at the last import (S, the field `zrodla` of the
     * memory) and the ones of the event that carry a repo id (A). A link counts only for a source that has a record after
     * the import and is not left out; a source joined or restored in this run is written with its own entry and stays out
     * of the three values. Links to sources without a repo id never take part, so they cause no conflict and never go.
     *
     * An event whose R or S names a source the reading skipped is not compared: it makes no item and keeps its memory.
     *
     * @param  ResearchEvent  $repo  the birth, the death or the wedding of the repo; a burial takes no sources
     * @param  Event|null  $event  the event of the tree with its sources loaded; `null` for an event the plan creates
     * @param  string  $key  repo key of the event
     * @param  string  $field  `birth_sources`, `death_sources` or `marriage_sources`
     * @return array{item: Item|null, kept: bool, values: array<string, string|null>, links: list<ResearchSourceLink>} the item to plan, whether a change of the app was left alone, the `zrodla` entry of the memory (empty for none) and the links the event ends with, to write when it is created
     */
    public function eventLinks(SourcePlan $plan, ResearchEvent $repo, ?Event $event, ?RepoSnapshot $snapshot, string $key, string $field, string $label, string $who): array
    {
        $links = array_values(array_filter($repo->sources, fn (ResearchSourceLink $link): bool => $plan->linkable($link->id)));
        $remembered = $snapshot !== null && array_key_exists('zrodla', $snapshot->values);
        $last = $remembered && is_string($snapshot->values['zrodla']) ? $snapshot->values['zrodla'] : null;

        if ($event !== null) {
            foreach ([...array_map(fn (ResearchSourceLink $link): string => $link->id, $repo->sources), ...SourceLinks::idsIn($last)] as $id) {
                if ($plan->isSkipped($id)) {
                    return ['item' => null, 'kept' => false, 'values' => $remembered ? ['zrodla' => $last] : [], 'links' => $links];
                }
            }
        }

        $values = $repo->sources !== [] || $remembered ? ['zrodla' => (new SourceLinks($links))->text()] : [];

        if ($event === null) {
            return ['item' => null, 'kept' => false, 'values' => $values, 'links' => $links];
        }

        $repoLinks = new SourceLinks(array_values(array_filter($links, fn (ResearchSourceLink $link): bool => $plan->comparable($link->id))));
        $lastLinks = new SourceLinks(array_map(
            fn (string $id): ResearchSourceLink => new ResearchSourceLink($id, $this->entryIn($last, $id)),
            array_values(array_filter(SourceLinks::idsIn($last), fn (string $id): bool => $plan->comparable($id))),
        ));
        $appLinks = new SourceLinks($this->appLinks($event, $plan));
        $outcome = ThreeWayValue::compare($repoLinks->text(), $remembered, $remembered ? $lastLinks->text() : null, $appLinks->text());
        $item = PlanImport::itemFor($outcome, $field, $label, "{$key}/zrodla", $who, $appLinks->label(), $repoLinks->label(), $lastLinks->label(), $remembered, new SourceLinks($links));

        return ['item' => $item, 'kept' => $outcome === ThreeWayOutcome::KeptManual, 'values' => $values, 'links' => $links];
    }

    /**
     * The links of an event of the tree to sources that carry a repo id and take part in the comparison.
     *
     * @return list<ResearchSourceLink>
     */
    private function appLinks(Event $event, SourcePlan $plan): array
    {
        $links = [];

        foreach ($event->sources as $source) {
            if ($source->repo_id !== null && $plan->comparable((string) $source->repo_id)) {
                $pivot = $source->getRelation('pivot');
                $links[] = new ResearchSourceLink((string) $source->repo_id, $pivot instanceof EventSource ? $pivot->entry_number : null);
            }
        }

        return $links;
    }

    /**
     * The entry number a canonical `zrodla` text holds for a source.
     */
    private function entryIn(?string $text, string $id): ?string
    {
        foreach (explode(';', (string) $text) as $part) {
            if (str_starts_with($part, "{$id}=")) {
                $entry = substr($part, strlen($id) + 1);

                return $entry === '' ? null : $entry;
            }
        }

        return null;
    }

    /**
     * The memory of a source: what the repo holds and the token of the parish it was recognized as.
     *
     * @return array<string, string|null>
     */
    private function memoryOf(ResearchSource $research, ?ResolvedPlace $parish): array
    {
        return [...RepoValues::source($research), 'parafia_id' => $parish?->token];
    }

    /**
     * The parish token the last import recognized the parish text as, when the repo still has the same text.
     */
    private function rememberedParish(ResearchSource $research, ?RepoSnapshot $snapshot): ?string
    {
        $values = $snapshot === null ? [] : $snapshot->values;
        $same = ($values['parafia'] ?? null) === RepoValues::source($research)['parafia'];

        return $same && is_string($values['parafia_id'] ?? null) ? $values['parafia_id'] : null;
    }

    /**
     * The values of the description the repo gives to the empty fields of the manual source it is joined to. The record
     * type and the parish belong to a metrical record only.
     *
     * @return list<PlannedFill>
     */
    private function mergeFills(ResearchSource $research, Source $manual, ?ResolvedPlace $parish): array
    {
        $isRecord = $manual->kind === SourceKind::MetricalRecord;
        $fills = [];

        foreach ([
            ['record_type', 'Typ aktu', $isRecord && $manual->record_type === null ? $research->recordType : null],
            ['name', 'Nazwa', PlanImport::cleanText($manual->name) === null ? PlanImport::cleanText($research->name) : null],
            ['year', 'Rok', $manual->year === null && $research->year !== null ? (string) $research->year : null],
            ['parish', 'Parafia', $isRecord && $manual->parish_id === null ? $parish?->place : null],
            ['archive', 'Archiwum', PlanImport::cleanText($manual->archive) === null ? PlanImport::cleanText($research->archive) : null],
            ['signature', 'Sygnatura', PlanImport::cleanText($manual->signature) === null ? PlanImport::cleanText($research->signature) : null],
            ['url', 'Link', PlanImport::cleanText($manual->url) === null ? PlanImport::cleanText($research->url) : null],
            ['note', 'Notatka', PlanImport::cleanText($manual->note) === null ? PlanImport::cleanText($research->note) : null],
        ] as [$field, $label, $value]) {
            if ($value !== null) {
                $fills[] = new PlannedFill($field, $value, $label);
            }
        }

        return $fills;
    }

    /**
     * Compare a source of the tree an earlier import took with the repo: each field of the description, the parish by
     * token and the scan by hash.
     *
     * @return array{values: array<string, string|null>, changes: list<PlannedChange>, fills: list<PlannedFill>, conflicts: list<ValueConflict>, kept: int}
     */
    private function compareKnown(ResearchSource $research, Source $source, ?RepoSnapshot $snapshot, PlaceResolver $places): array
    {
        $who = PlannedSourceUpdate::labelOf($research);
        $id = $research->id;
        $items = [];
        $kept = 0;

        foreach ([
            ['kind', 'Rodzaj', 'rodzaj', $research->kind->value, $source->kind->value, SourceKind::class, $research->kind],
            ['record_type', 'Typ aktu', 'typ', $research->recordType?->value, $source->record_type?->value, SourceRecordType::class, $research->recordType],
            ['name', 'Nazwa', 'nazwa', PlanImport::cleanText($research->name), PlanImport::cleanText($source->name), null, PlanImport::cleanText($research->name)],
            ['year', 'Rok', 'rok', $research->year === null ? null : (string) $research->year, $source->year === null ? null : (string) $source->year, null, $research->year === null ? null : (string) $research->year],
            ['archive', 'Archiwum', 'archiwum', PlanImport::cleanText($research->archive), PlanImport::cleanText($source->archive), null, PlanImport::cleanText($research->archive)],
            ['signature', 'Sygnatura', 'sygnatura', PlanImport::cleanText($research->signature), PlanImport::cleanText($source->signature), null, PlanImport::cleanText($research->signature)],
            ['url', 'Link', 'link', PlanImport::cleanText($research->url), PlanImport::cleanText($source->url), null, PlanImport::cleanText($research->url)],
            ['note', 'Notatka', 'notatka', PlanImport::cleanText($research->note), PlanImport::cleanText($source->note), null, PlanImport::cleanText($research->note)],
        ] as [$field, $label, $name, $repo, $app, $enum, $repoValue]) {
            [$hasLast, $last] = PlanImport::lastOf($snapshot, $name);
            $outcome = ThreeWayValue::compare($repo, $hasLast, $last, $app);
            $kept += $outcome === ThreeWayOutcome::KeptManual ? 1 : 0;
            $show = fn (?string $value): ?string => $value === null || $enum === null ? $value : ($enum::tryFrom($value)?->label() ?? $value);
            $item = PlanImport::itemFor($outcome, $field, $label, "{$id}/{$name}", $who, $show($app), $show($repo), $show($last), $hasLast, $repoValue);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        $resolved = $places->parish($research->parish, $this->rememberedParish($research, $snapshot));
        $values = $this->memoryOf($research, $resolved);

        if ($resolved !== null || $research->parish === null) {
            [$hasLast, $last] = PlanImport::lastOf($snapshot, 'parafia_id');

            if ($hasLast && $last !== null && ! $places->tokenExists($last)) {
                [$hasLast, $last] = [false, null];
            }

            $app = $source->parish_id === null ? null : 'P:'.$source->parish_id;
            $outcome = ThreeWayValue::compare($resolved?->token, $hasLast, $last, $app);
            $kept += $outcome === ThreeWayOutcome::KeptManual ? 1 : 0;
            $item = PlanImport::itemFor($outcome, 'parish', 'Parafia', "{$id}/parafia", $who, $places->tokenLabel($app), $resolved?->place->label, $places->tokenLabel($last), $hasLast, $resolved?->place);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        [$hasLast, $last] = PlanImport::lastOf($snapshot, 'plik_hash');
        $outcome = ThreeWayValue::compare($research->scanHash, $hasLast, $last, $hasLast ? $last : $research->scanHash);
        $previous = $snapshot !== null && is_string($snapshot->values['plik'] ?? null) ? $snapshot->values['plik'] : $source->original_name;
        $item = PlanImport::itemFor($outcome, 'file', 'Plik skanu', "{$id}/plik", $who, $previous, $research->scanPath, $previous, $hasLast, $research->scanPath);

        if ($item !== null) {
            $items[] = $item;
        }

        return [
            'values' => $values,
            'changes' => array_values(array_filter($items, fn ($item): bool => $item instanceof PlannedChange)),
            'fills' => array_values(array_filter($items, fn ($item): bool => $item instanceof PlannedFill)),
            'conflicts' => array_values(array_filter($items, fn ($item): bool => $item instanceof ValueConflict)),
            'kept' => $kept,
        ];
    }
}
