<?php

use App\Actions\Import\ParsePersonBlock;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\ConfidenceLevel;
use App\Enums\DateQualifier;
use App\Enums\Gender;
use App\Support\Import\ImportIssue;
use App\Support\Import\ResearchRepoReading;

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
        ->and($jan->burial->place)->toBe('Cmentarz w Zalesiu');
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
