<?php

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Person $resource
 * @property-read object{id: int} $pivot
 *
 * @mixin Person
 */
class RelativeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     relation_id: int,
     *     id: int,
     *     display_name: string,
     *     birth_date: string|null,
     *     death_date: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'relation_id' => $this->pivot->id,
            'id' => $this->id,
            'display_name' => $this->displayName(),
            'birth_date' => $this->birth()?->date->format(),
            'death_date' => $this->death()?->date->format(),
        ];
    }
}
