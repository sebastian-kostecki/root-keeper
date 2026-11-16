<?php

use App\Enums\RepoKeyKind;
use App\Support\Import\RepoKeys;

test('builds the key of a burial and tells its kind and person', function () {
    expect(RepoKeys::burial('O-0042'))->toBe('O-0042/pochowek')
        ->and(RepoKeys::kindOf('O-0042/pochowek'))->toBe(RepoKeyKind::Burial)
        ->and(RepoKeys::personIdsOf('O-0042/pochowek'))->toBe(['O-0042']);
});

test('builds the key of a source and tells that it names no person', function () {
    expect(RepoKeys::source('ZR-0012'))->toBe('ZR-0012')
        ->and(RepoKeys::kindOf('ZR-0012'))->toBe(RepoKeyKind::Source)
        ->and(RepoKeys::personIdsOf('ZR-0012'))->toBe([])
        ->and(RepoKeys::kindOf('O-0042'))->toBe(RepoKeyKind::Person);
});
