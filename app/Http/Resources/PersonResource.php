<?php

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Person $resource
 *
 * @mixin Person
 */
class PersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     first_name: string|null,
     *     last_name: string|null,
     *     display_name: string,
     *     residence: string|null,
     *     religion: array{value: string, label: string}|null,
     *     birth: array<string, mixed>|null,
     *     death: array<string, mixed>|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->displayName(),
            'residence' => $this->residence,
            'religion' => $this->religion === null ? null : [
                'value' => $this->religion->value,
                'label' => $this->religion->label(),
            ],
            'birth' => $this->birth() === null ? null : EventResource::make($this->birth())->resolve(),
            'death' => $this->death() === null ? null : EventResource::make($this->death())->resolve(),
        ];
    }
}
