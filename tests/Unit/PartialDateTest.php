<?php

use App\Support\PartialDate;

test('formats a full date', function () {
    $date = new PartialDate(year: 1850, month: 3, day: 12, isApproximate: false);

    expect($date->format())->toBe('12.03.1850');
});

test('formats a year and month', function () {
    $date = new PartialDate(year: 1850, month: 3, day: null, isApproximate: false);

    expect($date->format())->toBe('03.1850');
});

test('formats a year only', function () {
    $date = new PartialDate(year: 1850, month: null, day: null, isApproximate: false);

    expect($date->format())->toBe('1850');
});

test('prefixes an approximate year with ok.', function () {
    $date = new PartialDate(year: 1850, month: null, day: null, isApproximate: true);

    expect($date->format())->toBe('ok. 1850');
});

test('returns null when there is no year', function () {
    $date = new PartialDate(year: null, month: null, day: null, isApproximate: false);

    expect($date->format())->toBeNull();
});
