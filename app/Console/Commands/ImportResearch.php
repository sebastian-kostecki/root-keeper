<?php

namespace App\Console\Commands;

use App\Actions\Import\ReadResearchRepo;
use App\Models\User;
use App\Support\Import\ImportIssue;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('import:research
    {email : E-mail konta, do którego drzewa trafi import}
    {path : Katalog repo badawczego}')]
#[Description('Odczytaj bloki danych osób z repo badawczego i wypisz raport (nic nie zapisuje)')]
class ImportResearch extends Command
{
    public function handle(ReadResearchRepo $readResearchRepo): int
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

        return self::SUCCESS;
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

        $this->newLine();
        $this->info('Nic nie zostało zapisane.');
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
