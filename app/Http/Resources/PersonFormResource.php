<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use App\Support\PersonOrdering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Raw values for prefilling the person edit modal on the preview, including whether each
 * event is confirmed by a linked source.
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
     *     maiden_name: string|null,
     *     gender: string|null,
     *     religion: string|null,
     *     is_deceased: bool,
     *     birth: array{year: int|null, month: int|null, day: int|null, is_approximate: bool, locality: array{id: int, name: string}|null, is_confirmed: bool}|null,
     *     death: array{year: int|null, month: int|null, day: int|null, is_approximate: bool, locality: array{id: int, name: string}|null, is_confirmed: bool}|null,
     *     burial: array{locality: array{id: int, name: string}|null}|null,
     *     residences: list<array{id: int, locality: array{id: int, name: string}, year_from: int|null, year_to: int|null}>,
     *     burial_suggestion: array{id: int, name: string}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'maiden_name' => $this->maiden_name,
            'gender' => $this->gender?->value,
            'religion' => $this->religion?->value,
            'is_deceased' => $this->death() !== null,
            'birth' => $this->formEvent($this->birth()),
            'death' => $this->formEvent($this->death()),
            'burial' => $this->burial() === null ? null : ['locality' => $this->localityOf($this->burial())],
            'residences' => array_values(ResidenceResource::collection(PersonOrdering::residences($this->residences))->resolve()),
            'burial_suggestion' => $this->whenLoaded('gravestones', fn (): ?array => $this->burialSuggestion(), null),
        ];
    }

    /**
     * The cemetery of the person's gravestones when they all share exactly one, otherwise null.
     *
     * @return array{id: int, name: string}|null
     */
    private function burialSuggestion(): ?array
    {
        $cemeteries = $this->gravestones->pluck('cemetery')->filter()->unique('id')->values();

        if ($cemeteries->count() !== 1) {
            return null;
        }

        return ['id' => $cemeteries->first()->id, 'name' => $cemeteries->first()->name];
    }

    /**
     * @return array{year: int|null, month: int|null, day: int|null, is_approximate: bool, locality: array{id: int, name: string}|null, is_confirmed: bool}|null
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
            'locality' => $this->localityOf($event),
            'is_confirmed' => $event->sources->isNotEmpty(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function localityOf(?Event $event): ?array
    {
        $locality = $event?->locality;

        return $locality === null ? null : ['id' => $locality->id, 'name' => $locality->name];
    }
}
