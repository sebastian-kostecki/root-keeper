<?php

namespace App\Http\Resources;

use App\Models\Event;
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
     *     date_parts: array{year: int|null, month: int|null, day: int|null, is_approximate: bool},
     *     is_confirmed?: bool,
     *     sources?: list<array{id: int, kind: string, original_name: string, has_thumbnail: bool, uploaded_at: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'date' => $this->date->format(),
            'place' => $this->place,
            'date_parts' => [
                'year' => $this->date_year,
                'month' => $this->date_month,
                'day' => $this->date_day,
                'is_approximate' => $this->date_is_approximate,
            ],
            'is_confirmed' => $this->when(
                $this->relationLoaded('sources') || array_key_exists('sources_exists', $this->getAttributes()),
                fn (): bool => $this->relationLoaded('sources')
                    ? $this->sources->isNotEmpty()
                    : (bool) $this->getAttribute('sources_exists'),
            ),
            'sources' => $this->whenLoaded('sources', fn (): array => $this->sources->map(fn (Source $source): array => [
                'id' => $source->id,
                'kind' => $source->kind->value,
                'original_name' => $source->original_name,
                'has_thumbnail' => $source->thumbnail_path !== null,
                'uploaded_at' => $source->created_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
