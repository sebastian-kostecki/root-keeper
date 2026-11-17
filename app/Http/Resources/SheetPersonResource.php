<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The family sheet's card payload. Requires the `events` relation loaded;
 * `withDetails()` also needs `events.locality` and adds the pair card's data.
 *
 * @property-read Person $resource
 *
 * @mixin Person
 */
class SheetPersonResource extends JsonResource
{
    private bool $withDetails = false;

    /**
     * Include birth, death and burial data, as printed on the pair card.
     */
    public function withDetails(bool $withDetails = true): static
    {
        $this->withDetails = $withDetails;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     display_name: string,
     *     card_name: string,
     *     card_given_name: string,
     *     card_surname: string,
     *     gender: 'male'|'female'|null,
     *     lifespan: string|null,
     *     is_deceased: bool,
     *     age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null,
     *     photo_version: string|null,
     *     details?: array{
     *         birth: array{date_parts: array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null}, place: string|null}|null,
     *         death: array{date_parts: array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null}, place: string|null}|null,
     *         burial_place: string|null,
     *     },
     * }
     */
    public function toArray(Request $request): array
    {
        $death = $this->death();
        $birthYear = $this->birth()?->date->formatYear();

        if ($death === null) {
            $lifespan = $birthYear === null ? null : "{$birthYear} –";
        } else {
            $lifespan = ($birthYear ?? '?').' – '.($death->date->formatYear() ?? '?');
        }

        $nameParts = $this->cardNameParts();

        return [
            'id' => $this->id,
            'display_name' => $this->displayName(),
            'card_name' => $this->cardName(),
            'card_given_name' => $nameParts['given'],
            'card_surname' => $nameParts['surname'],
            'gender' => $this->gender?->value,
            'lifespan' => $lifespan,
            'is_deceased' => $death !== null,
            'age' => $this->age()?->toArray(),
            'photo_version' => $this->photo_thumbnail_path === null ? null : pathinfo($this->photo_thumbnail_path, PATHINFO_FILENAME),
            'details' => $this->when($this->withDetails, fn (): array => [
                'birth' => $this->eventDetails($this->birth()),
                'death' => $this->eventDetails($death),
                'burial_place' => $this->burial()?->locality?->name,
            ]),
        ];
    }

    /**
     * @return array{date_parts: array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null}, place: string|null}|null
     */
    private function eventDetails(?Event $event): ?array
    {
        if ($event === null || ($event->date_year === null && $event->locality_id === null)) {
            return null;
        }

        return [
            'date_parts' => [
                'year' => $event->date_year,
                'month' => $event->date_month,
                'day' => $event->date_day,
                'qualifier' => $event->date_qualifier->value,
                'until_year' => $event->date_until_year,
            ],
            'place' => $event->locality?->name,
        ];
    }
}
