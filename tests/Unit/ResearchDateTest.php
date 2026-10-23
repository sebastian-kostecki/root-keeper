<?php

use App\Enums\DateQualifier;
use App\Support\Import\ResearchDate;

test('parses an exact full date', function () {
    $date = ResearchDate::parse('1801-05-12');

    expect($date->qualifier)->toBe(DateQualifier::Exact)
        ->and([$date->year, $date->month, $date->day])->toBe([1801, 5, 12]);
});

test('parses partial dates', function () {
    expect(ResearchDate::parse('1801'))->toEqual(new ResearchDate(DateQualifier::Exact, 1801))
        ->and(ResearchDate::parse('1801-05'))->toEqual(new ResearchDate(DateQualifier::Exact, 1801, 5));
});

test('parses qualifiers', function () {
    expect(ResearchDate::parse('ok. 1825')->qualifier)->toBe(DateQualifier::Approximate)
        ->and(ResearchDate::parse('przed 1770')->qualifier)->toBe(DateQualifier::Before)
        ->and(ResearchDate::parse('po 1730-02')->qualifier)->toBe(DateQualifier::After)
        ->and(ResearchDate::parse('ok. 1801-05-12')->day)->toBe(12);
});

test('parses a range', function () {
    $date = ResearchDate::parse('między 1760 a 1765');

    expect($date->qualifier)->toBe(DateQualifier::Range)
        ->and([$date->year, $date->untilYear])->toBe([1760, 1765]);
});

test('ignores extra whitespace', function () {
    expect(ResearchDate::parse('  ok.   1825 '))->toEqual(ResearchDate::parse('ok. 1825'));
});

test('enforces the year bounds', function () {
    $currentYear = (int) date('Y');

    expect(ResearchDate::parse('0999'))->toBeNull()
        ->and(ResearchDate::parse('1000'))->not->toBeNull()
        ->and(ResearchDate::parse((string) $currentYear))->not->toBeNull()
        ->and(ResearchDate::parse((string) ($currentYear + 1)))->toBeNull();
});

test('rejects impossible calendar dates', function () {
    expect(ResearchDate::parse('1801-02-31'))->toBeNull()
        ->and(ResearchDate::parse('1801-02-29'))->toBeNull()
        ->and(ResearchDate::parse('1804-02-29'))->not->toBeNull()
        ->and(ResearchDate::parse('1801-13'))->toBeNull()
        ->and(ResearchDate::parse('1801-00-10'))->toBeNull();
});

test('rejects a reversed or empty range', function () {
    expect(ResearchDate::parse('między 1811 a 1805'))->toBeNull()
        ->and(ResearchDate::parse('między 1805 a 1805'))->toBeNull();
});

test('rejects text outside the grammar', function () {
    expect(ResearchDate::parse('około 1800'))->toBeNull()
        ->and(ResearchDate::parse('1801-5-7'))->toBeNull()
        ->and(ResearchDate::parse(''))->toBeNull()
        ->and(ResearchDate::parse('ok. ok. 1800'))->toBeNull();
});
