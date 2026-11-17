<?php

namespace App\Actions\Import;

use App\Enums\Gender;
use App\Support\Import\ImportIssue;
use App\Support\Import\ParsedPersonBlock;
use App\Support\Import\RepoKeys;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchMarriage;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\ResearchSource;
use App\Support\Import\ResearchSourceLink;
use ErrorException;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use UnexpectedValueException;

class ReadResearchRepo
{
    public function __construct(
        private readonly ParsePersonBlock $parsePersonBlock,
        private readonly ParseSourceBlock $parseSourceBlock,
    ) {}

    /**
     * Read every person block of drzewo/ and every source block of zrodla/ of a research repo without writing anything.
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
        $unrecognizedBlocks = [];
        $parsedPersons = [];
        $files = $this->personFiles($treePath);

        foreach ($files as $path) {
            $file = basename($path);

            if (! is_readable($path)) {
                $skipped[] = new ImportIssue($file, 'Nie udało się odczytać pliku');

                continue;
            }

            $parsed = $this->parsePersonBlock->handle($file, File::get($path));

            array_push($warnings, ...$parsed->warnings);

            if (! $parsed->hasBlock()) {
                $filesWithoutBlock[] = $file;
                array_push($unrecognizedBlocks, ...$parsed->warnings);
            } elseif ($parsed->skipReason !== null) {
                $skipped[] = new ImportIssue($file, $parsed->skipReason, $parsed->repoId);
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
        $droppedMarriageKeys = [];
        $marriages = $this->resolveMarriages($persons, $warnings, $droppedMarriageKeys);
        $sourceReading = $this->readSources($repoPath, $warnings);
        $this->checkSourceLinks($persons, $marriages, $sourceReading['sources'], $warnings);

        return new ResearchRepoReading(
            persons: $persons,
            marriages: $marriages,
            skipped: $skipped,
            warnings: $warnings,
            filesWithoutBlock: $filesWithoutBlock,
            scannedFiles: count($files),
            parentChildRelations: $relations,
            unrecognizedBlocks: $unrecognizedBlocks,
            droppedMarriageKeys: $droppedMarriageKeys,
            sources: $sourceReading['sources'],
            skippedSources: $sourceReading['skipped'],
            sourceFilesScanned: $sourceReading['scanned'],
            sourceFilesWithoutBlock: $sourceReading['withoutBlock'],
            sourceScanEntries: $sourceReading['entries'],
            hasSourcesDirectory: is_dir(rtrim($repoPath, '/').'/zrodla'),
        );
    }

    /**
     * Read the blocks under zrodla/; a missing directory means no sources.
     *
     * @param  list<ImportIssue>  $warnings
     * @return array{sources: array<string, ResearchSource>, skipped: list<ImportIssue>, scanned: int, withoutBlock: list<string>, entries: int}
     */
    private function readSources(string $repoPath, array &$warnings): array
    {
        $root = rtrim($repoPath, '/');
        $realRoot = realpath($root) ?: $root;
        $files = $this->sourceFiles($root.'/zrodla');
        $skipped = [];
        $withoutBlock = [];
        $entries = 0;
        $parsed = [];

        foreach ($files as $path) {
            $file = substr($path, strlen($root) + 1);
            $real = realpath($path);

            if ($real === false || ! str_starts_with($real, $realRoot.'/')) {
                $skipped[] = new ImportIssue($file, 'Plik poza repo');

                continue;
            }

            if (! is_readable($path)) {
                $skipped[] = new ImportIssue($file, 'Nie udało się odczytać pliku');

                continue;
            }

            $block = $this->parseSourceBlock->handle($root, $file, File::get($path));

            if (! $block->hasBlock) {
                $withoutBlock[] = $file;

                continue;
            }

            $entries += $block->entries;
            array_push($skipped, ...$block->skipped);
            array_push($warnings, ...$block->warnings);
            array_push($parsed, ...$block->sources);
        }

        $filesById = [];
        $entryIds = [];

        foreach ($parsed as $source) {
            $filesById[$source->id][$source->file] = true;
            $entryIds[] = $source->id;
        }

        foreach ($skipped as $issue) {
            if ($issue->repoId !== null) {
                $filesById[$issue->repoId][$issue->file] = true;
                $entryIds[] = $issue->repoId;
            }
        }

        $countById = array_count_values($entryIds);
        $sources = [];

        foreach ($parsed as $source) {
            if ($countById[$source->id] > 1) {
                $files = array_keys($filesById[$source->id]);
                sort($files);
                $skipped[] = new ImportIssue($source->file, sprintf(
                    'Powtórzony id %s w plikach: %s',
                    $source->id,
                    implode(', ', $files),
                ), $source->id);

                continue;
            }

            $sources[$source->id] = $source;
        }

        ksort($sources);

        $byScan = [];

        foreach ($sources as $source) {
            $byScan[$source->scanAbsolutePath][] = $source;
        }

        foreach ($byScan as $group) {
            if (count($group) > 1) {
                $warnings[] = new ImportIssue(
                    implode(', ', array_unique(array_map(fn (ResearchSource $source): string => $source->file, $group))),
                    sprintf('Ten sam plik skanu %s mają źródła: %s', $group[0]->scanPath, implode(', ', array_map(fn (ResearchSource $source): string => $source->id, $group))),
                );
            }
        }

        return ['sources' => $sources, 'skipped' => $skipped, 'scanned' => count($files), 'withoutBlock' => $withoutBlock, 'entries' => $entries];
    }

    /**
     * The .md files under zrodla/ at any depth, without the index files that start with an underscore.
     *
     * @return list<string>
     */
    private function sourceFiles(string $sourcesPath): array
    {
        if (! is_dir($sourcesPath)) {
            return [];
        }

        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourcesPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            foreach ($iterator as $item) {
                $name = $item->getFilename();

                if (str_ends_with($name, '.md') && ! str_starts_with($name, '_') && $item->isFile()) {
                    $files[] = $item->getPathname();
                }
            }
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException('Nie udało się odczytać katalogu zrodla/: '.$sourcesPath, previous: $exception);
        }

        sort($files);

        return $files;
    }

    /**
     * A fact that cites a source without a valid entry keeps the citation; the planner decides what to compare.
     *
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ResearchMarriage>  $marriages
     * @param  array<string, ResearchSource>  $sources
     * @param  list<ImportIssue>  $warnings
     */
    private function checkSourceLinks(array $persons, array $marriages, array $sources, array &$warnings): void
    {
        $check = function (ResearchEvent $event, string $file, string $label) use ($sources, &$warnings): void {
            foreach ($event->sources as $link) {
                if (! isset($sources[$link->id])) {
                    $warnings[] = new ImportIssue($file, "{$label}: źródło {$link->id} nie istnieje albo zostało pominięte");
                }
            }
        };

        foreach ($persons as $person) {
            foreach (['urodzenie' => $person->birth, 'zgon' => $person->death] as $label => $event) {
                if ($event !== null) {
                    $check($event, $person->file, "Pole {$label}.zrodla");
                }
            }
        }

        foreach ($marriages as $marriage) {
            $check($marriage->event, $marriage->files[0], "Małżeństwo {$marriage->personIds[0]} i {$marriage->personIds[1]}, pole zrodla");
        }
    }

    /**
     * @return list<string>
     */
    private function personFiles(string $treePath): array
    {
        $files = [];

        try {
            $names = scandir($treePath);
        } catch (ErrorException $exception) {
            throw new RuntimeException('Nie udało się odczytać katalogu drzewo/: '.$treePath, previous: $exception);
        }

        foreach ($names ?: [] as $name) {
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
                ), $person->id);

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
     * A marriage dropped with a warning because the files disagree or the pair is written twice in one file still
     * exists in the repo, so its key is collected in `$dropped` and not treated as removed by a re-import.
     *
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ImportIssue>  $warnings
     * @param  list<string>  $dropped  repo keys of the dropped marriages between two persons that have a valid block
     * @return list<ResearchMarriage>
     */
    private function resolveMarriages(array $persons, array &$warnings, array &$dropped): array
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
                $this->collectDropped($key, $persons, $dropped);

                continue;
            }

            $marriage = $this->resolvePair($key, $entries, $persons, $warnings, $dropped);

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
     * @param  list<string>  $dropped
     */
    private function resolvePair(string $key, array $entries, array $persons, array &$warnings, array &$dropped): ?ResearchMarriage
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

        if ($entries[0]->event->withSources([]) != $entries[1]->event->withSources([])) {
            $warnings[] = new ImportIssue(
                implode(', ', [$entries[0]->files[0], $entries[1]->files[0]]),
                "Małżeństwo {$first} i {$second}: dane (data, miejsce, poziom, notatka) różnią się w obu plikach; małżeństwo pominięte",
            );
            $this->collectDropped($key, $persons, $dropped);

            return null;
        }

        $sources = $this->mergeSources($entries[0]->event->sources, $entries[1]->event->sources);

        if ($sources === null) {
            $warnings[] = new ImportIssue(
                implode(', ', [$entries[0]->files[0], $entries[1]->files[0]]),
                sprintf(
                    'Małżeństwo %s i %s: zrodla różnią się w obu plikach (%s oraz %s); małżeństwo pominięte',
                    $first,
                    $second,
                    $this->sourceList($entries[0]->event->sources),
                    $this->sourceList($entries[1]->event->sources),
                ),
            );
            $this->collectDropped($key, $persons, $dropped);

            return null;
        }

        return new ResearchMarriage($firstEntry->personIds, $firstEntry->event->withSources($sources), [$entries[0]->files[0], $entries[1]->files[0]]);
    }

    /**
     * The source lists of the two files of a wedding: an empty list takes the other one, two lists must be equal.
     *
     * @param  list<ResearchSourceLink>  $first
     * @param  list<ResearchSourceLink>  $second
     * @return list<ResearchSourceLink>|null `null` when both lists are filled and differ
     */
    private function mergeSources(array $first, array $second): ?array
    {
        if ($first === []) {
            return $second;
        }

        if ($second === [] || $first == $second) {
            return $first;
        }

        return null;
    }

    /**
     * @param  list<ResearchSourceLink>  $links
     */
    private function sourceList(array $links): string
    {
        return '['.implode(', ', array_map(fn (ResearchSourceLink $link): string => $link->id.($link->entry === null ? '' : " ({$link->entry})"), $links)).']';
    }

    /**
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<string>  $dropped
     */
    private function collectDropped(string $pairKey, array $persons, array &$dropped): void
    {
        [$first, $second] = explode('|', $pairKey);

        if (isset($persons[$first], $persons[$second])) {
            $dropped[] = RepoKeys::marriage($first, $second);
        }
    }
}
