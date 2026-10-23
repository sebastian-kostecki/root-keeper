<?php

namespace App\Actions\Import;

use App\Enums\Gender;
use App\Support\Import\ImportIssue;
use App\Support\Import\ParsedPersonBlock;
use App\Support\Import\ResearchMarriage;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ReadResearchRepo
{
    public function __construct(private readonly ParsePersonBlock $parsePersonBlock) {}

    /**
     * Read every person block of a research repo without writing anything.
     *
     * @throws RuntimeException when the repo or its drzewo/ directory does not exist
     */
    public function handle(string $repoPath): ResearchRepoReading
    {
        $treePath = rtrim($repoPath, '/').'/drzewo';

        if (! is_dir($repoPath)) {
            throw new RuntimeException('Nie znaleziono katalogu repo: '.$repoPath);
        }

        if (! is_dir($treePath)) {
            throw new RuntimeException('Brak katalogu drzewo/ w repo: '.$repoPath);
        }

        $skipped = [];
        $warnings = [];
        $filesWithoutBlock = [];
        $parsedPersons = [];
        $files = $this->personFiles($treePath);

        foreach ($files as $path) {
            $file = basename($path);
            $parsed = $this->parsePersonBlock->handle($file, File::get($path));

            array_push($warnings, ...$parsed->warnings);

            if (! $parsed->hasBlock()) {
                $filesWithoutBlock[] = $file;
            } elseif ($parsed->skipReason !== null) {
                $skipped[] = new ImportIssue($file, $parsed->skipReason);
            } elseif ($parsed->person !== null) {
                $parsedPersons[] = $parsed;
            }
        }

        if ($files === []) {
            $warnings[] = new ImportIssue('drzewo/', 'Nie znaleziono żadnych plików .md osób (sprawdź ścieżkę repo)');
        }

        $persons = $this->dropDuplicateIds($parsedPersons, $skipped);
        ksort($persons);

        $relations = $this->checkRelations($persons, $warnings);
        $this->checkAhnentafel($persons, $warnings);
        $marriages = $this->resolveMarriages($persons, $warnings);

        return new ResearchRepoReading(
            persons: $persons,
            marriages: $marriages,
            skipped: $skipped,
            warnings: $warnings,
            filesWithoutBlock: $filesWithoutBlock,
            scannedFiles: count($files),
            parentChildRelations: $relations,
        );
    }

    /**
     * @return list<string>
     */
    private function personFiles(string $treePath): array
    {
        $files = [];

        foreach (scandir($treePath) ?: [] as $name) {
            $path = $treePath.'/'.$name;

            if (str_ends_with($name, '.md') && ! str_starts_with($name, '_') && is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  list<ParsedPersonBlock>  $parsedPersons
     * @param  list<ImportIssue>  $skipped
     * @return array<string, ResearchPerson>
     */
    private function dropDuplicateIds(array $parsedPersons, array &$skipped): array
    {
        $filesById = [];

        foreach ($parsedPersons as $parsed) {
            $filesById[$parsed->person->id][] = $parsed->file;
        }

        $persons = [];

        foreach ($parsedPersons as $parsed) {
            $person = $parsed->person;

            if (count($filesById[$person->id]) > 1) {
                $skipped[] = new ImportIssue($parsed->file, sprintf(
                    'Powtórzony id %s w plikach: %s',
                    $person->id,
                    implode(', ', $filesById[$person->id]),
                ));

                continue;
            }

            $persons[$person->id] = $person;
        }

        return $persons;
    }

    /**
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ImportIssue>  $warnings
     */
    private function checkRelations(array $persons, array &$warnings): int
    {
        $resolved = 0;

        foreach ($persons as $person) {
            foreach (['ojciec' => [$person->fatherId, Gender::Female], 'matka' => [$person->motherId, Gender::Male]] as $role => [$parentId, $wrongGender]) {
                if ($parentId === null) {
                    continue;
                }

                $parent = $persons[$parentId] ?? null;

                if ($parent === null) {
                    $warnings[] = new ImportIssue($person->file, "Pole rodzice.{$role}: {$parentId} nie ma poprawnego bloku w repo");

                    continue;
                }

                $resolved++;

                if ($parent->gender === $wrongGender) {
                    $warnings[] = new ImportIssue($person->file, "Pole rodzice.{$role}: {$parentId} ma płeć niezgodną z rolą");
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ImportIssue>  $warnings
     */
    private function checkAhnentafel(array $persons, array &$warnings): void
    {
        $holders = [];

        foreach ($persons as $person) {
            foreach (array_unique($person->ahnentafel) as $number) {
                $holders[$number][] = $person;
            }
        }

        ksort($holders);

        foreach ($holders as $number => $people) {
            if (count($people) > 1) {
                $warnings[] = new ImportIssue(
                    implode(', ', array_map(fn (ResearchPerson $person): string => $person->file, $people)),
                    sprintf('Numer ahnentafel %d mają różne osoby: %s', $number, implode(', ', array_map(fn (ResearchPerson $person): string => $person->id, $people))),
                );
            }
        }
    }

    /**
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ImportIssue>  $warnings
     * @return list<ResearchMarriage>
     */
    private function resolveMarriages(array $persons, array &$warnings): array
    {
        /** @var array<string, list<ResearchMarriage>> $byPair */
        $byPair = [];
        /** @var array<string, true> $repeated */
        $repeated = [];

        foreach ($persons as $person) {
            $seen = [];

            foreach ($person->marriages as $marriage) {
                $key = implode('|', $marriage->personIds);

                if (isset($seen[$key])) {
                    $repeated[$key] = true;
                    $warnings[] = new ImportIssue($person->file, "Małżeństwo {$marriage->personIds[0]} i {$marriage->personIds[1]} zapisane w pliku więcej niż raz; małżeństwo pominięte");

                    continue;
                }

                $seen[$key] = true;
                $byPair[$key][] = $marriage;
            }
        }

        ksort($byPair);

        $marriages = [];

        foreach ($byPair as $key => $entries) {
            if (isset($repeated[$key])) {
                continue;
            }

            $marriage = $this->resolvePair($key, $entries, $persons, $warnings);

            if ($marriage !== null) {
                $marriages[] = $marriage;
            }
        }

        return $marriages;
    }

    /**
     * @param  list<ResearchMarriage>  $entries
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ImportIssue>  $warnings
     */
    private function resolvePair(string $key, array $entries, array $persons, array &$warnings): ?ResearchMarriage
    {
        [$first, $second] = explode('|', $key);
        $firstEntry = $entries[0];

        foreach ([$first, $second] as $id) {
            if (! isset($persons[$id])) {
                $partner = $id === $first ? $second : $first;
                $warnings[] = new ImportIssue(
                    $firstEntry->files[0],
                    "Małżeństwo z {$id}: osoba nie ma poprawnego bloku w repo; małżeństwo pominięte (zapisane w pliku osoby {$partner})",
                );

                return null;
            }
        }

        if (count($entries) === 1) {
            $warnings[] = new ImportIssue(
                $firstEntry->files[0],
                "Małżeństwo {$first} i {$second} zapisane tylko w tym pliku (brak wpisu u współmałżonka)",
            );

            return $firstEntry;
        }

        if ($entries[0]->event != $entries[1]->event) {
            $warnings[] = new ImportIssue(
                implode(', ', [$entries[0]->files[0], $entries[1]->files[0]]),
                "Małżeństwo {$first} i {$second}: dane (data, miejsce, poziom, notatka) różnią się w obu plikach; małżeństwo pominięte",
            );

            return null;
        }

        return new ResearchMarriage($firstEntry->personIds, $firstEntry->event, [$entries[0]->files[0], $entries[1]->files[0]]);
    }
}
