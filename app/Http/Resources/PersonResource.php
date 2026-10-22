<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Support\PersonOrdering;
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
     *     gender: array{value: string, label: string}|null,
     *     religion: array{value: string, label: string}|null,
     *     birth: array<string, mixed>|null,
     *     death: array<string, mixed>|null,
     *     burial_place: string|null,
     *     age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null,
     *     photo_version: string|null,
     *     parents?: list<array<string, mixed>>,
     *     children?: list<array<string, mixed>>,
     *     marriages?: list<array<string, mixed>>,
     *     residences?: list<array{id: int, locality: array{id: int, name: string}, year_from: int|null, year_to: int|null}>,
     *     gravestones?: list<array{id: int, display_name: string, cemetery_label: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->displayName(),
            'gender' => $this->gender === null ? null : [
                'value' => $this->gender->value,
                'label' => $this->gender->label(),
            ],
            'religion' => $this->religion === null ? null : [
                'value' => $this->religion->value,
                'label' => $this->religion->label(),
            ],
            'birth' => $this->birth() === null ? null : EventResource::make($this->birth())->resolve(),
            'death' => $this->death() === null ? null : EventResource::make($this->death())->resolve(),
            'burial_place' => $this->burial()?->cemetery?->label() ?? $this->burial()?->locality?->name,
            'age' => $this->age()?->toArray(),
            'photo_version' => $this->photo_thumbnail_path === null ? null : pathinfo($this->photo_thumbnail_path, PATHINFO_FILENAME),
            'parents' => $this->whenLoaded('parents', fn (): array => RelativeResource::collection(
                PersonOrdering::relatives($this->parents)
            )->resolve()),
            'children' => $this->whenLoaded('children', fn (): array => RelativeResource::collection(
                PersonOrdering::relatives($this->children)
            )->resolve()),
            'marriages' => $this->whenLoaded('marriages', fn (): array => PersonOrdering::marriages($this->marriages)
                ->map(fn (Event $marriage): array => MarriageResource::make($marriage, $this->resource)->resolve())
                ->all()),
            'residences' => $this->whenLoaded('residences', fn (): array => array_values(ResidenceResource::collection(
                PersonOrdering::residences($this->residences)
            )->resolve())),
            'gravestones' => $this->whenLoaded('gravestones', fn (): array => $this->gravestones
                ->map(fn (Source $source): array => [
                    'id' => $source->id,
                    'display_name' => $source->displayName(),
                    'cemetery_label' => $source->cemetery?->label(),
                ])
                ->values()
                ->all()),
        ];
    }
}
