<?php

namespace App\Actions\Persons;

use App\Enums\DateQualifier;
use App\Models\Tree;

/**
 * Shared mapping from the birth/death form input shape to event fillable
 * attributes, plus checkbox coercion, so create and update actions cannot
 * diverge.
 */
trait MapsEventInput
{
    /**
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, locality_id?: int|string|null}  $date
     * @return array{date_year: int|string|null, date_month: int|string|null, date_day: int|string|null, date_qualifier: DateQualifier, locality_id: int|string|null}
     */
    private function eventAttributesFrom(array $date): array
    {
        return [
            'date_year' => $date['year'] ?? null,
            'date_month' => $date['month'] ?? null,
            'date_day' => $date['day'] ?? null,
            'date_qualifier' => $this->isChecked($date['is_approximate'] ?? null) ? DateQualifier::Approximate : DateQualifier::Exact,
            'locality_id' => $date['locality_id'] ?? null,
        ];
    }

    /**
     * A marriage's date and place plus its wedding form, which a missing or blank key resets to unknown like the date.
     *
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, locality_id?: int|string|null, form?: string|null}  $marriage
     * @return array{date_year: int|string|null, date_month: int|string|null, date_day: int|string|null, date_qualifier: DateQualifier, locality_id: int|string|null, marriage_form: string|null}
     */
    private function marriageAttributesFrom(array $marriage): array
    {
        return [
            ...$this->eventAttributesFrom($marriage),
            'marriage_form' => $marriage['form'] ?? null,
        ];
    }

    /**
     * The burial place from the form: the chosen cemetery and its locality, which every burial keeps equal to the
     * cemetery's. Null when no cemetery is chosen, i.e. there is no burial.
     *
     * @param  array{cemetery_id?: int|string|null}  $burial
     * @return array{locality_id: int, cemetery_id: int}|null
     */
    private function burialPlaceFrom(Tree $tree, array $burial): ?array
    {
        $cemeteryId = $burial['cemetery_id'] ?? null;

        if (blank($cemeteryId)) {
            return null;
        }

        $localityId = $tree->cemeteries()->whereKey($cemeteryId)->value('locality_id');

        return $localityId === null ? null : ['locality_id' => $localityId, 'cemetery_id' => (int) $cemeteryId];
    }

    private function isChecked(bool|int|string|null $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
