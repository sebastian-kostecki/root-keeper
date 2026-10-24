<?php

namespace App\Support\Import;

use App\Enums\ThreeWayOutcome;

/**
 * The rule that lets a repo value replace an app value only when nobody changed the app value since the last import.
 *
 * The values are compared in their normalized text form (see the callers); `null` is an empty field.
 */
final class ThreeWayValue
{
    /**
     * @param  string|null  $repo  the repo value now (R)
     * @param  bool  $hasLast  whether the last import left a memory for the field
     * @param  string|null  $last  the repo value at the last import (S), meaningful only when `$hasLast`
     * @param  string|null  $app  the app value now (A)
     * @param  bool  $refines  the repo date refines the app date; meaningful only without a memory
     * @param  bool|null  $repoIsApp  whether R equals A when the plain text comparison is not the right one (dates are compared as objects)
     */
    public static function compare(?string $repo, bool $hasLast, ?string $last, ?string $app, bool $refines = false, ?bool $repoIsApp = null): ThreeWayOutcome
    {
        if ($repoIsApp ?? $repo === $app) {
            return ThreeWayOutcome::Same;
        }

        if ($hasLast) {
            return match (true) {
                $repo === $last => ThreeWayOutcome::KeptManual,
                $app === $last => ThreeWayOutcome::Changed,
                default => ThreeWayOutcome::Conflict,
            };
        }

        return match (true) {
            $repo === null => ThreeWayOutcome::Same,
            $app === null, $refines => ThreeWayOutcome::Fill,
            default => ThreeWayOutcome::Conflict,
        };
    }
}
