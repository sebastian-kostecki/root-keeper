<?php

use App\Models\Person;
use App\Models\PersonSurnameVariant;
use App\Support\Import\ResearchPerson;
use App\Support\Import\SurnameVariants;

function researchPersonWithVariants(array $variants): ResearchPerson
{
    return new ResearchPerson('O-0001', 'jan.md', [], 'Jan', 'Misiuk', $variants, null, null, null, null, null, null, []);
}

test('trims, collapses spaces and drops empty entries', function () {
    $variants = SurnameVariants::fromResearch(researchPersonWithVariants(['  Mysiuk ', 'My   szuk', '', '   ']));

    expect($variants->names)->toBe(['Mysiuk', 'My szuk']);
});

test('drops a repeat that differs only in case and keeps the first', function () {
    $variants = SurnameVariants::fromResearch(researchPersonWithVariants(['Mysiuk', 'MYSIUK', 'Myszuk', 'mysiuk']));

    expect($variants->names)->toBe(['Mysiuk', 'Myszuk']);
});

test('folds case by simple case mapping, like the lower() of the unique index', function () {
    expect(SurnameVariants::fromResearch(researchPersonWithVariants(['Iwański', 'İwański', 'ŁÓDŹ', 'łódź']))->names)->toBe(['Iwański', 'ŁÓDŹ']);
});

test('keeps the order of the repo', function () {
    expect(SurnameVariants::fromResearch(researchPersonWithVariants(['Zet', 'Aaa', 'Mmm']))->names)->toBe(['Zet', 'Aaa', 'Mmm']);
});

test('joins the names by a new line for comparing and by a comma for showing', function () {
    $variants = SurnameVariants::fromResearch(researchPersonWithVariants(['Mysiuk', 'Myszuk']));

    expect($variants->text())->toBe("Mysiuk\nMyszuk")
        ->and($variants->label())->toBe('Mysiuk, Myszuk');
});

test('gives null for an empty list', function () {
    $variants = SurnameVariants::fromResearch(researchPersonWithVariants([' ']));

    expect($variants->names)->toBe([])
        ->and($variants->text())->toBeNull()
        ->and($variants->label())->toBeNull();
});

test('reads the variants of a person through the same normalization', function () {
    $person = new Person;
    $person->setRelation('surnameVariants', collect([
        new PersonSurnameVariant(['name' => ' Mysiuk ']),
        new PersonSurnameVariant(['name' => 'MYSIUK']),
        new PersonSurnameVariant(['name' => 'Myszuk']),
    ]));

    expect(SurnameVariants::fromPerson($person)->names)->toBe(['Mysiuk', 'Myszuk']);
});
