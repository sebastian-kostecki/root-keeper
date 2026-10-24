<?php

use App\Support\Import\NameKey;
use App\Support\Import\ResearchPerson;

function researchPersonNamed(?string $givenNames, ?string $surname, array $variants = []): ResearchPerson
{
    return new ResearchPerson('O-0001', 'osoba.md', [], $givenNames, $surname, $variants, null, null, null, null, null, null, []);
}

test('ignores case and Polish diacritics', function () {
    expect(NameKey::firstName('Łukasz'))->toBe(NameKey::firstName('lukasz'))
        ->and(NameKey::firstName('Łukasz'))->toBe('lukasz')
        ->and(NameKey::surname('  Żółć   Wrona '))->toBe('zolc wrona');
});

test('takes the first of the given names', function () {
    expect(NameKey::firstName('Jan Józef'))->toBe('jan')
        ->and(NameKey::firstName('  Józef   Jan '))->toBe('jozef');
});

test('treats a blank name as missing', function () {
    expect(NameKey::normalize(null))->toBeNull()
        ->and(NameKey::firstName('   '))->toBeNull()
        ->and(NameKey::surname(''))->toBeNull();
});

test('keys the surname of a repo person and each variant', function () {
    $person = researchPersonNamed('Jan', 'Nowak', ['Nowakowski', 'NOWAK', 'Nowaczyński']);

    expect(NameKey::surnames($person))->toBe(['nowak', 'nowakowski', 'nowaczynski']);
});

test('keeps a missing surname as a key of its own', function () {
    expect(NameKey::surnames(researchPersonNamed('Jan', null)))->toBe([null])
        ->and(NameKey::surnames(researchPersonNamed('Jan', null, ['Nowakowski'])))->toBe([null, 'nowakowski']);
});
