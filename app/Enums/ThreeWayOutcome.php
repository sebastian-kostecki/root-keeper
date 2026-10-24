<?php

namespace App\Enums;

/**
 * What the three values of a field (repo now, repo at the last import, app now) say should happen to the field.
 */
enum ThreeWayOutcome: string
{
    /** The repo and the app agree, or the repo has nothing to say; nothing to write. */
    case Same = 'same';

    /** The app value was changed by hand and the repo did not change; the app value stays. */
    case KeptManual = 'kept_manual';

    /** The repo changed and nobody touched the app value; the repo value replaces it. */
    case Changed = 'changed';

    /** The repo and the app both moved away from the last import; the author decides. */
    case Conflict = 'conflict';

    /** No memory of the last import and the app field is empty or the repo refines it; the repo value fills it. */
    case Fill = 'fill';
}
