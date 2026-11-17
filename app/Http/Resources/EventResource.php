<?php

namespace App\Http\Resources;

use App\Enums\EventType;
use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\EventSource;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Event $resource
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `is_confirmed` needs either the `sources` relation (preview) or the `sources_exists`
     * attribute from `withExists('sources')` (persons index, which never sends source bodies).
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     date: string|null,
     *     place: string|null,
     *     locality_id: int|null,
     *     date_parts: array{year: int|null, month: int|null, day: int|null, qualifier: string, until_year: int|null},
     *     confidence: string|null,
     *     confidence_label: string|null,
     *     marriage_form?: string|null,
     *     marriage_form_label?: string|null,
     *     is_confirmed?: bool,
     *     confirmed_kinds?: list<string>,
     *     sources?: list<array{id: int, kind: string, kind_short_label: string, original_name: string, display_name: string, has_thumbnail: bool, uploaded_at: string|null, entry_number: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'date' => $this->date->format(),
            'place' => $this->locality?->name,
            'locality_id' => $this->locality_id,
            'date_parts' => [
                'year' => $this->date_year,
                'month' => $this->date_month,
                'day' => $this->date_day,
                'qualifier' => $this->date_qualifier->value,
                'until_year' => $this->date_until_year,
            ],
            'confidence' => $this->confidence?->value,
            'confidence_label' => $this->confidence?->label(),
            'marriage_form' => $this->when($this->type === EventType::Marriage, fn (): ?string => $this->marriage_form?->value),
            'marriage_form_label' => $this->when($this->type === EventType::Marriage, fn (): ?string => $this->marriage_form?->label()),
            'is_confirmed' => $this->when(
                $this->relationLoaded('sources') || array_key_exists('sources_exists', $this->getAttributes()),
                fn (): bool => $this->relationLoaded('sources')
                    ? $this->sources->isNotEmpty()
                    : (bool) $this->getAttribute('sources_exists'),
            ),
            'confirmed_kinds' => $this->whenLoaded('sources', function (): array {
                $present = $this->sources->map(fn (Source $source): SourceKind => $source->kind)->unique();

                return array_values(array_map(
                    fn (SourceKind $kind): string => $kind->shortLabel(),
                    array_filter(SourceKind::cases(), fn (SourceKind $kind): bool => $present->contains($kind)),
                ));
            }),
            'sources' => $this->whenLoaded('sources', fn (): array => $this->sources->map(fn (Source $source): array => [
                'id' => $source->id,
                'kind' => $source->kind->value,
                'kind_short_label' => $source->kind->shortLabel(),
                'original_name' => $source->original_name,
                'display_name' => $source->displayName(),
                'has_thumbnail' => $source->thumbnail_path !== null,
                'uploaded_at' => $source->created_at?->toIso8601String(),
                'entry_number' => $this->pivotOf($source)->entry_number,
            ])->all()),
        ];
    }

    /**
     * The event-source link a source carries when it was loaded through `Event::sources()`.
     */
    private function pivotOf(Source $source): EventSource
    {
        /** @var EventSource $pivot */
        $pivot = $source->getRelation('pivot');

        return $pivot;
    }
}
