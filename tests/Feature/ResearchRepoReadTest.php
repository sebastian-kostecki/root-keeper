<?php

use App\Actions\Import\ParsePersonBlock;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\ConfidenceLevel;
use App\Enums\DateQualifier;
use App\Enums\Gender;
use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Support\Import\ImportIssue;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Support\Facades\File;

function readFixtureRepo(): ResearchRepoReading
{
    return app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));
}

/**
 * @param  list<ImportIssue>  $issues
 */
function messagesFor(array $issues, string $file): string
{
    return implode(' | ', array_map(
        fn (ImportIssue $issue): string => $issue->message,
        array_filter($issues, fn (ImportIssue $issue): bool => str_contains($issue->file, $file)),
    ));
}

test('reads the persons of the fixture repo and counts relations and marriages', function () {
    $reading = readFixtureRepo();

    expect($reading->scannedFiles)->toBe(33)
        ->and($reading->persons)->toHaveCount(13)
        ->and(array_keys($reading->persons))->toBe(array_map(fn (int $n): string => sprintf('O-%04d', $n), range(1, 13)))
        ->and($reading->parentChildRelations)->toBe(4)
        ->and($reading->marriages)->toHaveCount(3)
        ->and($reading->skipped)->toHaveCount(18)
        ->and($reading->filesWithoutBlock)->toBe(['bez_bloku.md', 'przesuniety_blok.md']);
});

test('maps the block fields onto the person', function () {
    $jan = readFixtureRepo()->persons['O-0001'];

    expect($jan->givenNames)->toBe('Jan')
        ->and($jan->surname)->toBe('Nowak')
        ->and($jan->surnameVariants)->toBe(['Nowakowski'])
        ->and($jan->gender)->toBe(Gender::Male)
        ->and($jan->ahnentafel)->toBe([1])
        ->and([$jan->fatherId, $jan->motherId])->toBe(['O-0002', 'O-0003'])
        ->and($jan->birth->date->day)->toBe(12)
        ->and($jan->birth->level)->toBe(ConfidenceLevel::A)
        ->and($jan->birth->note)->toBe('24 IV 1801 st.st.')
        ->and($jan->death->date->year)->toBe(1870)
        ->and($jan->burial->date)->toBeNull()
        ->and($jan->burial->place)->toBe('Zalesie')
        ->and($jan->burial->cemetery)->toBeNull()
        ->and($jan->burial->level)->toBe(ConfidenceLevel::C);
});

test('keeps an implex person with two ahnentafel numbers and a death without a date', function () {
    $reading = readFixtureRepo();

    expect($reading->persons['O-0006']->ahnentafel)->toBe([5, 15])
        ->and($reading->persons['O-0002']->death)->not->toBeNull()
        ->and($reading->persons['O-0002']->death->date)->toBeNull()
        ->and($reading->persons['O-0002']->birth->date->qualifier)->toBe(DateQualifier::Range)
        ->and($reading->persons['O-0001']->death)->not->toBeNull()
        ->and($reading->persons['O-0004']->birth)->toBeNull();
});

test('skips each malformed block with a reason that names the problem', function (string $file, string $reason) {
    expect(messagesFor(readFixtureRepo()->skipped, $file))->toContain($reason);
})->with([
    ['skip_yaml.md', 'Błąd składni YAML w linii'],
    ['skip_niezamkniety.md', 'Niezamknięty frontmatter'],
    ['skip_lista.md', 'Blok nie jest mapą'],
    ['skip_brak_wersji.md', 'Brak pola wersja'],
    ['skip_zla_wersja.md', 'tylko wersja bloku 1'],
    ['skip_brak_id.md', 'Brak pola id'],
    ['skip_zle_id.md', 'O-NNNN'],
    ['skip_brak_nazwy.md', 'Brak imion i nazwiska'],
    ['skip_zla_plec.md', 'plec musi mieć wartość M albo K'],
    ['skip_zly_ahnentafel.md', 'ahnentafel.0'],
    ['skip_zla_data.md', 'urodzenie.data'],
    ['skip_data_bez_cudzyslowu.md', 'ujmij datę w cudzysłów'],
    ['skip_zly_poziom.md', 'zgon.poziom'],
    ['skip_zle_odwolanie.md', 'rodzice.ojciec'],
    ['skip_wlasne_id.md', 'tę samą osobę'],
    ['skip_zly_typ.md', 'imiona musi być tekstem'],
]);

test('skips both entries that share an id and names both files', function () {
    $reading = readFixtureRepo();

    expect($reading->persons)->not->toHaveKey('O-0035')
        ->and(messagesFor($reading->skipped, 'duplikat_a.md'))->toContain('duplikat_a.md, duplikat_b.md')
        ->and(messagesFor($reading->skipped, 'duplikat_b.md'))->toContain('O-0035');
});

test('ignores index files that start with an underscore', function () {
    $reading = readFixtureRepo();

    expect($reading->persons)->not->toHaveKey('O-0900')
        ->and($reading->filesWithoutBlock)->not->toContain('_PRZODKOWIE.md');
});

test('warns about unknown keys, dangling references and a wrong parent gender', function () {
    $warnings = readFixtureRepo()->warnings;

    expect(messagesFor($warnings, 'nieznany_klucz.md'))->toContain('Nieznany klucz: kolor')->toContain('Nieznany klucz: urodzenie.rok')
        ->and(messagesFor($warnings, 'wrona_adam.md'))->toContain('O-0098 nie ma poprawnego bloku')
        ->and(messagesFor($warnings, 'kos_ewa.md'))->toContain('płeć niezgodną z rolą')
        ->and(messagesFor($warnings, 'ten_sam_numer.md'))->toContain('Numer ahnentafel 4');
});

test('reads the cemetery name of a burial and adds a death without a date for a burial without a death', function () {
    $reading = readTemporaryFiles([
        'jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\npochowek:\n  miejsce: Dolina\n  cmentarz: Stary cmentarz\n---\n",
        'anna.md' => "---\nwersja: 1\nid: O-0002\nimiona: Anna\nzgon:\n  data: \"1870\"\npochowek:\n  miejsce: Dolina\n---\n",
    ]);
    $jan = $reading->persons['O-0001'];

    expect($jan->burial->place)->toBe('Dolina')
        ->and($jan->burial->cemetery)->toBe('Stary cmentarz')
        ->and($jan->death)->not->toBeNull()
        ->and([$jan->death->date, $jan->death->place, $jan->death->cemetery])->toBe([null, null, null])
        ->and(messagesFor($reading->warnings, 'jan.md'))->toContain('pochowanie bez zgonu: dodano zgon bez daty')
        ->and($reading->persons['O-0002']->death->date->year)->toBe(1870)
        ->and($reading->persons['O-0002']->burial->cemetery)->toBeNull()
        ->and(messagesFor($reading->warnings, 'anna.md'))->not->toContain('pochowanie bez zgonu');
});

test('warns about a place that reads as a description and keeps its value', function () {
    $reading = readFixtureRepo();

    expect(messagesFor($reading->warnings, 'lis_marta.md'))->toContain('miejsce wygląda na opis, nie nazwę miejscowości')
        ->and($reading->persons['O-0003']->birth->place)->toBe('Dolina (par. Uchanie)')
        ->and(messagesFor($reading->warnings, 'nowak_jan_1801.md'))->not->toContain('miejsce wygląda na opis');
});

test('warns about a place with a comma or a parish marker, but not about a plain name', function (string $place, bool $warns) {
    $parsed = app(ParsePersonBlock::class)->handle('miejsce.md', "---\nwersja: 1\nid: O-0104\nimiona: Jan\nurodzenie:\n  miejsce: \"{$place}\"\n---\n");
    $messages = implode(' | ', array_map(fn (ImportIssue $issue): string => $issue->message, $parsed->warnings));

    expect(str_contains($messages, 'miejsce wygląda na opis'))->toBe($warns)
        ->and($parsed->person->birth->place)->toBe($place);
})->with([
    'comma' => ['Zalesie, gm. Uchanie', true],
    'parish abbreviation' => ['Parafia Uchanie', true],
    'par. marker' => ['Uchanie par. Zalesie', true],
    'plain name' => ['Zalesie', false],
    'a name with a hyphen and a space' => ['Nowy Dwór-Kolonia', false],
]);

test('treats the cemetery key as unknown outside of a burial', function () {
    $reading = readFixtureRepo();

    expect(messagesFor($reading->warnings, 'nieznany_klucz.md'))->toContain('Nieznany klucz: urodzenie.cmentarz')
        ->and($reading->persons['O-0012']->birth->cemetery)->toBeNull();
});

test('warns about a block that does not start on the first line', function () {
    expect(messagesFor(readFixtureRepo()->warnings, 'przesuniety_blok.md'))->toContain('nie zaczyna się w pierwszej linii');
});

test('joins a marriage written consistently in both files', function () {
    $marriages = collect(readFixtureRepo()->marriages)->keyBy(fn ($marriage): string => implode('+', $marriage->personIds));

    expect($marriages)->toHaveKeys(['O-0001+O-0004', 'O-0002+O-0003'])
        ->and($marriages['O-0001+O-0004']->files)->toBe(['nowak_jan_1801.md', 'wrona_anna.md'])
        ->and($marriages['O-0001+O-0004']->event->date->qualifier)->toBe(DateQualifier::Approximate)
        ->and($marriages['O-0002+O-0003']->event->date->year)->toBe(1798);
});

test('recognizes a one-sided marriage and warns about it', function () {
    $reading = readFixtureRepo();
    $keys = array_map(fn ($marriage): string => implode('+', $marriage->personIds), $reading->marriages);

    expect($keys)->toContain('O-0007+O-0008')
        ->and(messagesFor($reading->warnings, 'wrona_adam.md'))->toContain('tylko w tym pliku');
});

test('drops a marriage whose two files disagree', function () {
    $reading = readFixtureRepo();
    $keys = array_map(fn ($marriage): string => implode('+', $marriage->personIds), $reading->marriages);

    expect($keys)->not->toContain('O-0005+O-0006')
        ->and(messagesFor($reading->warnings, 'kos_katarzyna.md'))->toContain('różnią się w obu plikach');
});

test('drops a marriage with a person that has no valid block', function () {
    $reading = readFixtureRepo();
    $keys = array_map(fn ($marriage): string => implode('+', $marriage->personIds), $reading->marriages);

    expect($keys)->not->toContain('O-0009+O-0099')
        ->and(messagesFor($reading->warnings, 'bez_pary_tomasz.md'))->toContain('O-0099');
});

test('drops a pair written twice in one file', function () {
    $reading = readFixtureRepo();
    $keys = array_map(fn ($marriage): string => implode('+', $marriage->personIds), $reading->marriages);

    expect($keys)->not->toContain('O-0010+O-0011')
        ->and(messagesFor($reading->warnings, 'podwojne_jerzy.md'))->toContain('więcej niż raz');
});

test('collects the keys of the weddings it dropped between two valid persons', function () {
    $reading = readFixtureRepo();

    expect($reading->droppedMarriageKeys)->toBe(['O-0005+O-0006/slub', 'O-0010+O-0011/slub'])
        ->and($reading->droppedMarriageKeys)->not->toContain('O-0009+O-0099/slub');
});

test('keeps no dropped key for a wedding that both files agree on', function () {
    $reading = readTemporaryFiles([
        'jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"\n---\n",
        'anna.md' => "---\nwersja: 1\nid: O-0002\nimiona: Anna\nmalzenstwa:\n  - z: O-0001\n    data: \"1825\"\n---\n",
    ]);

    expect($reading->marriages)->toHaveCount(1)
        ->and($reading->droppedMarriageKeys)->toBe([]);
});

test('fails with a Polish message when the repo or its drzewo directory is missing', function () {
    $reader = app(ReadResearchRepo::class);

    expect(fn () => $reader->handle(base_path('tests/Fixtures/nie-ma-takiego-repo')))->toThrow(RuntimeException::class, 'Nie znaleziono katalogu repo')
        ->and(fn () => $reader->handle(base_path('tests/Fixtures')))->toThrow(RuntimeException::class, 'Brak katalogu drzewo/');
});

test('parses a block with CRLF line endings and a BOM', function () {
    $contents = "\xEF\xBB\xBF".str_replace("\n", "\r\n", "---\nwersja: 1\nid: O-0100\nimiona: Jan\nurodzenie:\n  data: \"1801-05-12\"\n---\n\n# Jan\n");

    $parsed = app(ParsePersonBlock::class)->handle('crlf.md', $contents);

    expect($parsed->person->id)->toBe('O-0100')
        ->and($parsed->person->birth->date->month)->toBe(5);
});

test('skips an unquoted full date instead of shifting an impossible day', function (string $date) {
    $parsed = app(ParsePersonBlock::class)->handle('data.md', "---\nwersja: 1\nid: O-0101\nimiona: Jan\nurodzenie:\n  data: {$date}\n---\n");

    expect($parsed->person)->toBeNull()
        ->and($parsed->skipReason)->toContain('ujmij datę w cudzysłów');
})->with(['1801-02-31', '1801-05-12', '1801-5-7']);

test('treats a bare year and a quoted year the same', function () {
    $parse = fn (string $year) => app(ParsePersonBlock::class)->handle('rok.md', "---\nwersja: 1\nid: O-0102\nimiona: Jan\nurodzenie:\n  data: {$year}\n---\n");

    expect($parse('1801')->person->birth->date)->toEqual($parse('"1801"')->person->birth->date);
});

test('reports a file without a front matter as having no block', function () {
    $parsed = app(ParsePersonBlock::class)->handle('proza.md', "# Jan\n\nTylko proza.\n");

    expect($parsed->hasBlock())->toBeFalse()
        ->and($parsed->warnings)->toBe([]);
});

test('reads a repo whose path contains glob characters', function () {
    $repo = sys_get_temp_dir().'/repo [kopia] '.uniqid();
    mkdir($repo.'/drzewo', 0777, true);
    file_put_contents($repo.'/drzewo/jan.md', "---\nwersja: 1\nid: O-0200\nimiona: Jan\n---\n");

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        unlink($repo.'/drzewo/jan.md');
        rmdir($repo.'/drzewo');
        rmdir($repo);
    }

    expect($reading->scannedFiles)->toBe(1)
        ->and($reading->persons)->toHaveKey('O-0200');
});

test('warns when the drzewo directory holds no person files', function () {
    $repo = sys_get_temp_dir().'/repo-pusty-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        rmdir($repo.'/drzewo');
        rmdir($repo);
    }

    expect($reading->scannedFiles)->toBe(0)
        ->and(messagesFor($reading->warnings, 'drzewo'))->toContain('Nie znaleziono żadnych plików .md');
});

test('names the missing quotes when an unquoted date lands in a text field', function (string $field) {
    $parsed = app(ParsePersonBlock::class)->handle('pole.md', "---\nwersja: 1\nid: O-0103\nimiona: Jan\nurodzenie:\n  {$field}: 1801-05-12\n---\n");

    expect($parsed->person)->toBeNull()
        ->and($parsed->skipReason)->toContain("urodzenie.{$field}")->toContain('ujmij datę w cudzysłów');
})->with(['notatka', 'miejsce']);

/**
 * Reads a throwaway repo whose files are given by name and content.
 *
 * @param  array<string, string>  $files  file name => contents
 */
function readTemporaryFiles(array $files): ResearchRepoReading
{
    $repo = sys_get_temp_dir().'/read-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($files as $name => $contents) {
        file_put_contents("{$repo}/drzewo/{$name}", $contents);
    }

    try {
        return app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }
}

test('keeps the id of an entry skipped because of a bad date', function () {
    $reading = readTemporaryFiles(['jan.md' => "---\nwersja: 1\nid: O-0042\nimiona: Jan\nurodzenie:\n  data: \"1801-13-40\"\n---\n"]);

    expect($reading->skipped)->toHaveCount(1)
        ->and($reading->skipped[0]->repoId)->toBe('O-0042')
        ->and($reading->skippedRepoIds())->toBe(['O-0042'])
        ->and($reading->hasSkippedWithoutId())->toBeFalse();
});

test('keeps the id of an entry skipped for an unquoted date only when it fits the id pattern', function (string $id, ?string $expected) {
    $reading = readTemporaryFiles(['jan.md' => "---\nwersja: 1\nid: {$id}\nimiona: Jan\nurodzenie:\n  data: 1801-05-12\n---\n"]);

    expect($reading->skipped[0]->message)->toContain('ujmij datę w cudzysłów')
        ->and($reading->skipped[0]->repoId)->toBe($expected)
        ->and($reading->hasSkippedWithoutId())->toBe($expected === null);
})->with([
    'a valid id' => ['O-0042', 'O-0042'],
    'an id off the pattern' => ['X-42', null],
]);

test('gives no id to an entry skipped for a YAML error, an unclosed block or a block that is not a map', function (string $contents) {
    $reading = readTemporaryFiles(['jan.md' => $contents]);

    expect($reading->skipped)->toHaveCount(1)
        ->and($reading->skipped[0]->repoId)->toBeNull()
        ->and($reading->skippedRepoIds())->toBe([])
        ->and($reading->hasSkippedWithoutId())->toBeTrue();
})->with([
    'YAML error' => ["---\nwersja: 1\nid: O-0042\nimiona: [Jan\n---\n"],
    'unclosed block' => ["---\nwersja: 1\nid: O-0042\nimiona: Jan\n"],
    'list' => ["---\n- a\n- b\n---\n"],
]);

test('skips a person whose surname variant is longer than 255 characters and reads the rest', function () {
    $long = str_repeat('a', 256);
    $reading = readTemporaryFiles([
        'jan.md' => "---\nwersja: 1\nid: O-0042\nimiona: Jan\nwarianty_nazwiska: [Nowak, {$long}]\n---\n",
        'anna.md' => "---\nwersja: 1\nid: O-0043\nimiona: Anna\nwarianty_nazwiska: [Nowak]\n---\n",
    ]);

    expect(messagesFor($reading->skipped, 'jan.md'))->toContain('warianty_nazwiska.1')
        ->and($reading->skippedRepoIds())->toBe(['O-0042'])
        ->and(array_keys($reading->persons))->toBe(['O-0043']);
});

test('gives no id to an entry whose own id fails the validator', function () {
    $reading = readTemporaryFiles(['jan.md' => "---\nwersja: 1\nid: nie-id\nimiona: Jan\n---\n"]);

    expect($reading->skipped[0]->repoId)->toBeNull()
        ->and($reading->hasSkippedWithoutId())->toBeTrue();
});

test('gives the id to both entries that share it', function () {
    $block = "---\nwersja: 1\nid: O-0042\nimiona: Jan\n---\n";
    $reading = readTemporaryFiles(['a.md' => $block, 'b.md' => $block]);

    expect(array_map(fn (ImportIssue $issue): ?string => $issue->repoId, $reading->skipped))->toBe(['O-0042', 'O-0042'])
        ->and($reading->skippedRepoIds())->toBe(['O-0042'])
        ->and($reading->hasSkippedWithoutId())->toBeFalse();
});

test('counts a file whose block starts after an empty line as skipped with its id', function () {
    $reading = readTemporaryFiles(['jan.md' => "\n---\nwersja: 1\nid: O-0042\nimiona: Jan\n---\n"]);

    expect($reading->persons)->toBe([])
        ->and($reading->filesWithoutBlock)->toBe(['jan.md'])
        ->and($reading->skippedRepoIds())->toBe(['O-0042'])
        ->and($reading->hasSkippedWithoutId())->toBeFalse();
});

test('counts a file with a misplaced block and no readable id as skipped without an id', function () {
    $reading = readTemporaryFiles(['jan.md' => "Wstęp.\n\nwersja: 1\nimiona: Jan\n"]);

    expect($reading->skippedRepoIds())->toBe([])
        ->and($reading->hasSkippedWithoutId())->toBeTrue();
});

test('does not count a plain note without a block, nor files that are not scanned', function () {
    $reading = readTemporaryFiles([
        'proza.md' => "# Jan\n\nTylko proza.\n",
        '_indeks.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\n---\n",
        'notatki.txt' => "---\nwersja: 1\nid: O-0002\nimiona: Anna\n---\n",
    ]);

    expect($reading->filesWithoutBlock)->toBe(['proza.md'])
        ->and($reading->skippedRepoIds())->toBe([])
        ->and($reading->hasSkippedWithoutId())->toBeFalse();
});

test('reports an unreadable person file as skipped without an id and reads the rest', function () {
    $repo = sys_get_temp_dir().'/unreadable-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);
    file_put_contents($repo.'/drzewo/O-0001.md', "---\nwersja: 1\nid: O-0001\nimiona: Jan\nnazwisko: Nowak\nplec: M\n---\n");
    file_put_contents($repo.'/drzewo/O-0002.md', "---\nwersja: 1\nid: O-0002\nimiona: Anna\nnazwisko: Wrona\nplec: K\n---\n");
    chmod($repo.'/drzewo/O-0002.md', 0000);

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        chmod($repo.'/drzewo/O-0002.md', 0600);
        File::deleteDirectory($repo);
    }

    expect(array_keys($reading->persons))->toBe(['O-0001'])
        ->and($reading->skipped)->toHaveCount(1)
        ->and($reading->skipped[0]->file)->toBe('O-0002.md')
        ->and($reading->skipped[0]->repoId)->toBeNull()
        ->and($reading->hasSkippedWithoutId())->toBeTrue();
});

/**
 * Build a temporary repo with a drzewo/ and the given files (path => contents, relative to the repo root), read it and
 * return the reading with a hash of every file in the repo before and after.
 *
 * @param  array<string, string>  $files
 * @return array{reading: ResearchRepoReading, before: array<string, string>, after: array<string, string>, root: string}
 */
function readTemporaryRepo(array $files, ?Closure $prepare = null): array
{
    $repo = sys_get_temp_dir().'/source-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$repo}/{$path}"));
        file_put_contents("{$repo}/{$path}", $contents);
    }

    if ($prepare !== null) {
        $prepare($repo);
    }

    $hashes = function () use ($repo): array {
        $result = [];

        foreach (File::allFiles($repo, true) as $file) {
            $result[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }

        ksort($result);

        return $result;
    };

    try {
        $before = $hashes();
        $reading = app(ReadResearchRepo::class)->handle($repo);

        return ['reading' => $reading, 'before' => $before, 'after' => $hashes(), 'root' => $repo];
    } finally {
        File::deleteDirectory($repo);
    }
}

function scanImage(string $type = 'jpeg', int $shade = 100): string
{
    $image = imagecreatetruecolor(8, 8);
    imagefill($image, 0, 0, imagecolorallocate($image, $shade, 20, 20));
    ob_start();
    match ($type) {
        'png' => imagepng($image),
        'webp' => imagewebp($image),
        'gif' => imagegif($image),
        default => imagejpeg($image),
    };

    return (string) ob_get_clean();
}

function sourceBlock(string $entries, string $version = '1'): string
{
    return "---\nwersja: {$version}\nskany:\n{$entries}---\n# Opis\n";
}

function sourceEntry(string $id, string $scan = 'zrodla/skany/a.jpg', string $extra = "    rodzaj: akt\n"): string
{
    return "  - id: {$id}\n".($scan === '' ? '' : "    plik: {$scan}\n").$extra;
}

test('reads a source block with two entries on one scan and hashes the file', function () {
    $jpeg = scanImage();
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => $jpeg,
        'zrodla/akty.md' => sourceBlock(
            sourceEntry('ZR-0002', extra: "    rodzaj: akt\n    typ: slub\n    nazwa: Akt 2\n    rok: 1837\n    parafia:\n      miejscowosc: Wieś Przykładowa\n      wyznanie: grekokatolickie\n      rodzaj: usc\n      wezwanie: św. Jana\n    archiwum: AP Przykładowe\n    sygnatura: 35/1\n    link: https://example.org/s\n    notatka: skan 34\n").
            sourceEntry('ZR-0001', extra: "    rodzaj: inne\n"),
        ),
    ]);
    $reading = $run['reading'];
    $source = $reading->sources['ZR-0002'];

    expect(array_keys($reading->sources))->toBe(['ZR-0001', 'ZR-0002'])
        ->and($reading->skippedSources)->toBe([])
        ->and($source->scanHash)->toBe(hash('sha256', $jpeg))
        ->and($source->scanPath)->toBe('zrodla/skany/a.jpg')
        ->and($source->file)->toBe('zrodla/akty.md')
        ->and($source->kind)->toBe(SourceKind::MetricalRecord)
        ->and($source->recordType)->toBe(SourceRecordType::Marriage)
        ->and($source->parish->religion)->toBe(Religion::GreekCatholic)
        ->and($source->parish->kind)->toBe(ParishKind::CivilRegistry)
        ->and([$source->parish->locality, $source->parish->dedication])->toBe(['Wieś Przykładowa', 'św. Jana'])
        ->and([$source->name, $source->year, $source->archive, $source->signature, $source->url, $source->note])->toBe(['Akt 2', 1837, 'AP Przykładowe', '35/1', 'https://example.org/s', 'skan 34'])
        ->and($reading->sources['ZR-0001']->kind)->toBe(SourceKind::Other)
        ->and(messagesFor($reading->warnings, 'zrodla/akty.md'))->toContain('Ten sam plik skanu zrodla/skany/a.jpg mają źródła: ZR-0001, ZR-0002')
        ->and($reading->sourceFilesScanned)->toBe(1)
        ->and($reading->sourceScanEntries)->toBe(2)
        ->and($run['after'])->toBe($run['before']);
});

test('skips a scan entry with the reason and keeps the rest of the file', function (string $entry, ?string $scanName, ?string $scanContents, string $reason, ?Closure $prepare = null) {
    $files = ['zrodla/z.md' => sourceBlock($entry.sourceEntry('ZR-0099', 'zrodla/skany/ok.jpg')), 'zrodla/skany/ok.jpg' => scanImage()];

    if ($scanName !== null) {
        $files[$scanName] = $scanContents ?? '';
    }

    $run = readTemporaryRepo($files, $prepare);
    $reading = $run['reading'];

    expect(array_keys($reading->sources))->toBe(['ZR-0099'])
        ->and($reading->skippedSources)->toHaveCount(1)
        ->and($reading->skippedSources[0]->repoId)->toBe('ZR-0001')
        ->and($reading->skippedSources[0]->file)->toBe('zrodla/z.md')
        ->and($reading->skippedSources[0]->message)->toContain($reason)
        ->and($reading->skippedSourceIds())->toBe(['ZR-0001'])
        ->and($reading->hasSkippedSourceWithoutId())->toBeFalse()
        ->and($run['after'])->toBe($run['before']);
})->with([
    'no scan field' => [sourceEntry('ZR-0001', ''), null, null, 'brak skanu'],
    'missing file' => [sourceEntry('ZR-0001', 'zrodla/skany/brak.jpg'), null, null, 'plik skanu nie istnieje: zrodla/skany/brak.jpg'],
    'parent directory' => [sourceEntry('ZR-0001', '../poza.jpg'), null, null, 'plik skanu poza repo'],
    'absolute path' => [sourceEntry('ZR-0001', '/etc/passwd'), null, null, 'plik skanu poza repo'],
    'pdf' => [sourceEntry('ZR-0001', 'zrodla/skany/a.pdf'), 'zrodla/skany/a.pdf', "%PDF-1.4\n", 'PDF nie jest obsługiwany'],
    'pdf named jpg' => [sourceEntry('ZR-0001', 'zrodla/skany/a.jpg'), 'zrodla/skany/a.jpg', "%PDF-1.4\n%%EOF\n", 'PDF nie jest obsługiwany'],
    'gif' => [sourceEntry('ZR-0001', 'zrodla/skany/a.gif'), 'zrodla/skany/a.gif', null, 'nieobsługiwany format pliku'],
    'gif content named jpg' => [sourceEntry('ZR-0001', 'zrodla/skany/b.jpg'), 'zrodla/skany/b.jpg', null, 'nieobsługiwany format pliku'],
    'tombstone' => [sourceEntry('ZR-0001', 'zrodla/skany/ok.jpg', "    rodzaj: nagrobek\n"), null, null, 'nagrobki: osobna zmiana'],
    'unknown religion' => [sourceEntry('ZR-0001', 'zrodla/skany/ok.jpg', "    rodzaj: akt\n    parafia:\n      miejscowosc: Wieś\n      wyznanie: greckokatolickie\n"), null, null, 'parafia.wyznanie'],
    'unknown record type' => [sourceEntry('ZR-0001', 'zrodla/skany/ok.jpg', "    rodzaj: akt\n    typ: chrzest\n"), null, null, 'typ'],
    'unknown kind' => [sourceEntry('ZR-0001', 'zrodla/skany/ok.jpg', "    rodzaj: pamiatka\n"), null, null, 'rodzaj'],
    'unknown parish kind' => [sourceEntry('ZR-0001', 'zrodla/skany/ok.jpg', "    rodzaj: akt\n    parafia:\n      miejscowosc: Wieś\n      rodzaj: cerkiew\n"), null, null, 'parafia.rodzaj'],
]);

test('skips a scan over 20 MB and a symlink that leaves the repo', function () {
    $outside = sys_get_temp_dir().'/outside-scan-'.uniqid().'.jpg';
    file_put_contents($outside, scanImage());

    try {
        $run = readTemporaryRepo([
            'zrodla/z.md' => sourceBlock(
                sourceEntry('ZR-0001', 'zrodla/skany/duzy.jpg').sourceEntry('ZR-0002', 'zrodla/skany/link.jpg'),
            ),
        ], function (string $repo) use ($outside): void {
            mkdir($repo.'/zrodla/skany');
            $handle = fopen($repo.'/zrodla/skany/duzy.jpg', 'wb');
            ftruncate($handle, 20 * 1024 * 1024 + 1);
            fclose($handle);
            symlink($outside, $repo.'/zrodla/skany/link.jpg');
        });
    } finally {
        unlink($outside);
    }

    expect($run['reading']->sources)->toBe([])
        ->and(array_map(fn (ImportIssue $issue): string => $issue->message, $run['reading']->skippedSources))->toBe(['plik skanu większy niż 20 MB', 'plik skanu poza repo']);
});

test('skips a source file that is a symlink leaving the repo without reading it', function () {
    $outside = sys_get_temp_dir().'/outside-block-'.uniqid().'.md';
    file_put_contents($outside, sourceBlock(sourceEntry('ZR-0001')));

    try {
        $run = readTemporaryRepo([
            'zrodla/skany/a.jpg' => scanImage(),
        ], function (string $repo) use ($outside): void {
            symlink($outside, $repo.'/zrodla/obcy.md');
        });
    } finally {
        unlink($outside);
    }

    expect($run['reading']->sources)->toBe([])
        ->and(messagesFor($run['reading']->skippedSources, 'zrodla/obcy.md'))->toBe('Plik poza repo');
});

test('accepts jpg, png and webp scans', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/skany/b.png' => scanImage('png'),
        'zrodla/skany/c.webp' => scanImage('webp'),
        'zrodla/z.md' => sourceBlock(sourceEntry('ZR-0001').sourceEntry('ZR-0002', 'zrodla/skany/b.png').sourceEntry('ZR-0003', 'zrodla/skany/c.webp')),
    ]);

    expect(array_keys($run['reading']->sources))->toBe(['ZR-0001', 'ZR-0002', 'ZR-0003']);
});

test('skips every entry of an id that is written twice, in one file and across files', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/a.md' => sourceBlock(sourceEntry('ZR-0001').sourceEntry('ZR-0001').sourceEntry('ZR-0002')),
        'zrodla/b/b.md' => sourceBlock(sourceEntry('ZR-0002').sourceEntry('ZR-0003')),
    ]);
    $reading = $run['reading'];

    expect(array_keys($reading->sources))->toBe(['ZR-0003'])
        ->and($reading->skippedSourceIds())->toBe(['ZR-0001', 'ZR-0002'])
        ->and(messagesFor($reading->skippedSources, 'zrodla/b/b.md'))->toContain('Powtórzony id ZR-0002 w plikach: zrodla/a.md, zrodla/b/b.md')
        ->and($reading->sourceFilesScanned)->toBe(2)
        ->and($reading->sourceScanEntries)->toBe(5);
});

test('skips the valid entry of an id whose other entry is skipped', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/a.md' => sourceBlock(sourceEntry('ZR-0001').sourceEntry('ZR-0002')),
        'zrodla/b.md' => sourceBlock(sourceEntry('ZR-0001', scan: '')),
    ]);
    $reading = $run['reading'];

    expect(array_keys($reading->sources))->toBe(['ZR-0002'])
        ->and($reading->skippedSourceIds())->toBe(['ZR-0001'])
        ->and(messagesFor($reading->skippedSources, 'zrodla/a.md'))->toBe('Powtórzony id ZR-0001 w plikach: zrodla/a.md, zrodla/b.md');
});

test('skips a whole source file with a YAML error, a bad version or skany that is not a list', function (string $contents, string $reason) {
    $run = readTemporaryRepo(['zrodla/z.md' => $contents]);
    $reading = $run['reading'];

    expect($reading->sources)->toBe([])
        ->and($reading->skippedSources)->toHaveCount(1)
        ->and($reading->skippedSources[0]->message)->toContain($reason)
        ->and($reading->hasSkippedSourceWithoutId())->toBeTrue();
})->with([
    'yaml error' => ["---\nwersja: 1\nskany: [a, : b\n---\n", 'Błąd składni YAML'],
    'version 2' => ["---\nwersja: 2\nskany: []\n---\n", 'wersja'],
    'missing version' => ["---\nskany: []\n---\n", 'Brak pola wersja'],
    'skany a map' => ["---\nwersja: 1\nskany:\n  id: ZR-0001\n---\n", 'musi być listą'],
]);

test('reads zrodla recursively, ignores underscore files and counts files without a block', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/glebiej/jeszcze/z.md' => sourceBlock(sourceEntry('ZR-0001')),
        'zrodla/_indeks.md' => sourceBlock(sourceEntry('ZR-0009')),
        'zrodla/proza.md' => "# Tylko proza\n",
        'zrodla/notatki.txt' => sourceBlock(sourceEntry('ZR-0008')),
    ]);
    $reading = $run['reading'];

    expect(array_keys($reading->sources))->toBe(['ZR-0001'])
        ->and($reading->sources['ZR-0001']->file)->toBe('zrodla/glebiej/jeszcze/z.md')
        ->and($reading->sourceFilesScanned)->toBe(2)
        ->and($reading->sourceFilesWithoutBlock)->toBe(['zrodla/proza.md']);
});

test('reads no sources and no error when zrodla is missing', function () {
    $reading = readTemporaryRepo([])['reading'];

    expect($reading->sources)->toBe([])
        ->and($reading->sourceFilesScanned)->toBe(0)
        ->and($reading->skippedSources)->toBe([]);
});

test('warns about unknown keys, fields that only an act has and an archive that looks like a signature', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/z.md' => "---\nwersja: 1\nextra: 1\nskany:\n".sourceEntry('ZR-0001', extra: "    rodzaj: inne\n    typ: zgon\n    parafia:\n      miejscowosc: Wieś\n    kolor: zielony\n    archiwum: AP Lublin, 35/1813\n")."---\n",
    ]);
    $messages = messagesFor($run['reading']->warnings, 'zrodla/z.md');

    expect($run['reading']->sources['ZR-0001']->recordType)->toBeNull()
        ->and($run['reading']->sources['ZR-0001']->parish)->toBeNull()
        ->and($messages)->toContain('Nieznany klucz: extra')
        ->and($messages)->toContain('Nieznany klucz: ZR-0001.kolor')
        ->and($messages)->toContain('pole typ dotyczy tylko aktu')
        ->and($messages)->toContain('pole parafia dotyczy tylko aktu')
        ->and($messages)->toContain('archiwum wygląda na sygnaturę');
});

test('reads a source of another kind with a wrong record type or an incomplete parish, leaving both fields out with a warning', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/z.md' => sourceBlock(sourceEntry('ZR-0001', extra: "    rodzaj: inne\n    typ: chrzest\n    parafia:\n      wezwanie: św. Jana\n")),
    ]);
    $messages = messagesFor($run['reading']->warnings, 'zrodla/z.md');

    expect($run['reading']->skippedSources)->toBe([])
        ->and($run['reading']->sources['ZR-0001']->recordType)->toBeNull()
        ->and($run['reading']->sources['ZR-0001']->parish)->toBeNull()
        ->and($messages)->toContain('pole typ dotyczy tylko aktu')
        ->and($messages)->toContain('pole parafia dotyczy tylko aktu');
});

test('keeps the sources of an event sorted, as text, without repeated ids', function () {
    $run = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/z.md' => sourceBlock(sourceEntry('ZR-0001').sourceEntry('ZR-0002')),
        'drzewo/jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\nurodzenie:\n  zrodla:\n    - {id: ZR-0002, wpis: 15}\n    - {id: ZR-0001, wpis: \"15/1837\"}\n    - {id: ZR-0002, wpis: \"inny\"}\nzgon:\n  zrodla: [{id: ZR-0001}]\n---\n",
    ]);
    $reading = $run['reading'];
    $links = $reading->persons['O-0001']->birth->sources;

    expect(array_map(fn ($link): array => [$link->id, $link->entry], $links))->toBe([['ZR-0001', '15/1837'], ['ZR-0002', '15']])
        ->and($reading->persons['O-0001']->death->sources[0]->entry)->toBeNull()
        ->and(messagesFor($reading->warnings, 'jan.md'))->toContain('źródło ZR-0002 wpisane więcej niż raz');
});

test('skips a person with a bad source reference', function (string $zrodla) {
    $reading = readTemporaryRepo(['drzewo/jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\nurodzenie:\n  zrodla: {$zrodla}\n---\n"])['reading'];

    expect($reading->persons)->toBe([])
        ->and($reading->skipped)->toHaveCount(1)
        ->and($reading->skipped[0]->repoId)->toBe('O-0001');
})->with([
    'bad id' => ['[{id: Z-0001}]'],
    'no id' => ['[{wpis: "1"}]'],
    'entry over 50 characters' => ['[{id: ZR-0001, wpis: "'.str_repeat('1', 51).'"}]'],
    'not a list' => ['{id: ZR-0001}'],
]);

test('keeps a reference to an unknown or skipped source and warns', function () {
    $run = readTemporaryRepo([
        'zrodla/z.md' => sourceBlock(sourceEntry('ZR-0001', '')),
        'drzewo/jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\nurodzenie:\n  zrodla: [{id: ZR-0001, wpis: \"1\"}, {id: ZR-0777}]\n---\n",
    ]);
    $reading = $run['reading'];

    expect(array_map(fn ($link): string => $link->id, $reading->persons['O-0001']->birth->sources))->toBe(['ZR-0001', 'ZR-0777'])
        ->and(messagesFor($reading->warnings, 'jan.md'))->toContain('źródło ZR-0001 nie istnieje albo zostało pominięte')
        ->and(messagesFor($reading->warnings, 'jan.md'))->toContain('źródło ZR-0777 nie istnieje albo zostało pominięte');
});

test('warns about zrodla in a burial as an unknown key', function () {
    $reading = readTemporaryRepo(['drzewo/jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\nzgon: {}\npochowek:\n  zrodla: [{id: ZR-0001}]\n---\n"])['reading'];

    expect($reading->persons['O-0001']->burial->sources)->toBe([])
        ->and(messagesFor($reading->warnings, 'jan.md'))->toContain('Nieznany klucz: pochowek.zrodla');
});

test('merges the sources of a wedding written in both files', function (string $first, string $second, ?array $expected) {
    $person = fn (string $id, string $partner, string $zrodla): string => "---\nwersja: 1\nid: {$id}\nimiona: X\nmalzenstwa:\n  - z: {$partner}\n    data: \"1830\"\n    zrodla: {$zrodla}\n---\n";
    $reading = readTemporaryRepo([
        'drzewo/a.md' => $person('O-0001', 'O-0002', $first),
        'drzewo/b.md' => $person('O-0002', 'O-0001', $second),
    ])['reading'];

    if ($expected === null) {
        expect($reading->marriages)->toBe([])
            ->and($reading->droppedMarriageKeys)->toBe(['O-0001+O-0002/slub'])
            ->and(messagesFor($reading->warnings, 'a.md'))->toContain('[ZR-0001 (1), ZR-0002] oraz [ZR-0003]');

        return;
    }

    expect($reading->marriages)->toHaveCount(1)
        ->and(array_map(fn ($link): string => $link->id, $reading->marriages[0]->event->sources))->toBe($expected);
})->with([
    'same sources in another order' => ['[{id: ZR-0002}, {id: ZR-0001}]', '[{id: ZR-0001}, {id: ZR-0002}]', ['ZR-0001', 'ZR-0002']],
    'only the first spouse' => ['[{id: ZR-0001}]', '[]', ['ZR-0001']],
    'only the second spouse' => ['[]', '[{id: ZR-0002}]', ['ZR-0002']],
    'two different lists' => ['[{id: ZR-0001, wpis: "1"}, {id: ZR-0002}]', '[{id: ZR-0003}]', null],
]);

test('maps every value of rodzaj, typ, wyznanie and parafia rodzaj onto the enum case', function () {
    $entries = '';
    $expected = [];
    $number = 1;

    foreach (['akt' => SourceKind::MetricalRecord, 'inne' => SourceKind::Other] as $kind => $kindCase) {
        $id = sprintf('ZR-%04d', $number++);
        $entries .= sourceEntry($id, extra: "    rodzaj: {$kind}\n");
        $expected[$id] = [$kindCase, null, null, null];
    }

    foreach (['urodzenie' => SourceRecordType::Birth, 'slub' => SourceRecordType::Marriage, 'zgon' => SourceRecordType::Death, 'inne' => SourceRecordType::Other] as $type => $case) {
        $id = sprintf('ZR-%04d', $number++);
        $entries .= sourceEntry($id, extra: "    rodzaj: akt\n    typ: {$type}\n");
        $expected[$id] = [SourceKind::MetricalRecord, $case, null, null];
    }

    $religions = ['rzymskokatolickie' => Religion::RomanCatholic, 'grekokatolickie' => Religion::GreekCatholic, 'ewangelickie' => Religion::Evangelical, 'prawoslawne' => Religion::Orthodox, 'mojzeszowe' => Religion::Jewish, 'inne' => Religion::Other];

    foreach ($religions as $religion => $case) {
        $id = sprintf('ZR-%04d', $number++);
        $entries .= sourceEntry($id, extra: "    rodzaj: akt\n    parafia:\n      miejscowosc: Wieś\n      wyznanie: {$religion}\n");
        $expected[$id] = [SourceKind::MetricalRecord, null, $case, ParishKind::Parish];
    }

    foreach (['parafia' => ParishKind::Parish, 'usc' => ParishKind::CivilRegistry, 'okreg_bozniczy' => ParishKind::JewishDistrict, 'inny' => ParishKind::Other] as $kind => $case) {
        $id = sprintf('ZR-%04d', $number++);
        $entries .= sourceEntry($id, extra: "    rodzaj: akt\n    parafia:\n      miejscowosc: Wieś\n      rodzaj: {$kind}\n");
        $expected[$id] = [SourceKind::MetricalRecord, null, null, $case];
    }

    $reading = readTemporaryRepo(['zrodla/skany/a.jpg' => scanImage(), 'zrodla/z.md' => sourceBlock($entries)])['reading'];

    expect($reading->skippedSources)->toBe([])
        ->and(array_map(fn ($source): array => [$source->kind, $source->recordType, $source->parish?->religion, $source->parish?->kind], $reading->sources))->toBe($expected);
});

test('builds the memory values of a source and the key of a source', function () {
    $reading = readTemporaryRepo([
        'zrodla/skany/a.jpg' => scanImage(),
        'zrodla/z.md' => sourceBlock(sourceEntry('ZR-0001', extra: "    rodzaj: akt\n    typ: zgon\n    rok: 1840\n    parafia:\n      miejscowosc: Wieś\n      wyznanie: rzymskokatolickie\n      wezwanie: św. Jana\n    sygnatura: 12/3\n")),
    ])['reading'];
    $values = RepoValues::forKey('ZR-0001', $reading);

    expect($values)->toMatchArray(['rodzaj' => 'metrical_record', 'typ' => 'death', 'rok' => '1840', 'parafia' => 'Wieś|roman_catholic|parish|św. Jana', 'sygnatura' => '12/3', 'plik' => 'zrodla/skany/a.jpg', 'archiwum' => null])
        ->and($values['plik_hash'])->toHaveLength(64)
        ->and(RepoValues::forKey('ZR-0404', $reading))->toBeNull();
});

test('leaves the fixture repo without sources', function () {
    $reading = readFixtureRepo();

    expect($reading->sources)->toBe([])
        ->and($reading->sourceFilesScanned)->toBe(0)
        ->and($reading->skippedSources)->toBe([])
        ->and($reading->skippedSourceIds())->toBe([])
        ->and($reading->hasSkippedSourceWithoutId())->toBeFalse();
});
