<?php

use App\Enums\ThreeWayOutcome;
use App\Support\Import\ThreeWayValue;

test('decides the three-way cases', function (?string $repo, bool $hasLast, ?string $last, ?string $app, bool $refines, ThreeWayOutcome $expected) {
    expect(ThreeWayValue::compare($repo, $hasLast, $last, $app, $refines))->toBe($expected);
})->with([
    'manual change stays, repo unchanged' => ['1801', true, '1801', '1830', false, ThreeWayOutcome::KeptManual],
    'repo changed, app untouched' => ['1802', true, '1801', '1801', false, ThreeWayOutcome::Changed],
    'both changed' => ['1802', true, '1801', '1830', false, ThreeWayOutcome::Conflict],
    'both changed to the same value' => ['1802', true, '1801', '1802', false, ThreeWayOutcome::Same],
    'repo cleared the field, app untouched' => [null, true, '1801', '1801', false, ThreeWayOutcome::Changed],
    'repo cleared the field, app changed' => [null, true, '1801', '1830', false, ThreeWayOutcome::Conflict],
    'repo fills an empty app field without memory' => ['1801', false, null, null, false, ThreeWayOutcome::Fill],
    'repo refines the app date without memory' => ['1801-05-12', false, null, '1801', true, ThreeWayOutcome::Fill],
    'repo differs from the app without memory' => ['1801', false, null, '1830', false, ThreeWayOutcome::Conflict],
    'repo is empty without memory' => [null, false, null, '1830', false, ThreeWayOutcome::Same],
    'both empty' => [null, false, null, null, false, ThreeWayOutcome::Same],
    'repo equals app without memory' => ['1801', false, null, '1801', false, ThreeWayOutcome::Same],
    'memory of an empty field, repo filled it, app empty' => ['1801', true, null, null, false, ThreeWayOutcome::Changed],
    'memory of an empty field, repo filled it, app filled by hand' => ['1801', true, null, '1830', false, ThreeWayOutcome::Conflict],
]);

test('ignores a refinement when the last import left a memory', function () {
    expect(ThreeWayValue::compare('1801-05-12', true, '1801', '1830', true))->toBe(ThreeWayOutcome::Conflict)
        ->and(ThreeWayValue::compare('1801-05-12', true, '1801', '1801', true))->toBe(ThreeWayOutcome::Changed);
});

test('takes the object comparison of the caller in place of the text equality', function () {
    expect(ThreeWayValue::compare('1801-05', false, null, '1801-05', repoIsApp: false))->toBe(ThreeWayOutcome::Conflict)
        ->and(ThreeWayValue::compare('1801-05', false, null, '1801-06', repoIsApp: true))->toBe(ThreeWayOutcome::Same)
        ->and(ThreeWayValue::compare('1802', true, '1801', '1801', repoIsApp: true))->toBe(ThreeWayOutcome::Same);
});
