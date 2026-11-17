<?php

use App\Support\Import\PlaceName;

test('the key ignores capitals, polish characters and extra spaces', function () {
    expect(PlaceName::key('Kołemczyce'))->toBe(PlaceName::key(' kolemczyce '))
        ->and(PlaceName::key('Stary  Majdan'))->toBe('stary majdan')
        ->and(PlaceName::key('   '))->toBeNull()
        ->and(PlaceName::key(null))->toBeNull();
});

test('decides which different spellings look like one place', function (string $a, string $b, bool $similar) {
    expect(PlaceName::similar(PlaceName::key($a), PlaceName::key($b)))->toBe($similar)
        ->and(PlaceName::similar(PlaceName::key($b), PlaceName::key($a)))->toBe($similar);
})->with([
    'the same key' => ['Kołemczyce', 'Kolemczyce', true],
    'one edit in seven characters' => ['Bukinia', 'Bokinia', true],
    'a swapped consonant' => ['Roztoka', 'Rostoka', true],
    'two edits in a long name' => ['Barbarówka', 'Barbarowina', true],
    'two edits in a six character name' => ['Bielin', 'Bielsk', false],
    'one edit in a short name' => ['Rudka', 'Rutka', true],
    'the same words in another order' => ['Stary Majdan', 'Majdan Stary', true],
    'one name containing the other' => ['Siedliszcze', 'Siedliszcze Bramowe', false],
]);
