<?php

use App\Enums\RepoKeyKind;
use App\Support\Import\RepoKeys;

test('builds the key of a burial and tells its kind and person', function () {
    expect(RepoKeys::burial('O-0042'))->toBe('O-0042/pochowek')
        ->and(RepoKeys::kindOf('O-0042/pochowek'))->toBe(RepoKeyKind::Burial)
        ->and(RepoKeys::personIdsOf('O-0042/pochowek'))->toBe(['O-0042']);
});
