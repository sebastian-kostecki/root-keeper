<?php

use App\Actions\Import\MatchResearchSources;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Enums\SourceRecordType;
use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\SourceDecisions;
use App\Support\Import\SourceMatches;
use Illuminate\Support\Facades\File;

/**
 * Reads a throwaway repo of persons and sources and lists the manual sources that may hold the same scan as a source
 * of the repo. Fails when a candidate of another tree shows up.
 *
 * @param  array<string, string>  $blocks  repo id of a person => block body without id and version
 * @param  array<string, string>  $sources  repo id of a source => extra fields of its entry (`typ`, `rok`)
 * @param  array<string, int>  $matched  repo id of a person => id of the tree person the author matched
 * @param  array<string, Person>  $known  repo id of a person => the tree person that holds it
 */
function matchTemporarySources(Tree $tree, array $blocks, array $sources, array $matched = [], array $known = [], array $skipped = []): SourceMatches
{
    $repo = sys_get_temp_dir().'/source-match-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);
    mkdir($repo.'/zrodla/skany', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    $entries = '';

    foreach (array_keys($sources) as $index => $id) {
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 20 + $index, 20, 20));
        imagejpeg($image, "{$repo}/zrodla/skany/{$id}.jpg");
        $entries .= "  - id: {$id}\n    plik: zrodla/skany/{$id}.jpg\n    rodzaj: akt\n{$sources[$id]}";
    }

    file_put_contents("{$repo}/zrodla/akty.md", "---\nwersja: 1\nskany:\n{$entries}---\n");

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }

    expect($reading->skippedSources)->toBe([]);

    $decisions = new ImportDecisions(new IdentityDecisions(matched: $matched, skipped: $skipped));
    $matches = app(MatchResearchSources::class)->handle($tree, $reading, new RepoRecordClasses(knownPersons: $known), $decisions);

    foreach ($matches->matches as $match) {
        foreach ($match->candidates as $candidate) {
            expect($candidate->tree_id)->toBe($tree->id);
        }
    }

    return $matches;
}

/**
 * A person block whose birth cites the sources.
 *
 * @param  list<string>  $sourceIds
 */
function bornCiting(array $sourceIds, string $year = '1837'): string
{
    $links = implode('', array_map(fn (string $id): string => "\n    - id: {$id}", $sourceIds));

    return "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"{$year}\"\n  zrodla:{$links}";
}

/**
 * A person of the tree with a birth, optionally with a repo id on the person and the birth, and the given sources on it.
 *
 * @param  list<Source>  $sources
 * @return array{0: Person, 1: Event}
 */
function personWithBirth(Tree $tree, array $sources = [], ?string $repoId = null): array
{
    $person = Person::factory()->create(['tree_id' => $tree->id, 'first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => $repoId]);
    $birth = Event::factory()->birth()->create(['tree_id' => $tree->id, 'repo_id' => $repoId === null ? null : "{$repoId}/urodzenie"]);
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    foreach ($sources as $source) {
        $birth->sources()->attach($source);
    }

    return [$person, $birth];
}

function manualScan(Tree $tree, array $attributes = []): Source
{
    return Source::factory()->create(['tree_id' => $tree->id, ...$attributes]);
}

/**
 * Builds the same person with a manual scan in the tree of another account, so that a match that leaves its tree would
 * show up, and returns a new tree.
 */
function sourceTwinTree(): Tree
{
    $other = User::factory()->create()->tree;
    personWithBirth($other, [manualScan($other, ['record_type' => SourceRecordType::Birth, 'year' => 1837])], 'O-0001');

    return User::factory()->create()->tree;
}

/**
 * @return list<int>
 */
function candidateIds(SourceMatches $matches, string $repoId): array
{
    return array_map(fn (Source $source): int => $source->id, ($matches->matches[$repoId] ?? null)?->candidates ?? []);
}

test('a manual source on the matched birth is a candidate for the source the person cites', function () {
    $tree = sourceTwinTree();
    $scan = manualScan($tree, ['record_type' => SourceRecordType::Birth, 'year' => 1837]);
    [$person, $birth] = personWithBirth($tree, [$scan]);

    $matches = matchTemporarySources($tree, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => "    typ: urodzenie\n    rok: 1837\n"], matched: ['O-0001' => $person->id]);

    expect(array_keys($matches->matches))->toBe(['ZR-0001'])
        ->and(candidateIds($matches, 'ZR-0001'))->toBe([$scan->id])
        ->and($matches->matches['ZR-0001']->source->id)->toBe('ZR-0001')
        ->and(array_map(fn (Event $event): int => $event->id, $matches->matches['ZR-0001']->events))->toBe([$birth->id]);
});

test('a manual source on an event found by its repo id is a candidate for a person the tree already holds', function () {
    $tree = sourceTwinTree();
    $scan = manualScan($tree);
    [$person] = personWithBirth($tree, [$scan], 'O-0001');

    $matches = matchTemporarySources($tree, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => ''], known: ['O-0001' => $person]);

    expect(candidateIds($matches, 'ZR-0001'))->toBe([$scan->id]);
});

test('a manual source with an empty type and year agrees with any', function () {
    $tree = sourceTwinTree();
    $scan = manualScan($tree);
    [$person] = personWithBirth($tree, [$scan]);

    $matches = matchTemporarySources($tree, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => "    typ: zgon\n    rok: 1901\n"], matched: ['O-0001' => $person->id]);

    expect(candidateIds($matches, 'ZR-0001'))->toBe([$scan->id]);
});

test('a manual source is not a candidate with another type or year, as a gravestone, with a repo id or when not on the cited event', function (Closure $arrange) {
    $tree = sourceTwinTree();
    [$person, $birth] = personWithBirth($tree);
    $arrange($tree, $birth);

    $matches = matchTemporarySources($tree, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => "    typ: urodzenie\n    rok: 1837\n"], matched: ['O-0001' => $person->id]);

    expect($matches->matches)->toBe([]);
})->with([
    'another type' => [fn (Tree $tree, Event $birth) => $birth->sources()->attach(manualScan($tree, ['record_type' => SourceRecordType::Death]))],
    'another year' => [fn (Tree $tree, Event $birth) => $birth->sources()->attach(manualScan($tree, ['year' => 1838]))],
    'a gravestone' => [fn (Tree $tree, Event $birth) => $birth->sources()->attach(Source::factory()->gravestone()->create(['tree_id' => $tree->id]))],
    'a repo id' => [fn (Tree $tree, Event $birth) => $birth->sources()->attach(manualScan($tree, ['repo_id' => 'ZR-0099']))],
    'not on the event' => [function (Tree $tree) {
        manualScan($tree);
        $other = Person::factory()->create(['tree_id' => $tree->id]);
        $event = Event::factory()->birth()->create(['tree_id' => $tree->id]);
        $event->participants()->attach($other, ['role' => ParticipantRole::Subject]);
        $event->sources()->attach(manualScan($tree));
    }],
]);

test('a source the repo does not cite on the fact, a known source and a source with memory are not matched', function () {
    $tree = sourceTwinTree();
    $scan = manualScan($tree);
    [$person] = personWithBirth($tree, [$scan]);
    manualScan($tree, ['repo_id' => 'ZR-0002']);
    $tree->repoSnapshots()->create(['repo_id' => 'ZR-0003', 'values' => ['rodzaj' => 'metrical_record']]);

    $matches = matchTemporarySources(
        $tree,
        ['O-0001' => bornCiting(['ZR-0002', 'ZR-0003'])],
        ['ZR-0001' => '', 'ZR-0002' => '', 'ZR-0003' => ''],
        matched: ['O-0001' => $person->id],
    );

    expect($matches->matches)->toBe([]);
});

test('a person who is skipped or not matched brings no candidate', function () {
    $tree = sourceTwinTree();
    [$person] = personWithBirth($tree, [manualScan($tree)]);
    $blocks = ['O-0001' => bornCiting(['ZR-0001'])];

    expect(matchTemporarySources($tree, $blocks, ['ZR-0001' => ''], matched: ['O-0001' => $person->id], skipped: ['O-0001'])->matches)->toBe([])
        ->and(matchTemporarySources($tree, $blocks, ['ZR-0001' => ''])->matches)->toBe([]);
});

test('a manual source on the matched wedding is a candidate for the source the wedding cites', function () {
    $tree = sourceTwinTree();
    $scan = manualScan($tree);
    [$groom] = personWithBirth($tree);
    [$bride] = personWithBirth($tree);
    $wedding = Event::factory()->create(['tree_id' => $tree->id, 'type' => EventType::Marriage]);
    $wedding->participants()->attach([$groom->id => ['role' => ParticipantRole::Spouse], $bride->id => ['role' => ParticipantRole::Spouse]]);
    $wedding->sources()->attach($scan);

    $matches = matchTemporarySources(
        $tree,
        [
            'O-0001' => "imiona: Jan\nnazwisko: Nowak\nmalzenstwa:\n  - z: O-0002\n    data: \"1860\"\n    zrodla:\n      - id: ZR-0001",
            'O-0002' => "imiona: Anna\nnazwisko: Nowak\nmalzenstwa:\n  - z: O-0001\n    data: \"1860\"\n    zrodla:\n      - id: ZR-0001",
        ],
        ['ZR-0001' => ''],
        matched: ['O-0001' => $groom->id, 'O-0002' => $bride->id],
    );

    expect(candidateIds($matches, 'ZR-0001'))->toBe([$scan->id]);
});

test('one manual source is offered for every source that cites its event and a double merge is detectable', function () {
    $tree = sourceTwinTree();
    $scan = manualScan($tree);
    [$person] = personWithBirth($tree, [$scan]);

    $matches = matchTemporarySources($tree, ['O-0001' => bornCiting(['ZR-0001', 'ZR-0002'])], ['ZR-0001' => '', 'ZR-0002' => ''], matched: ['O-0001' => $person->id]);

    expect(candidateIds($matches, 'ZR-0001'))->toBe([$scan->id])
        ->and(candidateIds($matches, 'ZR-0002'))->toBe([$scan->id])
        ->and((new SourceDecisions(['ZR-0001' => $scan->id]))->hasDuplicateTargets())->toBeFalse()
        ->and((new SourceDecisions(['ZR-0001' => $scan->id, 'ZR-0002' => $scan->id]))->hasDuplicateTargets())->toBeTrue();
});

test('the manual scans of another account on the same persons are never candidates', function () {
    $other = User::factory()->create()->tree;
    $author = User::factory()->create()->tree;
    [$otherPerson] = personWithBirth($other, [manualScan($other)]);
    [$person] = personWithBirth($author);

    $matches = matchTemporarySources($author, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => ''], matched: ['O-0001' => $person->id]);

    expect($matches->matches)->toBe([]);

    $mine = manualScan($author);
    Event::query()->whereHas('participants', fn ($query) => $query->whereKey($person->id))->firstOrFail()->sources()->attach($mine);

    $matches = matchTemporarySources($author, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => ''], matched: ['O-0001' => $person->id]);

    expect(candidateIds($matches, 'ZR-0001'))->toBe([$mine->id]);

    $matches = matchTemporarySources($author, ['O-0001' => bornCiting(['ZR-0001'])], ['ZR-0001' => ''], matched: ['O-0001' => $otherPerson->id]);

    expect($matches->matches)->toBe([]);
});
