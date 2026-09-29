<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Scan;
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
     * @return array{
     *     id: int,
     *     type: string,
     *     date: string|null,
     *     place: string|null,
     *     date_parts: array{year: int|null, month: int|null, day: int|null, is_approximate: bool},
     *     is_confirmed?: bool,
     *     scans?: list<array{id: int, original_name: string, has_thumbnail: bool, uploaded_at: string|null}>,
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
                $this->relationLoaded('scans') || array_key_exists('scans_exists', $this->getAttributes()),
                fn (): bool => $this->relationLoaded('scans')
                    ? $this->scans->isNotEmpty()
                    : (bool) $this->getAttribute('scans_exists'),
            ),
            'scans' => $this->whenLoaded('scans', fn (): array => $this->scans->map(fn (Scan $scan): array => [
                'id' => $scan->id,
                'original_name' => $scan->original_name,
                'has_thumbnail' => $scan->thumbnail_path !== null,
                'uploaded_at' => $scan->created_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
