<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Raw values for prefilling the person edit form, including whether each
 * event is confirmed by a linked scan.
 *
 * @property-read Person $resource
 *
 * @mixin Person
 */
class PersonFormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     display_name: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     gender: string|null,
     *     residence: string|null,
     *     religion: string|null,
     *     is_deceased: bool,
     *     birth: array{year: int|null, month: int|null, day: int|null, is_approximate: bool, place: string|null, is_confirmed: bool}|null,
     *     death: array{year: int|null, month: int|null, day: int|null, is_approximate: bool, place: string|null, is_confirmed: bool}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender?->value,
            'residence' => $this->residence,
            'religion' => $this->religion?->value,
            'is_deceased' => $this->death() !== null,
            'birth' => $this->formEvent($this->birth()),
            'death' => $this->formEvent($this->death()),
        ];
    }

    /**
     * @return array{year: int|null, month: int|null, day: int|null, is_approximate: bool, place: string|null, is_confirmed: bool}|null
     */
    private function formEvent(?Event $event): ?array
    {
        if ($event === null) {
            return null;
        }

        return [
            'year' => $event->date_year,
            'month' => $event->date_month,
            'day' => $event->date_day,
            'is_approximate' => $event->date_is_approximate,
            'place' => $event->place,
            'is_confirmed' => $event->scans->isNotEmpty(),
        ];
    }
}
