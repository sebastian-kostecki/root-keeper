<?php

namespace App\Http\Resources;

use App\Models\Event;
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
     * @return array{type: string, date: string|null, place: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'date' => $this->date->format(),
            'place' => $this->place,
        ];
    }
}
