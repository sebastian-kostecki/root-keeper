<?php

namespace App\Console\Commands;

use App\Actions\Import\ApplyImport;
use App\Actions\Import\MatchResearchPersons;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\DateQualifier;
use App\Enums\MatchVerdict;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\PersonMatch;
use App\Support\Import\PersonMatches;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
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

    public function handle(ReadResearchRepo $readResearchRepo, MatchResearchPersons $matchResearchPersons, PlanImport $planImport, ApplyImport $applyImport): int
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

        $matches = $matchResearchPersons->handle($tree, $reading);

        if ($matches->blockedReason !== null) {
            $this->error($matches->blockedReason);

            return self::FAILURE;
        }

        $this->matchSummary($matches);

        if ($this->needsAnswers($matches) && ! $this->input->isInteractive()) {
            $this->error('Dopasowanie wymaga odpowiedzi w terminalu; nic nie zostało zapisane.');

            return self::FAILURE;
        }

        $decisions = $this->decideIdentities($matches);

        try {
            $plan = $planImport->handle($tree, $reading, $decisions);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summary($plan, $reading);

        if ($plan->blockedReason !== null) {
            $this->error($plan->blockedReason);

            return self::FAILURE;
        }

        $newCount = count($plan->persons);
        $matchedCount = count($plan->matched);

        if ($newCount + $matchedCount === 0) {
            $this->info('Nic do zapisania.');

            return self::SUCCESS;
        }

        $resolutions = $this->resolveConflicts($plan);

        $this->newLine();

        if (! $this->confirm("Zapisać do drzewa konta {$email}: nowe osoby {$newCount}, dopasowane {$matchedCount}?", false)) {
            $this->info('Nic nie zostało zapisane.');

            return self::SUCCESS;
        }

        if (app()->isProduction()
            && $this->call('backup:run', ['--only-db' => true, '--disable-notifications' => true]) !== self::SUCCESS) {
            $this->error('Kopia bazy nie powiodła się, nic nie zostało zapisane.');

            return self::FAILURE;
        }

        try {
            $result = $applyImport->handle($tree, $plan, $resolutions);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            $this->error('Nic nie zostało zapisane.');

            return self::FAILURE;
        }

        $this->written($result, $plan, $tree);

        return self::SUCCESS;
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
            $choices[$conflict->key] = $this->choose(
                sprintf('Konflikt %d/%d: %s — %s', $index + 1, $total, $conflict->person, $conflict->label),
                [
                    ConflictResolutions::FROM_APP => Str::limit("Zostaw z aplikacji: {$conflict->appLabel}", self::OPTION_WIDTH),
                    ConflictResolutions::FROM_REPO => Str::limit("Weź z repo: {$conflict->repoLabel}", self::OPTION_WIDTH),
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
        $this->line('Do notatki osoby:');
        $this->line(sprintf('  miejsca: %d (spis miejsc — S-05)', $notes->places));
        $this->line(sprintf('  pochowania: %d (cmentarze i pochowanie — S-05)', $notes->burials));
        $this->line(sprintf('  poziomy pewności: %d (S-08)', $notes->levels));
        $this->line(sprintf('  notatki wydarzeń: %d', $notes->eventNotes));
        $this->line(sprintf('  warianty nazwiska: %d (S-09)', $notes->surnameVariants));

        $this->issueTable('Pominięte osoby', ['Plik', 'Powód'], $plan->skippedPersons);
        $this->issueTable('Pominięte relacje', ['Plik', 'Powód'], $plan->skippedRelations);
        $this->issueTable('Pominięte małżeństwa', ['Plik', 'Powód'], $plan->skippedMarriages);
        $this->issueTable('Pominięte wartości', ['Plik', 'Powód'], $plan->skippedValues);
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

        foreach ($plan->marriages as $planned) {
            if ($planned->dateFill === null) {
                continue;
            }

            $spouses = array_map(
                fn (string $repoId): string => isset($reading->persons[$repoId]) ? sprintf('%s (%s)', $this->researchName($reading->persons[$repoId]), $repoId) : $repoId,
                $planned->marriage->personIds,
            );

            $rows[] = [implode(' i ', $spouses), $planned->dateFill->label, $planned->dateFill->valueLabel()];
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
        $this->line(sprintf('Wydarzenia: %d (urodzenia: %d, zgony: %d)', $result->events, $result->births, $result->deaths));

        if ($tree->root_person_id !== null) {
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

        if ($this->output->isVerbose() && $reading->filesWithoutBlock !== []) {
            $this->newLine();
            $this->line('Pliki bez bloku:');

            foreach ($reading->filesWithoutBlock as $file) {
                $this->line("  {$file}");
            }
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
