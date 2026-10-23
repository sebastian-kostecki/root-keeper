<?php

namespace App\Http\Resources;

use App\Enums\EventType;
use App\Enums\Gender;
use App\Support\TimelineEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TimelineEntry $resource
 */
class TimelineEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     label: string,
     *     date: string|null,
     *     year: int|null,
     *     qualifier: string,
     *     until_year: int|null,
     *     date_has_month: bool,
     *     age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null,
     *     place: string|null,
     *     relative: array{id: int, display_name: string}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource->event;
        $relative = $this->resource->relative;

        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'label' => $this->label(),
            'date' => $event->date_month === null ? $event->date->format() : $event->date->formatWithoutQualifier(),
            'year' => $event->date_year,
            'qualifier' => $event->date_qualifier->value,
            'until_year' => $event->date_until_year,
            'date_has_month' => $event->date_month !== null,
            'age' => $this->resource->age?->toArray(),
            'place' => $event->cemetery?->label() ?? $event->locality?->name,
            'relative' => $relative === null ? null : [
                'id' => $relative->id,
                'display_name' => $relative->displayName(),
            ],
        ];
    }

    /**
     * The Polish label of the entry, depending on its type, the role of the viewed person and the relative's gender.
     */
    private function label(): string
    {
        $entry = $this->resource;

        if ($entry->role === 'self') {
            return $entry->event->type->label();
        }

        $gender = $entry->relative?->gender;

        if ($entry->role === 'child') {
            $noun = match ($gender) {
                Gender::Male => 'syna',
                Gender::Female => 'córki',
                null => 'dziecka',
            };

            return $entry->event->type === EventType::Birth ? "Urodzenie {$noun}" : "Zgon {$noun}";
        }

        return match ($gender) {
            Gender::Male => 'Zgon męża',
            Gender::Female => 'Zgon żony',
            null => 'Zgon małżonka',
        };
    }
}
