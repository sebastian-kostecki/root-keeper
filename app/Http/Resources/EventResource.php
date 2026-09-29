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
            'is_confirmed' => $this->whenLoaded('scans', fn (): bool => $this->scans->isNotEmpty()),
            'scans' => $this->whenLoaded('scans', fn (): array => $this->scans->map(fn (Scan $scan): array => [
                'id' => $scan->id,
                'original_name' => $scan->original_name,
                'has_thumbnail' => $scan->thumbnail_path !== null,
                'uploaded_at' => $scan->created_at?->toIso8601String(),
            ])->all()),
        ];
    }
}
