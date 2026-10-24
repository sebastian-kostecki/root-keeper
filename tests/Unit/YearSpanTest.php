<?php

use App\Enums\DateQualifier;
use App\Support\Import\ResearchDate;
use App\Support\Import\YearSpan;

function spanOf(string $date): YearSpan
{
    return YearSpan::fromDate(ResearchDate::parse($date));
}

test('has no span without a year', function () {
    expect(YearSpan::fromDate(null))->toBeNull()
        ->and(YearSpan::fromColumns(DateQualifier::Exact, null, null))->toBeNull();
});

test('builds the span from event columns the same way as from a block date', function () {
    expect(YearSpan::fromColumns(DateQualifier::Approximate, 1801, null))->toEqual(spanOf('ok. 1801'))
        ->and(YearSpan::fromColumns(DateQualifier::Range, 1798, 1802))->toEqual(spanOf('między 1798 a 1802'))
        ->and(YearSpan::fromColumns(DateQualifier::Before, 1809, null))->toEqual(spanOf('przed 1809'))
        ->and(YearSpan::fromColumns(DateQualifier::After, 1809, null))->toEqual(spanOf('po 1809'))
        ->and(YearSpan::fromColumns(DateQualifier::Range, 1801, null))->toEqual(spanOf('1801'));
});

test('agrees and contradicts according to the year rule', function (string $left, string $right, bool $agrees, bool $contradicts) {
    expect(spanOf($left)->agreesWith(spanOf($right)))->toBe($agrees)
        ->and(spanOf($right)->agreesWith(spanOf($left)))->toBe($agrees)
        ->and(spanOf($left)->contradicts(spanOf($right)))->toBe($contradicts)
        ->and(spanOf($right)->contradicts(spanOf($left)))->toBe($contradicts);
})->with([
    'same year' => ['1801', '1801', true, false],
    'two years apart' => ['1801', '1803', true, false],
    'three years apart' => ['1801', '1804', false, true],
    'a day and a year are the same year' => ['1801-05-12', '1802', true, false],
    'approximate within two years' => ['ok. 1801', '1803', true, false],
    'approximate beyond two years' => ['ok. 1801', '1804', false, true],
    'range holds the year' => ['między 1798 a 1802', '1800', true, false],
    'range and a year two apart' => ['między 1798 a 1802', '1804', true, false],
    'range and a year three apart' => ['między 1798 a 1802', '1805', false, true],
    'overlapping ranges' => ['między 1790 a 1795', 'między 1795 a 1799', true, false],
    'ranges two years apart' => ['między 1790 a 1795', 'między 1797 a 1799', false, true],
    'before never agrees, but does not contradict an earlier year' => ['przed 1809', '1790', false, false],
    'before contradicts a later year' => ['przed 1809', '1810', false, true],
    'before holds its own year' => ['przed 1809', '1809', false, false],
    'after does not contradict a later year' => ['po 1809', '1850', false, false],
    'after contradicts an earlier year' => ['po 1809', '1808', false, true],
    'before and after that overlap' => ['przed 1809', 'po 1800', false, false],
    'before and after that do not meet' => ['przed 1800', 'po 1809', false, true],
]);
