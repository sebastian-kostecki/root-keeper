<?php

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The family sheet's card payload. Requires the `events` relation loaded.
 *
 * @property-read Person $resource
 *
 * @mixin Person
 */
class SheetPersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     display_name: string,
     *     gender: 'male'|'female'|null,
     *     lifespan: string|null,
     *     is_deceased: bool,
     *     age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null,
     *     photo_version: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $death = $this->death();
        $birthYear = $this->birth()?->date->formatYear();

        if ($death === null) {
            $lifespan = $birthYear === null ? null : "{$birthYear} –";
        } else {
            $lifespan = ($birthYear ?? '?').' – '.($death->date->formatYear() ?? '?');
        }

        return [
            'id' => $this->id,
            'display_name' => $this->displayName(),
            'gender' => $this->gender?->value,
            'lifespan' => $lifespan,
            'is_deceased' => $death !== null,
            'age' => $this->age()?->toArray(),
            'photo_version' => $this->photo_thumbnail_path === null ? null : pathinfo($this->photo_thumbnail_path, PATHINFO_FILENAME),
        ];
    }
}
