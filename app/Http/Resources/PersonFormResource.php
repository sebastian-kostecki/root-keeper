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
     *     gender: string|null,
     *     religion: string|null,
     *     is_deceased: bool,
     *     birth: array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null, locality: array{id: int, name: string}|null, confidence: string|null, is_confirmed: bool}|null,
     *     death: array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null, locality: array{id: int, name: string}|null, confidence: string|null, is_confirmed: bool}|null,
     *     burial: array{cemetery: array{id: int, label: string, locality_id: int, locality_name: string}|null, confidence: string|null}|null,
     *     residences: list<array{id: int, locality: array{id: int, name: string}, year_from: int|null, year_to: int|null}>,
     *     burial_suggestion: array{id: int, label: string, locality_id: int, locality_name: string}|null,
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
            'religion' => $this->religion?->value,
            'is_deceased' => $this->death() !== null,
            'birth' => $this->formEvent($this->birth()),
            'death' => $this->formEvent($this->death()),
            'burial' => $this->burial() === null ? null : ['cemetery' => $this->burial()->cemetery?->toOption(), 'confidence' => $this->burial()->confidence?->value],
            'residences' => array_values(ResidenceResource::collection(PersonOrdering::residences($this->residences))->resolve()),
            'burial_suggestion' => $this->whenLoaded('gravestones', fn (): ?array => $this->burialSuggestion(), null),
        ];
    }

    /**
     * The cemetery of the person's gravestones when they all share exactly one, otherwise null.
     *
     * @return array{id: int, label: string, locality_id: int, locality_name: string}|null
     */
    private function burialSuggestion(): ?array
    {
        $cemeteries = $this->gravestones->pluck('cemetery')->filter()->unique('id')->values();

        return $cemeteries->count() === 1 ? $cemeteries->first()->toOption() : null;
    }

    /**
     * @return array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null, locality: array{id: int, name: string}|null, confidence: string|null, is_confirmed: bool}|null
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
            'qualifier' => $event->date_qualifier->value,
            'until_year' => $event->date_until_year,
            'locality' => $this->localityOf($event),
            'confidence' => $event->confidence?->value,
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
