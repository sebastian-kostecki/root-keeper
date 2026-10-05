<?php

use App\Support\Age;
use App\Support\PartialDate;
use Carbon\CarbonImmutable;

function ageDate(?int $year, ?int $month = null, ?int $day = null, bool $approximate = false): PartialDate
{
    return new PartialDate($year, $month, $day, $approximate);
}

/**
 * @return array{unit: string, value: int|null}|null
 */
function ageOf(?PartialDate $birth, ?PartialDate $death, string $today = '2026-10-05'): ?array
{
    return Age::from($birth, $death, $death !== null, CarbonImmutable::parse($today))?->toArray();
}

test('a deceased person with year-only dates ages by the year difference', function () {
    expect(ageOf(ageDate(1850), ageDate(1922, 3, 15)))->toBe(['unit' => 'years', 'value' => 72]);
});

test('year and month in both dates subtract a year when the death month is earlier', function () {
    expect(ageOf(ageDate(1850, 5), ageDate(1922, 3)))->toBe(['unit' => 'years', 'value' => 71]);
});

test('full dates use the calendar difference', function () {
    expect(ageOf(ageDate(1850, 3, 15), ageDate(1922, 3, 14)))->toBe(['unit' => 'years', 'value' => 71]);
    expect(ageOf(ageDate(1850, 3, 15), ageDate(1922, 3, 15)))->toBe(['unit' => 'years', 'value' => 72]);
});

test('a person without a death event ages up to today', function () {
    expect(ageOf(ageDate(1980), null))->toBe(['unit' => 'years', 'value' => 46]);
    expect(ageOf(ageDate(1980, 11, 1), null))->toBe(['unit' => 'years', 'value' => 45]);
});

test('a living person reaching 110 has no age, 109 is shown', function () {
    expect(ageOf(ageDate(1916), null))->toBeNull();
    expect(ageOf(ageDate(1917), null))->toBe(['unit' => 'years', 'value' => 109]);
});

test('a deceased person older than 110 keeps the age', function () {
    expect(ageOf(ageDate(1800), ageDate(1912)))->toBe(['unit' => 'years', 'value' => 112]);
});

test('an infant with full dates is counted in months', function () {
    expect(ageOf(ageDate(1890, 1, 2), ageDate(1890, 3, 20)))->toBe(['unit' => 'months', 'value' => 2]);
});

test('an infant with full dates under a month is counted in days', function () {
    expect(ageOf(ageDate(1890, 3, 2), ageDate(1890, 3, 19)))->toBe(['unit' => 'days', 'value' => 17]);
});

test('an infant born at the end of a month counts days until the same day number recurs', function () {
    expect(ageOf(ageDate(1890, 1, 31), ageDate(1890, 3, 1)))->toBe(['unit' => 'days', 'value' => 29]);
    expect(ageOf(ageDate(1890, 1, 31), ageDate(1890, 3, 31)))->toBe(['unit' => 'months', 'value' => 2]);
});

test('birth and death on the same day is zero days', function () {
    expect(ageOf(ageDate(1890, 3, 2), ageDate(1890, 3, 2)))->toBe(['unit' => 'days', 'value' => 0]);
});

test('a person who died within the birth year with partial dates is under a year', function () {
    expect(ageOf(ageDate(1890), ageDate(1890)))->toBe(['unit' => 'under_year', 'value' => null]);
    expect(ageOf(ageDate(1890, 1), ageDate(1890, 6, 3)))->toBe(['unit' => 'under_year', 'value' => null]);
});

test('there is no age without a birth year', function () {
    expect(ageOf(null, ageDate(1922)))->toBeNull();
    expect(ageOf(ageDate(null, 3, 1), ageDate(1922)))->toBeNull();
});

test('there is no age for a death without a year', function () {
    expect(ageOf(ageDate(1850), ageDate(null)))->toBeNull();
});

test('a negative result hides the age', function () {
    expect(ageOf(ageDate(1950), ageDate(1940)))->toBeNull();
    expect(ageOf(ageDate(1950, 5, 1), ageDate(1950, 4, 1)))->toBeNull();
    expect(ageOf(ageDate(1950, 5), ageDate(1950, 4)))->toBeNull();
});

test('the approximate flag does not change the result', function () {
    expect(ageOf(ageDate(1850, null, null, true), ageDate(1922, 3, 15, true)))
        ->toBe(ageOf(ageDate(1850), ageDate(1922, 3, 15)));
});
