<?php

namespace App\Console\Commands;

use App\Actions\Import\ApplyImport;
use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\MatchResearchPersons;
use App\Actions\Import\MatchResearchPlaces;
use App\Actions\Import\MatchResearchSources;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\CemeteryMatchKind;
use App\Enums\DateQualifier;
use App\Enums\MatchVerdict;
use App\Enums\ParishMatchKind;
use App\Enums\PlaceMatchKind;
use App\Enums\RepoKeyKind;
use App\Models\Person;
use App\Models\RepoSnapshot;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\CemeteryMatches;
use App\Support\Import\CemeteryVerdict;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\ParishMatches;
use App\Support\Import\ParishRequest;
use App\Support\Import\ParishVerdict;
use App\Support\Import\PersonMatch;
use App\Support\Import\PersonMatches;
use App\Support\Import\PlaceDecisions;
use App\Support\Import\PlaceMatches;
use App\Support\Import\PlaceUse;
use App\Support\Import\PlaceVerdict;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedRemoval;
use App\Support\Import\PlannedSourceUpdate;
use App\Support\Import\RemovalImpact;
use App\Support\Import\RepoRecord;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\SourceDecisions;
use App\Support\Import\SourceMatches;
use App\Support\Import\ValueConflict;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\select;

#[Signature('import:research
    {email : E-mail konta, do którego drzewa trafi import}
    {path : Katalog repo badawczego}')]
#[Description('Zaimportuj przodków z repo badawczego do drzewa konta')]
class ImportResearch extends Command
{
    /**
     * The longest label of an answer option; the details are in the table above the question.
     */
    private const OPTION_WIDTH = 74;

    public function handle(ReadResearchRepo $readResearchRepo, ClassifyRepoRecords $classifyRepoRecords, MatchResearchPersons $matchResearchPersons, MatchResearchPlaces $matchResearchPlaces, MatchResearchSources $matchResearchSources, PlanImport $planImport, ApplyImport $applyImport): int
    {
        $email = (string) $this->argument('email');
        $path = (string) $this->argument('path');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error('Nie znaleziono konta dla adresu: '.$email);

            return self::FAILURE;
        }

        $tree = $user->tree;

        if ($tree === null) {
            $this->error('Konto '.$email.' nie ma drzewa, więc nie ma dokąd importować.');

            return self::FAILURE;
        }

        try {
            $reading = $readResearchRepo->handle($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($email, $path, $reading);

        if ($reading->persons === [] && $tree->people()->whereNotNull('repo_id')->exists()) {
            $this->error('Repo nie zawiera żadnej poprawnej osoby, a drzewo ma osoby z importu. Sprawdź ścieżkę i czy dysk jest zamontowany; nic nie zostało zapisane.');

            return self::FAILURE;
        }

        $classes = $classifyRepoRecords->handle($tree, $reading);

        $this->stateSummary($classes);

        $matches = $matchResearchPersons->handle($tree, $reading, $classes->newPersonIds);

        if ($classes->newPersonIds !== []) {
            $this->matchSummary($matches);
        }

        $askedRemovals = array_values(array_filter($classes->removedInRepo, fn (RepoRecord $entry): bool => ! $entry->detachOnly()));

        if (($this->needsAnswers($matches) || $askedRemovals !== [] || $classes->deletedInApp !== []) && ! $this->input->isInteractive()) {
            return $this->needsTerminal();
        }

        $identity = $this->decideIdentities($matches);
        $removals = $this->decideRemovals($classes, $askedRemovals, $reading);
        $deletions = $this->decideDeletions($classes, $reading);

        $placeMatches = $matchResearchPlaces->handle($tree, $reading, $classes, new ImportDecisions($identity, $removals, $deletions));

        $sourceMatches = $matchResearchSources->handle($tree, $reading, $classes, new ImportDecisions($identity, $removals, $deletions));

        if (! $this->input->isInteractive() && ($this->needsPlaceAnswers($tree, $matchResearchPlaces, $placeMatches) || $sourceMatches->matches !== [])) {
            return $this->needsTerminal();
        }

        $decisions = new ImportDecisions($identity, $removals, $deletions, $this->decidePlaces($tree, $matchResearchPlaces, $placeMatches, $reading));
        $sources = $this->decideSourceMatches($sourceMatches);

        try {
            $plan = $planImport->handle($tree, $reading, $classes, $decisions, $sources);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summary($plan, $reading);

        $stored = $tree->repoSnapshots()->get()->keyBy('repo_id')->all();

        if ($this->nothingToWrite($plan, $stored)) {
            $this->info('Nic do zapisania.');

            return self::SUCCESS;
        }

        if (($plan->changes !== [] || $plan->conflicts !== []) && ! $this->input->isInteractive()) {
            return $this->needsTerminal();
        }

        $approvals = $this->decideChanges($plan);
        $resolutions = $this->resolveConflicts($plan);

        $this->newLine();

        $newCount = count($plan->persons);
        $matchedCount = count($plan->matched);
        $changeCount = count($plan->changes) - count($approvals->rejected) + $plan->fillCount()
            + count(array_filter($plan->conflicts, fn (ValueConflict $conflict): bool => $resolutions->takesRepo($conflict->key)));
        $removalCount = count(array_filter($plan->removals, fn (PlannedRemoval $removal): bool => $removal->decision === ImportDecisions::DELETE));
        $memoryCount = count($plan->changedSnapshots($stored)) + count(array_filter($plan->forgottenSnapshots, fn (string $key): bool => isset($stored[$key])));
        $sourceCount = count($plan->newSources) + count($plan->mergedSources);
        $linkCount = $this->linkCount($plan);
        $parishCount = count($plan->newParishes);
        $sourcesText = $sourceCount + $linkCount + $parishCount > 0
            ? sprintf(', źródła %d, powiązania z faktami %d, nowe parafie %d', $sourceCount, $linkCount, $parishCount)
            : '';

        if (! $this->confirm("Zapisać do drzewa konta {$email}: nowe osoby {$newCount}, dopasowane {$matchedCount}, zmiany {$changeCount}, usunięcia {$removalCount}, pamięć importu {$memoryCount}{$sourcesText}?", false)) {
            $this->info('Nic nie zostało zapisane.');

            return self::SUCCESS;
        }

        if (app()->isProduction()
            && $this->call('backup:run', ['--only-db' => true, '--disable-notifications' => true]) !== self::SUCCESS) {
            $this->error('Kopia bazy nie powiodła się, nic nie zostało zapisane.');

            return self::FAILURE;
        }

        try {
            $result = $applyImport->handle($tree, $plan, $approvals, $resolutions);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            $this->error('Nic nie zostało zapisane.');

            return self::FAILURE;
        }

        $this->written($result, $plan, $tree);

        return self::SUCCESS;
    }

    private function needsTerminal(): int
    {
        $this->error('Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.');

        return self::FAILURE;
    }

    private function stateSummary(RepoRecordClasses $classes): void
    {
        $this->newLine();
        $this->info('Stan względem ostatniego importu');
        $this->line(sprintf('Znane osoby: %d', count($classes->knownPersons)));
        $this->line(sprintf('Nowe w repo: %d', count($classes->newPersonIds)));
        $this->line(sprintf('Usunięte w repo: %d', count($classes->removedInRepo)));
        $this->line(sprintf('Usunięte w aplikacji: %d (do decyzji), %d (zostawione wcześniej)', count($classes->deletedInApp), count($classes->keptDeleted)));
        $this->line(sprintf('Nie do oceny: %d', count($classes->unassessable)));
    }

    /**
     * Whether the write would change nothing: no record, no value and no memory entry.
     *
     * @param  array<string, RepoSnapshot>  $stored
     */
    private function nothingToWrite(ImportPlan $plan, array $stored): bool
    {
        return $plan->persons === []
            && $plan->matched === []
            && $plan->updates === []
            && $plan->changes === []
            && $plan->conflicts === []
            && $plan->fillCount() === 0
            && $plan->removals === []
            && $plan->detachedBirths === []
            && $plan->restorations === []
            && $plan->relations === []
            && $plan->marriages === []
            && $plan->aliases === []
            && ! $this->hasSourceWork($plan)
            && $plan->forgottenSnapshots === []
            && $plan->changedSnapshots($stored) === [];
    }

    /**
     * Ask what to do with each record the repo no longer has; a birth is only detached, without a question.
     *
     * @param  list<RepoRecord>  $asked  the removed records that get a question, in the order of the keys
     * @return array<string, string> key => `ImportDecisions::DELETE` or `DETACH`
     */
    private function decideRemovals(RepoRecordClasses $classes, array $asked, ResearchRepoReading $reading): array
    {
        $decisions = [];

        foreach ($classes->removedInRepo as $entry) {
            if ($entry->detachOnly()) {
                $decisions[$entry->key] = ImportDecisions::DETACH;
            }
        }

        $total = count($asked);

        foreach ($asked as $index => $entry) {
            $this->newLine();
            $this->table(
                ['Klucz', 'Skutki usunięcia', 'Dołączone klucze'],
                [[$entry->key, $this->impactText($entry->impact, $entry->kind === RepoKeyKind::Source), $this->memberKeys($entry)]],
            );

            $decisions[$entry->key] = $this->choose(
                sprintf('Usunięte w repo %d/%d: %s (%s) — co zrobić?', $index + 1, $total, $this->describe($entry, $classes, $reading), $entry->key),
                [ImportDecisions::DETACH => 'Zostaw — odłącz od repo', ImportDecisions::DELETE => 'Usuń z aplikacji'],
                ImportDecisions::DETACH,
            );
        }

        return $decisions;
    }

    /**
     * Ask whether each record the author deleted in the app, and the repo still has, comes back.
     *
     * @return array<string, string> key => `ImportDecisions::RESTORE` or `KEEP`
     */
    private function decideDeletions(RepoRecordClasses $classes, ResearchRepoReading $reading): array
    {
        $decisions = [];
        $total = count($classes->deletedInApp);

        foreach ($classes->deletedInApp as $index => $entry) {
            $this->newLine();
            $this->line('Teraz w repo: '.$this->repoValuesText($entry, $reading));

            $decisions[$entry->key] = $this->choose(
                sprintf('Usunięte w aplikacji %d/%d: %s (%s) — co zrobić?', $index + 1, $total, $this->describe($entry, $classes, $reading), $entry->key),
                [ImportDecisions::KEEP => 'Zostaw usunięte', ImportDecisions::RESTORE => 'Przywróć z repo'],
                ImportDecisions::KEEP,
            );
        }

        return $decisions;
    }

    /**
     * Whether any place of the repo needs an answer: a new or doubtful locality, a cemetery or a parish to choose, or a new parish.
     */
    private function needsPlaceAnswers(Tree $tree, MatchResearchPlaces $matcher, PlaceMatches $places): bool
    {
        foreach ($places->localities as $verdict) {
            if ($verdict->kind !== PlaceMatchKind::Sure) {
                return true;
            }
        }

        foreach ($matcher->cemeteries($tree, $places, new PlaceDecisions)->matches as $verdict) {
            if ($verdict->kind === CemeteryMatchKind::Choice) {
                return true;
            }
        }

        foreach ($matcher->parishes($tree, $places, new PlaceDecisions)->matches as $verdict) {
            if (in_array($verdict->kind, [ParishMatchKind::New, ParishMatchKind::Choice], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ask about the places of the repo, once per name: the new localities together or one by one, each doubtful one
     * on its own, then the cemeteries with a choice, the new parishes together or one by one and the parishes with a choice. Sure
     * matches and new cemeteries need no answer.
     */
    private function decidePlaces(Tree $tree, MatchResearchPlaces $matcher, PlaceMatches $places, ResearchRepoReading $reading): PlaceDecisions
    {
        $localities = [];
        $aliases = [];

        $new = array_filter($places->localities, fn (PlaceVerdict $verdict): bool => $verdict->kind === PlaceMatchKind::New);
        $doubtful = array_filter($places->localities, fn (PlaceVerdict $verdict): bool => $verdict->kind === PlaceMatchKind::Doubtful);

        if ($new !== []) {
            $rows = [];

            foreach ($new as $verdict) {
                $rows[] = [$verdict->name, $verdict->eventCount(), $this->placeUsers($verdict->uses, $reading)];
            }

            $this->newLine();
            $this->line('Nowe miejscowości:');
            $this->table(['Nazwa', 'Zdarzeń', 'Osoby'], $rows);

            $mode = $this->choose(
                sprintf('Nowe miejscowości (%d): co zrobić?', count($new)),
                ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'],
                'all',
            );

            $position = 0;

            foreach ($new as $key => $verdict) {
                $position++;

                $answer = $mode === 'all' ? PlaceDecisions::NEW : $this->choose(
                    sprintf('Nowa miejscowość %d/%d: %s (%s)', $position, count($new), $verdict->name, $this->countText($verdict->eventCount(), ['zdarzenie', 'zdarzenia', 'zdarzeń'])),
                    [PlaceDecisions::NEW => 'Dodaj do spisu', PlaceDecisions::NONE => 'Bez miejsca'],
                    PlaceDecisions::NEW,
                );

                $localities[(string) $key] = $answer;
            }
        }

        $position = 0;

        foreach ($doubtful as $key => $verdict) {
            $position++;

            $this->newLine();
            $this->line(sprintf('Niepewne %d/%d: %s — %s', $position, count($doubtful), $verdict->name, $this->placeUsers($verdict->uses, $reading)));

            $options = [];

            foreach ($verdict->candidates as $candidate) {
                $options['locality:'.$candidate->id] = 'To '.$candidate->name.' (zapamiętaj wariant)';
            }

            if (! $verdict->keyTaken()) {
                $options[PlaceDecisions::NEW] = 'Dodaj jako nową';
            }

            $options[PlaceDecisions::NONE] = 'Bez miejsca';

            $answer = $this->choose(
                sprintf('Niepewne %d/%d: %s (%s)', $position, count($doubtful), $verdict->name, $this->countText($verdict->eventCount(), ['zdarzenie', 'zdarzenia', 'zdarzeń'])),
                $options,
                PlaceDecisions::NONE,
            );

            if (str_starts_with($answer, 'locality:')) {
                $localities[(string) $key] = (int) substr($answer, strlen('locality:'));
                $aliases[(string) $key] = true;
            } else {
                $localities[(string) $key] = $answer;
            }
        }

        $cemeteries = $this->decideCemeteries($matcher->cemeteries($tree, $places, new PlaceDecisions($localities, $aliases)), $places, $reading);
        $parishes = $this->decideParishes($matcher->parishes($tree, $places, new PlaceDecisions($localities, $aliases, $cemeteries)), $places);

        return new PlaceDecisions($localities, $aliases, $cemeteries, $parishes);
    }

    /**
     * Ask about the parishes and offices the sources name: the new ones together or one by one, then each one with
     * several candidates. A sure match, or a seat left out, needs no answer.
     *
     * @return array<string, int|string> {@see PlaceDecisions::parishKey()} => id of a parish, `NEW` or `NONE`
     */
    private function decideParishes(ParishMatches $matches, PlaceMatches $places): array
    {
        $new = array_filter($matches->matches, fn (ParishVerdict $verdict): bool => $verdict->kind === ParishMatchKind::New);
        $choices = array_filter($matches->matches, fn (ParishVerdict $verdict): bool => $verdict->kind === ParishMatchKind::Choice);
        $decisions = [];

        if ($new !== []) {
            $rows = [];

            foreach ($new as $verdict) {
                $request = $verdict->request;
                $rows[] = [
                    $this->seatName($request, $places),
                    $request->kind->label(),
                    $request->religion?->label() ?? '—',
                    $request->dedication ?? '—',
                    $verdict->sourceCount(),
                ];
            }

            $this->newLine();
            $this->line('Nowe parafie i urzędy:');
            $this->table(['Siedziba', 'Rodzaj', 'Wyznanie', 'Wezwanie', 'Źródeł'], $rows);

            $mode = $this->choose(
                sprintf('Nowe parafie i urzędy (%d): co zrobić?', count($new)),
                ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'],
                'all',
            );

            $position = 0;

            foreach ($new as $key => $verdict) {
                $position++;

                $decisions[(string) $key] = $mode === 'all' ? PlaceDecisions::NEW : $this->choose(
                    sprintf('Nowa parafia lub urząd %d/%d: %s (%s)', $position, count($new), $this->parishName($verdict->request, $places), $this->countText($verdict->sourceCount(), ['źródło', 'źródła', 'źródeł'])),
                    [PlaceDecisions::NEW => 'Dodaj do spisu', PlaceDecisions::NONE => 'Bez parafii'],
                    PlaceDecisions::NEW,
                );
            }
        }

        $position = 0;

        foreach ($choices as $key => $verdict) {
            $position++;
            $name = $this->parishName($verdict->request, $places);

            $this->newLine();
            $this->line(sprintf('Parafia %d/%d: %s — %s', $position, count($choices), $name, $this->sourceNames($verdict->request->uses)));

            $options = [];

            foreach ($verdict->options as $parish) {
                $options['parish:'.$parish->id] = $parish->label();
            }

            $options[PlaceDecisions::NEW] = 'Dodaj nową';
            $options[PlaceDecisions::NONE] = 'Bez parafii';

            $answer = $this->choose(
                sprintf('Parafia %d/%d: %s (%s)', $position, count($choices), $name, $this->countText($verdict->sourceCount(), ['źródło', 'źródła', 'źródeł'])),
                $options,
                PlaceDecisions::NONE,
            );

            $decisions[(string) $key] = str_starts_with($answer, 'parish:') ? (int) substr($answer, strlen('parish:')) : $answer;
        }

        return $decisions;
    }

    private function seatName(ParishRequest $request, PlaceMatches $places): string
    {
        return $places->localities[$request->localityKey]->name ?? $request->localityKey;
    }

    /**
     * A parish as the list of the app names it: the seat, the kind, the dedication and the religion.
     */
    private function parishName(ParishRequest $request, PlaceMatches $places): string
    {
        $label = implode(' ', array_filter(
            [$this->seatName($request, $places), $request->kind->labelSuffix(), $request->dedication],
            fn (?string $part): bool => $part !== null && $part !== '',
        ));

        return $request->religion === null ? $label : "{$label} ({$request->religion->shortLabel()})";
    }

    /**
     * The sources that name a parish, e.g. "ZR-0001, ZR-0002", at most five.
     *
     * @param  list<PlaceUse>  $uses
     */
    private function sourceNames(array $uses): string
    {
        $ids = array_values(array_unique(array_map(fn (PlaceUse $use): string => $use->repoId, $uses)));

        return implode(', ', array_slice($ids, 0, 5)).(count($ids) > 5 ? sprintf(' i %d innych', count($ids) - 5) : '');
    }

    /**
     * Ask, for each new source of the repo that may hold the scan of a manual source, whether it is that source. The
     * default is a new source.
     */
    private function decideSourceMatches(SourceMatches $matches): SourceDecisions
    {
        $merges = [];
        $taken = [];
        $position = 0;
        $total = count($matches->matches);

        foreach ($matches->matches as $repoId => $match) {
            $position++;
            $source = $match->source;
            $name = PlannedSourceUpdate::labelOf($source);

            $rows = [['Z repo', $source->id, PlanImport::cleanText($source->name) ?? '—', basename($source->scanPath), $source->recordType?->label() ?? '—', $source->year ?? '—', '—']];
            $options = ['new' => 'Dodaj jako nowe'];
            $labels = array_count_values(array_map(fn (Source $candidate): string => $candidate->original_name, $match->candidates));

            foreach ($match->candidates as $candidate) {
                $facts = $candidate->events->map(fn ($event): string => trim($event->type->label().' '.(ResearchDate::fromEvent($event)?->label() ?? '')))->implode(', ');
                $rows[] = ['Ręczne', '#'.$candidate->id, $candidate->name ?? '—', $candidate->original_name, $candidate->record_type?->label() ?? '—', $candidate->year ?? '—', $facts === '' ? '—' : $facts];

                if (! isset($taken[$candidate->id])) {
                    $options['source:'.$candidate->id] = 'Połącz z '.$candidate->original_name.($labels[$candidate->original_name] > 1 ? ' (#'.$candidate->id.')' : '');
                }
            }

            $this->newLine();
            $this->line(sprintf('Źródło %d/%d: to ten sam skan?', $position, $total));
            $this->table(['', 'ID', 'Nazwa', 'Plik', 'Typ', 'Rok', 'Fakt'], $rows);

            $answer = $this->choose(sprintf('Źródło %d/%d: %s — to ten sam skan?', $position, $total, $name), $options, 'new');

            if (str_starts_with($answer, 'source:')) {
                $id = (int) substr($answer, strlen('source:'));
                $merges[(string) $repoId] = $id;
                $taken[$id] = true;
            }
        }

        return new SourceDecisions($merges);
    }

    private function hasSourceWork(ImportPlan $plan): bool
    {
        return $plan->newSources !== [] || $plan->mergedSources !== [] || $plan->sourceUpdates !== [] || $plan->newParishes !== [];
    }

    /**
     * The links of facts to sources that the plan creates together with records: the births and deaths of new, matched and known persons, new weddings and restored records and sources.
     */
    private function linkCount(ImportPlan $plan): int
    {
        $links = 0;

        foreach ([...$plan->persons, ...$plan->matched, ...$plan->updates] as $planned) {
            $links += count($planned->birthSources) + count($planned->deathSources);
        }

        foreach ($plan->marriages as $marriage) {
            $links += count($marriage->sources);
        }

        foreach ($plan->restorations as $restoration) {
            $links += count($restoration->sources) + ($restoration->source === null ? 0 : count($restoration->source->entries));
        }

        return $links;
    }

    /**
     * Ask which cemetery a burial names when its locality has several and the block does not point at one.
     *
     * @return array<string, int|string> {@see PlaceDecisions::cemeteryKey()} => id of a cemetery, `NEW` or `NONE`
     */
    private function decideCemeteries(CemeteryMatches $matches, PlaceMatches $places, ResearchRepoReading $reading): array
    {
        $choices = array_filter($matches->matches, fn (CemeteryVerdict $verdict): bool => $verdict->kind === CemeteryMatchKind::Choice);
        $decisions = [];
        $position = 0;

        foreach ($choices as $key => $verdict) {
            $position++;

            $locality = $places->localities[$verdict->request->localityKey]->name ?? $verdict->request->localityKey;
            $name = $verdict->request->name === null ? $locality : sprintf('%s, cmentarz „%s”', $locality, $verdict->request->name);

            $this->newLine();
            $this->line(sprintf('Cmentarz %d/%d: %s — %s', $position, count($choices), $name, $this->placeUsers($verdict->request->uses, $reading)));

            $options = [];

            foreach ($verdict->options as $cemetery) {
                $options['cemetery:'.$cemetery->id] = $cemetery->label();
            }

            $options[PlaceDecisions::NEW] = 'Nowy cmentarz';
            $options[PlaceDecisions::NONE] = 'Bez miejsca';

            $answer = $this->choose(
                sprintf('Cmentarz %d/%d: %s (%s)', $position, count($choices), $name, $this->countText($verdict->eventCount(), ['pochowanie', 'pochowania', 'pochowań'])),
                $options,
                PlaceDecisions::NONE,
            );

            $decisions[(string) $key] = str_starts_with($answer, 'cemetery:') ? (int) substr($answer, strlen('cemetery:')) : $answer;
        }

        return $decisions;
    }

    /**
     * The persons whose events name a place, e.g. "Jan Nowak (O-0001), Anna Wrona (O-0004)", at most five.
     *
     * @param  list<PlaceUse>  $uses
     */
    private function placeUsers(array $uses, ResearchRepoReading $reading): string
    {
        $ids = array_values(array_unique(array_map(fn (PlaceUse $use): string => $use->repoId, $uses)));
        $names = array_map(
            fn (string $id): string => isset($reading->persons[$id]) ? sprintf('%s (%s)', $this->researchName($reading->persons[$id]), $id) : $id,
            array_slice($ids, 0, 5),
        );

        return implode(', ', $names).(count($ids) > 5 ? sprintf(' i %d innych', count($ids) - 5) : '');
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $forms  one, few and many
     */
    private function countText(int $count, array $forms): string
    {
        $form = match (true) {
            $count === 1 => $forms[0],
            $count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 12 || $count % 100 > 14) => $forms[1],
            default => $forms[2],
        };

        return "{$count} {$form}";
    }

    /**
     * Show every change of the repo in fields nobody touched in the app and ask for all of them at once or one by one.
     */
    private function decideChanges(ImportPlan $plan): ChangeApprovals
    {
        $changes = $plan->changes;

        if ($changes === []) {
            return new ChangeApprovals;
        }

        $rows = [];

        foreach ($changes as $change) {
            [$app, $repo] = $this->shown($change->field, $change->appLabel, $change->repoLabel);
            $rows[] = [$change->person, $change->label, $app, $repo];
        }

        $this->newLine();
        $this->line('Zmiany z repo:');
        $this->table(['Osoba', 'Pole', 'W aplikacji', 'Teraz w repo'], $rows);

        $mode = $this->choose(
            sprintf('Zmiany z repo (%d): co zrobić?', count($changes)),
            ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'],
            'all',
        );

        if ($mode === 'all') {
            return new ChangeApprovals;
        }

        $rejected = [];

        foreach ($changes as $index => $change) {
            [$app, $repo] = $this->shown($change->field, $change->appLabel, $change->repoLabel);

            $answer = $this->choose(
                sprintf('Zmiana %d/%d: %s — %s', $index + 1, count($changes), $change->person, $change->label),
                [
                    'repo' => $this->optionLabel("Weź z repo: {$repo}", $change->field),
                    'app' => $this->optionLabel("Zostaw: {$app}", $change->field),
                ],
                'repo',
            );

            if ($answer === 'app') {
                $rejected[] = $change->key;
            }
        }

        return new ChangeApprovals($rejected);
    }

    /**
     * The two values as the author sees them in a table or an option. A note block is cut to its first differing line.
     *
     * @return array{0: string, 1: string} the app value, then the repo value
     */
    private function shown(string $field, string $app, string $repo): array
    {
        if ($field !== 'note') {
            return [$app, $repo];
        }

        $appLines = explode("\n", $app);
        $repoLines = explode("\n", $repo);

        foreach (range(0, max(count($appLines), count($repoLines)) - 1) as $index) {
            if (($appLines[$index] ?? null) !== ($repoLines[$index] ?? null)) {
                return [$appLines[$index] ?? '—', $repoLines[$index] ?? '—'];
            }
        }

        return [$app, $repo];
    }

    /**
     * An answer option cut to the width of the terminal; a place keeps its whole name.
     */
    private function optionLabel(string $text, string $field): string
    {
        return str_ends_with($field, '_place') ? $text : Str::limit($text, self::OPTION_WIDTH);
    }

    private function impactText(?RemovalImpact $impact, bool $source = false): string
    {
        if ($impact === null || $impact->isEmpty()) {
            return 'brak';
        }

        $parts = [];

        if ($impact->scan) {
            $parts[] = 'skan do usunięcia';
        }

        if ($impact->photo) {
            $parts[] = 'zdjęcie';
        }

        if ($impact->sourceLinks > 0) {
            $parts[] = ($source ? 'powiązania z faktami: ' : 'powiązania ze źródłami: ').$impact->sourceLinks;
        }

        if ($impact->burial) {
            $parts[] = 'pochówek';
        }

        if ($impact->otherEvents > 0) {
            $parts[] = "inne wydarzenia: {$impact->otherEvents}";
        }

        if ($impact->residences > 0) {
            $parts[] = "zamieszkania: {$impact->residences}";
        }

        if ($impact->relations > 0) {
            $parts[] = "relacje: {$impact->relations}";
        }

        if ($impact->marriages > 0) {
            $parts[] = "małżeństwa: {$impact->marriages}";
        }

        if ($impact->gravestones > 0) {
            $parts[] = "nagrobki: {$impact->gravestones}";
        }

        if ($impact->proband) {
            $parts[] = 'proband drzewa';
        }

        return implode(', ', $parts);
    }

    private function memberKeys(RepoRecord $entry): string
    {
        return $entry->members === [] ? '—' : implode(', ', array_map(fn (RepoRecord $member): string => $member->key, $entry->members));
    }

    /**
     * What the repo holds for the key of an entry and for the keys decided with it.
     */
    private function repoValuesText(RepoRecord $entry, ResearchRepoReading $reading): string
    {
        $parts = [];

        foreach ([$entry, ...$entry->members] as $each) {
            $values = array_filter(RepoValues::forKey($each->key, $reading) ?? [], fn (?string $value, string $field): bool => $value !== null && $field !== 'plik_hash', ARRAY_FILTER_USE_BOTH);
            $pairs = array_map(
                fn (string $field, string $value): string => $field.': '.Str::limit($field === 'warianty' ? str_replace("\n", ', ', $value) : (strtok($value, "\n") ?: '—'), 60),
                array_keys($values),
                $values,
            );

            $parts[] = $each->key.' — '.($pairs === [] ? 'bez wartości' : implode(', ', $pairs));
        }

        return implode('; ', $parts);
    }

    private function describe(RepoRecord $entry, RepoRecordClasses $classes, ResearchRepoReading $reading): string
    {
        $name = function (string $id) use ($classes, $reading): string {
            if (isset($reading->persons[$id])) {
                return $this->researchName($reading->persons[$id]);
            }

            return isset($classes->knownPersons[$id]) ? $classes->knownPersons[$id]->displayName() : $id;
        };

        return match ($entry->kind) {
            RepoKeyKind::Person => $entry->record instanceof Person ? $entry->record->displayName() : $name($entry->personIds[0]),
            RepoKeyKind::Birth => 'Urodzenie: '.$name($entry->personIds[0]),
            RepoKeyKind::Death => 'Zgon: '.$name($entry->personIds[0]),
            RepoKeyKind::Burial => 'Pochowanie: '.$name($entry->personIds[0]),
            RepoKeyKind::Relation => sprintf('Relacja: %s → %s', $name($entry->personIds[0]), $name($entry->personIds[1])),
            RepoKeyKind::Marriage => sprintf('Małżeństwo: %s i %s', $name($entry->personIds[0]), $name($entry->personIds[1])),
            RepoKeyKind::Source => 'Źródło: '.$this->sourceName($entry, $reading),
        };
    }

    /**
     * The name of a source for a question: its name, or the scan file when it has none; the repo id is in the question.
     */
    private function sourceName(RepoRecord $entry, ResearchRepoReading $reading): string
    {
        $research = $reading->sources[$entry->key] ?? null;

        if ($entry->record instanceof Source) {
            return PlanImport::cleanText($entry->record->name) ?? $entry->record->original_name;
        }

        return $research === null ? $entry->key : (PlanImport::cleanText($research->name) ?? basename($research->scanPath));
    }

    private function matchSummary(PersonMatches $matches): void
    {
        $counts = array_count_values(array_map(fn (PersonMatch $match): string => $match->verdict->value, $matches->matches));

        $this->newLine();
        $this->info('Dopasowanie do osób w drzewie');
        $this->line(sprintf(
            'Osoby z repo: %d (%s: %d, %s: %d, %s: %d)',
            count($matches->matches),
            MatchVerdict::New->label(),
            $counts[MatchVerdict::New->value] ?? 0,
            MatchVerdict::Sure->label(),
            $counts[MatchVerdict::Sure->value] ?? 0,
            MatchVerdict::Doubtful->label(),
            $counts[MatchVerdict::Doubtful->value] ?? 0,
        ));
    }

    private function needsAnswers(PersonMatches $matches): bool
    {
        foreach ($matches->matches as $match) {
            if ($match->verdict !== MatchVerdict::New) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ask the author who the sure and doubtful repo persons are. The sure ones can be approved together; every other
     * case is asked one by one, in the order of the repo ids, and a tree person already chosen is not offered again.
     * A repo person without a candidate is a new person.
     */
    private function decideIdentities(PersonMatches $matches): IdentityDecisions
    {
        $all = $matches->matches;
        ksort($all);

        $sure = array_filter($all, fn (PersonMatch $match): bool => $match->verdict === MatchVerdict::Sure);
        $doubtful = array_filter($all, fn (PersonMatch $match): bool => $match->verdict === MatchVerdict::Doubtful);

        $matched = [];
        $skipped = [];
        $new = array_keys(array_filter($all, fn (PersonMatch $match): bool => $match->verdict === MatchVerdict::New));
        $taken = [];
        $review = [];

        if ($sure !== []) {
            $this->sureTable($sure);

            $mode = $this->choose(
                sprintf('Pewne dopasowania (%d): co zrobić?', count($sure)),
                ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'],
                'all',
            );

            if ($mode === 'all') {
                foreach ($sure as $repoId => $match) {
                    $matched[$repoId] = $match->candidates[0]->person->id;
                    $taken[$match->candidates[0]->person->id] = true;
                }
            } else {
                $review[] = ['Pewne', $sure, true];
            }
        }

        if ($doubtful !== []) {
            $review[] = ['Wątpliwe', $doubtful, false];
        }

        foreach ($review as [$group, $cases, $preferCandidate]) {
            $position = 0;

            foreach ($cases as $repoId => $match) {
                $position++;
                $answer = $this->askIdentity($group, $position, count($cases), (string) $repoId, $match, $taken, $preferCandidate);

                if ($answer === 'new') {
                    $new[] = $repoId;
                } elseif (str_starts_with($answer, 'person:')) {
                    $personId = (int) substr($answer, strlen('person:'));
                    $matched[$repoId] = $personId;
                    $taken[$personId] = true;
                } else {
                    $skipped[] = $repoId;
                }
            }
        }

        return new IdentityDecisions(
            $matched,
            array_map(strval(...), $skipped),
            array_map(strval(...), $new),
        );
    }

    /**
     * @param  array<string, PersonMatch>  $sure
     */
    private function sureTable(array $sure): void
    {
        $rows = [];

        foreach ($sure as $repoId => $match) {
            $candidate = $match->candidates[0];

            $rows[] = [
                $repoId,
                $this->researchName($match->person),
                $this->treePersonLabel($candidate->person),
                implode(', ', array_map(fn ($element): string => $element->label(), $candidate->agreeing)),
            ];
        }

        $this->newLine();
        $this->line('Pewne dopasowania:');
        $this->table(['Id repo', 'Osoba z repo', 'Osoba w drzewie', 'Zgodne elementy'], $rows);
    }

    /**
     * Show the comparison of one repo person with its candidates and ask who it is.
     *
     * @param  array<int, true>  $taken  tree persons already chosen for another repo person
     * @return string `person:<id>`, `new` or `skip`
     */
    private function askIdentity(string $group, int $position, int $total, string $repoId, PersonMatch $match, array $taken, bool $preferCandidate): string
    {
        $name = $this->researchName($match->person);

        $this->newLine();
        $this->line("{$group} {$position}/{$total}: {$name} ({$repoId})");
        $this->comparisonTable($match, $taken);

        $options = [];

        foreach ($match->candidates as $candidate) {
            if (! isset($taken[$candidate->person->id])) {
                $options['person:'.$candidate->person->id] = Str::limit('To '.$this->treePersonLabel($candidate->person), self::OPTION_WIDTH);
            }
        }

        $options['new'] = 'Inna osoba — dodaj jako nową';
        $options['skip'] = 'Pomiń — nie importuj teraz';

        $default = 'skip';

        if ($preferCandidate) {
            $first = 'person:'.$match->candidates[0]->person->id;
            $default = isset($options[$first]) ? $first : 'skip';
        }

        return $this->choose("{$group} {$position}/{$total}: {$name} ({$repoId}) — kto to?", $options, $default);
    }

    /**
     * One row per field with the repo value and the value of each candidate, then the agreeing and the contradicting elements.
     *
     * @param  array<int, true>  $taken
     */
    private function comparisonTable(PersonMatch $match, array $taken): void
    {
        $headers = ['Pole', 'Repo'];
        $fields = [];

        foreach ($match->candidates as $index => $candidate) {
            $headers[] = sprintf('Kandydat %d (#%d)%s', $index + 1, $candidate->person->id, isset($taken[$candidate->person->id]) ? ' — wybrany' : '');

            foreach ($candidate->rows as [$field, $repoValue, $treeValue]) {
                $fields[$field]['repo'] ??= $repoValue;
                $fields[$field]['tree'][$index] = $treeValue;
            }
        }

        $rows = [];

        foreach ($fields as $field => $values) {
            $row = [(string) $field, $values['repo']];

            foreach (array_keys($match->candidates) as $index) {
                $row[] = $values['tree'][$index] ?? '—';
            }

            $rows[] = $row;
        }

        $agreeing = ['Zgodne', ''];
        $contradictions = ['Sprzeczne', ''];

        foreach ($match->candidates as $candidate) {
            $agreeing[] = $candidate->agreeing === [] ? '—' : implode(', ', array_map(fn ($element): string => $element->label(), $candidate->agreeing));
            $contradictions[] = $candidate->contradictions === [] ? '—' : implode('; ', $candidate->contradictions);
        }

        $this->table($headers, [...$rows, $agreeing, $contradictions]);
    }

    private function resolveConflicts(ImportPlan $plan): ConflictResolutions
    {
        $choices = [];
        $total = count($plan->conflicts);

        foreach ($plan->conflicts as $index => $conflict) {
            $this->newLine();

            if ($conflict->field === 'note') {
                $this->line('W aplikacji:');
                $this->line($conflict->appLabel);
                $this->line('Teraz w repo:');
                $this->line($conflict->repoLabel);
            }

            if ($conflict->lastRepoLabel !== null) {
                $this->line("poprzednio w repo: {$conflict->lastRepoLabel}");
            }

            [$app, $repo] = $this->shown($conflict->field, $conflict->appLabel, $conflict->repoLabel);

            $choices[$conflict->key] = $this->choose(
                sprintf('Konflikt %d/%d: %s — %s', $index + 1, $total, $conflict->person, $conflict->label),
                [
                    ConflictResolutions::FROM_APP => $this->optionLabel("Zostaw z aplikacji: {$app}", $conflict->field),
                    ConflictResolutions::FROM_REPO => $this->optionLabel("Weź z repo: {$repo}", $conflict->field),
                ],
                ConflictResolutions::FROM_APP,
            );
        }

        return new ConflictResolutions($choices);
    }

    /**
     * @param  array<string, string>  $options  key => label
     * @return string the key of the chosen option
     */
    private function choose(string $label, array $options, string $default): string
    {
        return (string) select($label, $options, $default);
    }

    private function researchName(ResearchPerson $person): string
    {
        $name = trim(($person->givenNames ?? '').' '.($person->surname ?? ''));

        return $name === '' ? 'NN' : $name;
    }

    private function treePersonLabel(Person $person): string
    {
        $name = trim(($person->first_name ?? '').' '.($person->last_name ?? ''));
        $born = ResearchDate::fromEvent($person->birth())?->label() ?? 'brak daty';

        return sprintf('%s (#%d, ur. %s)', $name === '' ? 'NN' : $name, $person->id, $born);
    }

    private function summary(ImportPlan $plan, ResearchRepoReading $reading): void
    {
        $newRelations = count(array_filter($plan->relations, fn ($relation): bool => $relation->existingId === null));
        $newMarriages = count(array_filter($plan->marriages, fn ($marriage): bool => $marriage->existingEventId === null));

        $this->newLine();
        $this->info('Podsumowanie importu');
        $this->line(sprintf('Osoby: nowe %d, dopasowane %d, pominięte %d', count($plan->persons), count($plan->matched), count($plan->skippedPersons)));
        $this->line(sprintf('Uzupełnienia pustych pól: %d', $plan->fillCount()));
        $this->line(sprintf(
            'Relacje rodzic–dziecko: %d do zapisania, %d istniejących do oznaczenia, %d pominiętych',
            $newRelations,
            count($plan->relations) - $newRelations,
            count($plan->skippedRelations),
        ));
        $this->line(sprintf(
            'Małżeństwa: %d do zapisania, %d istniejących do oznaczenia, %d pominiętych',
            $newMarriages,
            count($plan->marriages) - $newMarriages,
            count($plan->skippedMarriages),
        ));
        $this->line(sprintf('Urodzenia: %d, zgony: %d, śluby: %d', $plan->births, $plan->deaths, $plan->weddings));
        $this->line(sprintf('Konflikty wartości do rozstrzygnięcia: %d', count($plan->conflicts)));
        $this->line(sprintf('Zmiany z repo: %d', count($plan->changes)));
        $this->line(sprintf('Zachowane ręczne zmiany: %d', $plan->keptManual));
        $this->line(sprintf(
            'Usunięcia: %d, odłączenia: %d, przywrócenia: %d',
            count(array_filter($plan->removals, fn (PlannedRemoval $removal): bool => $removal->decision === ImportDecisions::DELETE)),
            count(array_filter($plan->removals, fn (PlannedRemoval $removal): bool => $removal->decision === ImportDecisions::DETACH)) + count($plan->detachedBirths),
            count($plan->restorations),
        ));

        $dates = [];

        foreach (DateQualifier::cases() as $qualifier) {
            $count = $plan->datesByQualifier[$qualifier->value] ?? 0;

            if ($count > 0) {
                $dates[] = sprintf('%s: %d', $this->qualifierLabel($qualifier), $count);
            }
        }

        $dates[] = sprintf('bez daty: %d', $plan->undatedEvents);
        $this->line('Daty: '.implode(', ', $dates));

        $notes = $plan->noteValues;
        $this->line('Miejsca:');
        $this->line(sprintf('  nowe miejscowości: %d, nowe cmentarze: %d, zapamiętane warianty: %d', $notes->newLocalities, $notes->newCemeteries, $notes->aliases));
        $this->line(sprintf('  zdarzenia z miejscem: %d, pochowania: %d', $notes->placedEvents, $notes->burials));
        $this->line(sprintf('Warianty nazwiska przy osobach: %d', $notes->surnameVariants));
        $this->line('Do notatki osoby:');
        $this->line(sprintf('  zdarzenia bez miejsca: %d', $notes->unplacedEvents));
        $this->line(sprintf('  poziomy pewności: %d (S-08)', $notes->levels));
        $this->line(sprintf('  notatki wydarzeń: %d', $notes->eventNotes));

        if ($this->hasSourceWork($plan)) {
            $this->line(sprintf(
                'Źródła: %d nowych, %d połączone z ręcznymi, %d nowy plik skanu; powiązania z faktami: %d; nowe parafie: %d',
                count($plan->newSources),
                count($plan->mergedSources),
                count(array_filter($plan->changes, fn (PlannedChange $change): bool => $change->field === 'file')),
                $this->linkCount($plan),
                count($plan->newParishes),
            ));
        }

        $this->issueTable('Pominięte osoby', ['Plik', 'Powód'], $plan->skippedPersons);
        $this->issueTable('Pominięte relacje', ['Plik', 'Powód'], $plan->skippedRelations);
        $this->issueTable('Pominięte małżeństwa', ['Plik', 'Powód'], $plan->skippedMarriages);
        $this->issueTable('Pominięte wartości', ['Plik', 'Powód'], $plan->skippedValues);
        $this->issueTable('Odłączone urodzenia', ['Klucz', 'Powód'], array_map(
            fn (PlannedRemoval $removal): ImportIssue => new ImportIssue($removal->key, 'urodzenie zniknęło z repo; zostaje w drzewie bez id repo'),
            $plan->detachedBirths,
        ));
        $this->issueTable('Nie do oceny', ['Klucz', 'Powód'], $plan->unassessable);
        $this->fillTable($plan, $reading);
    }

    private function fillTable(ImportPlan $plan, ResearchRepoReading $reading): void
    {
        $rows = [];

        foreach ($plan->matched as $match) {
            foreach ($match->fills as $fill) {
                $rows[] = [sprintf('%s (%s)', $this->researchName($match->person), $match->person->id), $fill->label, $fill->valueLabel()];
            }
        }

        foreach ($plan->updates as $update) {
            foreach ($update->fills as $fill) {
                $rows[] = [sprintf('%s (%s)', $this->researchName($update->person), $update->person->id), $fill->label, $fill->valueLabel()];
            }
        }

        foreach ($plan->marriages as $planned) {
            if ($planned->dateFill === null && $planned->placeFill === null) {
                continue;
            }

            $spouses = implode(' i ', array_map(
                fn (string $repoId): string => isset($reading->persons[$repoId]) ? sprintf('%s (%s)', $this->researchName($reading->persons[$repoId]), $repoId) : $repoId,
                $planned->marriage->personIds,
            ));

            foreach (array_filter([$planned->dateFill, $planned->placeFill]) as $fill) {
                $rows[] = [$spouses, $fill->label, $fill->valueLabel()];
            }
        }

        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->line('Uzupełnienia:');
        $this->table(['Osoba', 'Pole', 'Wartość z repo'], $rows);
    }

    private function qualifierLabel(DateQualifier $qualifier): string
    {
        return match ($qualifier) {
            DateQualifier::Exact => 'dokładne',
            DateQualifier::Approximate => 'przybliżone',
            DateQualifier::Before => 'przed',
            DateQualifier::After => 'po',
            DateQualifier::Range => 'zakres',
        };
    }

    private function written(ImportResult $result, ImportPlan $plan, Tree $tree): void
    {
        $this->newLine();
        $this->info('Zapisano w drzewie:');
        $this->line(sprintf('Osoby: nowe %d, dopasowane %d', $result->persons, $result->matched));
        $this->line(sprintf('Uzupełnienia pustych pól: %d, wartości wzięte z repo: %d', $result->fills, $result->takenFromRepo));
        $this->line(sprintf('Relacje rodzic–dziecko: %d (istniejące oznaczone: %d)', $result->relations, $result->markedRelations));
        $this->line(sprintf('Małżeństwa: %d (istniejące oznaczone: %d)', $result->marriages, $result->markedMarriages));
        $this->line(sprintf('Wydarzenia: %d (urodzenia: %d, zgony: %d, pochowania: %d)', $result->events, $result->births, $result->deaths, $result->burials));
        $this->line(sprintf('Miejsca: nowe miejscowości %d, nowe cmentarze %d, zapamiętane warianty %d', $result->newLocalities, $result->newCemeteries, $result->aliases));
        $this->line(sprintf('Zmiany z repo: wzięte %d, odrzucone %d', $result->changesTaken, $result->changesRejected));
        $this->line(sprintf(
            'Usunięte: osoby %d, inne rekordy %d; odłączone: %d; przywrócone: %d; zostawione jako usunięte: %d',
            $result->removedPersons,
            $result->removedRecords,
            $result->detached,
            $result->restored,
            $result->keptDeleted,
        ));

        if ($result->newSources + $result->mergedSources + $result->changedSources + $result->removedSources + $result->detachedSources + $result->newParishes + $result->linksAdded + $result->linksRemoved > 0) {
            $this->line(sprintf(
                'Źródła: nowe %d, połączone z ręcznymi %d, zmienione %d (podmienione pliki: %d), usunięte %d, odłączone od repo %d',
                $result->newSources,
                $result->mergedSources,
                $result->changedSources,
                $result->replacedFiles,
                $result->removedSources,
                $result->detachedSources,
            ));
            $this->line(sprintf('Powiązania z faktami: dopięte %d, odpięte %d; nowe parafie: %d', $result->linksAdded, $result->linksRemoved, $result->newParishes));
        }

        if ($tree->refresh()->root_person_id !== null) {
            return;
        }

        $proband = 'Import nie ustawia probanta — wybierz go w aplikacji (Drzewo → Wybierz probanta).';

        if ($plan->probandHint !== null) {
            $proband .= sprintf(' Ahnentafel 1: %s (%s).', $this->researchName($plan->probandHint), $plan->probandHint->id);
        }

        $this->line($proband);
    }

    private function report(string $email, string $path, ResearchRepoReading $reading): void
    {
        $this->info("Odczyt repo badawczego dla konta {$email}");
        $this->line("Katalog: {$path}");
        $this->newLine();

        $withoutBlock = count($reading->filesWithoutBlock);

        $this->line(sprintf('Pliki przejrzane: %d (z blokiem: %d, bez bloku: %d)', $reading->scannedFiles, $reading->scannedFiles - $withoutBlock, $withoutBlock));
        $this->line(sprintf('Osoby przeczytane: %d', count($reading->persons)));
        $this->line(sprintf('Relacje rodzic–dziecko rozpoznane: %d', $reading->parentChildRelations));
        $this->line(sprintf('Małżeństwa rozpoznane: %d', count($reading->marriages)));
        $this->line(sprintf('Wpisy pominięte: %d', count($reading->skipped)));
        $this->line(sprintf('Ostrzeżenia: %d', count($reading->warnings)));

        $this->issueTable('Pominięte wpisy', ['Plik', 'Powód'], $reading->skipped);
        $this->issueTable('Ostrzeżenia', ['Plik', 'Opis'], $reading->warnings);

        $this->sourceReport($reading);

        if ($this->output->isVerbose() && $reading->filesWithoutBlock !== []) {
            $this->newLine();
            $this->line('Pliki bez bloku:');

            foreach ($reading->filesWithoutBlock as $file) {
                $this->line("  {$file}");
            }
        }

        if ($this->output->isVerbose() && $reading->sourceFilesWithoutBlock !== []) {
            $this->newLine();
            $this->line('Pliki źródeł bez bloku:');

            foreach ($reading->sourceFilesWithoutBlock as $file) {
                $this->line("  {$file}");
            }
        }
    }

    /**
     * The lines about zrodla/ and the table of the scans that were left out. A repo without zrodla/ prints nothing.
     */
    private function sourceReport(ResearchRepoReading $reading): void
    {
        if ($reading->sourceFilesScanned === 0 && $reading->skippedSources === []) {
            return;
        }

        $withoutBlock = count($reading->sourceFilesWithoutBlock);

        $this->newLine();
        $this->line(sprintf('Pliki źródeł przejrzane: %d (z blokiem: %d, bez bloku: %d)', $reading->sourceFilesScanned, $reading->sourceFilesScanned - $withoutBlock, $withoutBlock));
        $this->line(sprintf('Skany w blokach: %d, pominięte: %d', $reading->sourceScanEntries, $reading->sourceScanEntries - count($reading->sources)));

        if ($reading->skippedSources !== []) {
            $this->newLine();
            $this->line('Pominięte skany:');
            $this->table(
                ['Plik', 'ID', 'Powód'],
                array_map(fn (ImportIssue $issue): array => [$issue->file, $issue->repoId ?? '—', $issue->message], $reading->skippedSources),
            );
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<ImportIssue>  $issues
     */
    private function issueTable(string $title, array $headers, array $issues): void
    {
        if ($issues === []) {
            return;
        }

        $this->newLine();
        $this->line($title.':');
        $this->table($headers, array_map(fn (ImportIssue $issue): array => [$issue->file, $issue->message], $issues));
    }
}
