<?php

use App\Enums\DateQualifier;
use App\Support\PartialDate;

test('formats a full date', function () {
    $date = new PartialDate(year: 1850, month: 3, day: 12);

    expect($date->format())->toBe('12.03.1850');
});

test('formats a year and month', function () {
    $date = new PartialDate(year: 1850, month: 3, day: null);

    expect($date->format())->toBe('03.1850');
});

test('formats a year only', function () {
    $date = new PartialDate(year: 1850, month: null, day: null);

    expect($date->format())->toBe('1850');
});

test('prefixes an approximate year with ok.', function () {
    $date = new PartialDate(year: 1850, month: null, day: null, qualifier: DateQualifier::Approximate);

    expect($date->format())->toBe('ok. 1850');
});

test('returns null when there is no year', function () {
    $date = new PartialDate(year: null, month: null, day: null);

    expect($date->format())->toBeNull();
});

test('formats a year for a card', function () {
    $date = new PartialDate(year: 1850, month: 3, day: 12);

    expect($date->formatYear())->toBe('1850');
});

test('prefixes an approximate card year with ok.', function () {
    $date = new PartialDate(year: 1850, month: null, day: null, qualifier: DateQualifier::Approximate);

    expect($date->formatYear())->toBe('ok. 1850');
});

test('returns no card year without a year', function () {
    $date = new PartialDate(year: null, month: 3, day: null);

    expect($date->formatYear())->toBeNull();
});

test('formats every qualifier at every precision', function (DateQualifier $qualifier, ?int $month, ?int $day, string $expected) {
    $date = new PartialDate(year: 1809, month: $month, day: $day, qualifier: $qualifier);

    expect($date->format())->toBe($expected);
})->with([
    'before a full date' => [DateQualifier::Before, 5, 12, 'przed 12.05.1809'],
    'before a month' => [DateQualifier::Before, 5, null, 'przed 05.1809'],
    'before a year' => [DateQualifier::Before, null, null, 'przed 1809'],
    'after a full date' => [DateQualifier::After, 5, 12, 'po 12.05.1809'],
    'after a year' => [DateQualifier::After, null, null, 'po 1809'],
    'approximate a month' => [DateQualifier::Approximate, 5, null, 'ok. 05.1809'],
    'approximate a full date' => [DateQualifier::Approximate, 5, 12, 'ok. 12.05.1809'],
]);

test('formats a range with an en dash and no spaces', function () {
    $date = new PartialDate(year: 1805, month: null, day: null, qualifier: DateQualifier::Range, untilYear: 1811);

    expect($date->format())->toBe('1805–1811')
        ->and($date->formatYear())->toBe('1805–1811')
        ->and($date->formatWithoutQualifier())->toBe('1805');
});

test('the card year carries the qualifier word', function () {
    expect((new PartialDate(1809, 5, 12, DateQualifier::Before))->formatYear())->toBe('przed 1809')
        ->and((new PartialDate(1801, null, null, DateQualifier::After))->formatYear())->toBe('po 1801');
});

test('formatWithoutQualifier keeps the precision but drops the qualifier word', function () {
    expect((new PartialDate(1850, 3, 12, DateQualifier::Approximate))->formatWithoutQualifier())->toBe('12.03.1850')
        ->and((new PartialDate(1850, 3, null, DateQualifier::Before))->formatWithoutQualifier())->toBe('03.1850')
        ->and((new PartialDate(1850, null, null, DateQualifier::After))->formatWithoutQualifier())->toBe('1850')
        ->and((new PartialDate(null, null, null))->formatWithoutQualifier())->toBeNull();
});

test('only before, after and range dates are fuzzy', function () {
    expect(collect(DateQualifier::cases())->filter(fn (DateQualifier $qualifier): bool => (new PartialDate(1850, null, null, $qualifier))->isFuzzy())->map->value->values()->all())
        ->toBe(['before', 'after', 'range']);
});
