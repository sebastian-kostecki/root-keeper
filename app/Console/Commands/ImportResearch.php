<?php

namespace App\Console\Commands;

use App\Actions\Import\ApplyFirstImport;
use App\Actions\Import\PlanFirstImport;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\DateQualifier;
use App\Models\User;
use App\Support\Import\FirstImportPlan;
use App\Support\Import\FirstImportResult;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('import:research
    {email : E-mail konta, do którego drzewa trafi import}
    {path : Katalog repo badawczego}')]
#[Description('Zaimportuj przodków z repo badawczego do pustego drzewa konta')]
class ImportResearch extends Command
{
    private const BLOCKED_REASON = 'Drzewo konta ma już osoby. Import do drzewa z osobami (dopasowanie — S-03, ponowny import — S-04) jeszcze nie działa; nic nie zostanie zapisane.';

    public function handle(ReadResearchRepo $readResearchRepo, PlanFirstImport $planFirstImport, ApplyFirstImport $applyFirstImport): int
    {
        $email = (string) $this->argument('email');
        $path = (string) $this->argument('path');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error('Nie znaleziono konta dla adresu: '.$email);

            return self::FAILURE;
        }

        if ($user->tree === null) {
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

        if ($user->tree->people()->exists()) {
            $this->error(self::BLOCKED_REASON);

            return self::FAILURE;
        }

        $plan = $planFirstImport->handle($user->tree, $reading, IdentityDecisions::allNew($reading));

        $this->summary($plan);

        if ($plan->blockedReason !== null) {
            $this->error($plan->blockedReason);

            return self::FAILURE;
        }

        $personCount = count($plan->persons);

        if ($personCount === 0) {
            $this->info('Nic do zapisania.');

            return self::SUCCESS;
        }

        $this->newLine();

        if (! $this->confirm("Zapisać {$personCount} osób do drzewa konta {$email}?", false)) {
            $this->info('Nic nie zostało zapisane.');

            return self::SUCCESS;
        }

        if (app()->isProduction()
            && $this->call('backup:run', ['--only-db' => true, '--disable-notifications' => true]) !== self::SUCCESS) {
            $this->error('Kopia bazy nie powiodła się, nic nie zostało zapisane.');

            return self::FAILURE;
        }

        try {
            $result = $applyFirstImport->handle($user->tree, $plan);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            $this->error('Nic nie zostało zapisane.');

            return self::FAILURE;
        }

        $this->written($result, $plan);

        return self::SUCCESS;
    }

    private function summary(FirstImportPlan $plan): void
    {
        $this->newLine();
        $this->info('Podsumowanie importu');
        $this->line(sprintf('Osoby: %d', count($plan->persons)));
        $this->line(sprintf('Relacje rodzic–dziecko: %d do zapisania, %d pominiętych', count($plan->relations), count($plan->skippedRelations)));
        $this->line(sprintf('Małżeństwa: %d', count($plan->marriages)));
        $this->line(sprintf('Urodzenia: %d, zgony: %d, śluby: %d', $plan->births, $plan->deaths, $plan->weddings));

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

        $this->issueTable('Pominięte relacje', ['Plik', 'Powód'], $plan->skippedRelations);
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

    private function written(FirstImportResult $result, FirstImportPlan $plan): void
    {
        $this->newLine();
        $this->info('Zapisano w drzewie:');
        $this->line(sprintf('Osoby: %d', $result->persons));
        $this->line(sprintf('Relacje rodzic–dziecko: %d', $result->relations));
        $this->line(sprintf('Małżeństwa: %d', $result->marriages));
        $this->line(sprintf('Wydarzenia: %d (urodzenia: %d, zgony: %d)', $result->events, $result->births, $result->deaths));

        $proband = 'Import nie ustawia probanta — wybierz go w aplikacji (Drzewo → Wybierz probanta).';

        if ($plan->probandHint !== null) {
            $name = trim(($plan->probandHint->givenNames ?? '').' '.($plan->probandHint->surname ?? ''));
            $proband .= sprintf(' Ahnentafel 1: %s (%s).', $name === '' ? 'NN' : $name, $plan->probandHint->id);
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
