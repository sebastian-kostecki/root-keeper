<?php

use App\Support\Import\NoteBlock;

const NOTE_BLOCK = "Z repo badawczego (O-0042):\nUrodzenie: Zalesie; poziom A\nZgon: Wola";

test('finds the block between a hand-written text before and after it', function () {
    $note = "Mój dopisek.\n\n".NOTE_BLOCK."\n\nInny dopisek.";

    expect(NoteBlock::find($note, 'O-0042'))->toBe(NOTE_BLOCK);
});

test('finds the block at the end of a note with a trailing newline', function () {
    expect(NoteBlock::find(NOTE_BLOCK."\n", 'O-0042'))->toBe(NOTE_BLOCK);
});

test('finds the block of a note with Windows line endings', function () {
    expect(NoteBlock::find(str_replace("\n", "\r\n", "Dopisek.\n\n".NOTE_BLOCK."\n\nReszta."), 'O-0042'))->toBe(NOTE_BLOCK);
});

test('keeps a line written under the block as part of it', function () {
    expect(NoteBlock::find(NOTE_BLOCK."\nMój dopisek pod blokiem.", 'O-0042'))->toBe(NOTE_BLOCK."\nMój dopisek pod blokiem.");
});

test('does not find the block of another person or in an empty note', function (?string $note) {
    expect(NoteBlock::find($note, 'O-0042'))->toBeNull();
})->with([
    'another person' => ["Z repo badawczego (O-0043):\nUrodzenie: Zalesie"],
    'a longer id' => ["Z repo badawczego (O-00420):\nUrodzenie: Zalesie"],
    'a header inside a line' => ['Zob. Z repo badawczego (O-0042): nic'],
    'no note' => [null],
    'empty note' => [''],
]);

test('replaces only the block and keeps the text around it', function () {
    $note = "Mój dopisek.\n\n".NOTE_BLOCK."\n\nInny dopisek.";
    $new = "Z repo badawczego (O-0042):\nUrodzenie: Wola";

    expect(NoteBlock::replace($note, 'O-0042', $new))->toBe("Mój dopisek.\n\n{$new}\n\nInny dopisek.");
});

test('keeps the block of another person when it replaces', function () {
    $other = "Z repo badawczego (O-0043):\nUrodzenie: Zalesie";
    $new = "Z repo badawczego (O-0042):\nUrodzenie: Wola";

    expect(NoteBlock::replace($other."\n\n".NOTE_BLOCK, 'O-0042', $new))->toBe($other."\n\n".$new);
});

test('appends the block after an empty line when the note has none', function () {
    expect(NoteBlock::replace('Mój dopisek.', 'O-0042', NOTE_BLOCK))->toBe("Mój dopisek.\n\n".NOTE_BLOCK);
});

test('uses the block alone for an empty note', function (?string $note) {
    expect(NoteBlock::replace($note, 'O-0042', NOTE_BLOCK))->toBe(NOTE_BLOCK);
})->with([[null], [''], ["  \n"]]);

test('removes the block together with the empty line before it', function (string $note, ?string $expected) {
    expect(NoteBlock::replace($note, 'O-0042', null))->toBe($expected);
})->with([
    'in the middle' => ["Przed.\n\n".NOTE_BLOCK."\n\nPo.", "Przed.\n\nPo."],
    'at the end' => ["Przed.\n\n".NOTE_BLOCK, 'Przed.'],
    'at the start' => [NOTE_BLOCK."\n\nPo.", 'Po.'],
    'alone' => [NOTE_BLOCK, null],
]);

test('leaves a note without the block as it is when it removes', function () {
    expect(NoteBlock::replace('Mój dopisek.', 'O-0042', null))->toBe('Mój dopisek.')
        ->and(NoteBlock::replace(null, 'O-0042', null))->toBeNull();
});
