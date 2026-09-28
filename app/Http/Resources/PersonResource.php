<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;
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
     *     residence: string|null,
     *     religion: array{value: string, label: string}|null,
     *     birth: array<string, mixed>|null,
     *     death: array<string, mixed>|null,
     *     parents?: list<array<string, mixed>>,
     *     children?: list<array<string, mixed>>,
     *     marriages?: list<array<string, mixed>>,
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
            'residence' => $this->residence,
            'religion' => $this->religion === null ? null : [
                'value' => $this->religion->value,
                'label' => $this->religion->label(),
            ],
            'birth' => $this->birth() === null ? null : EventResource::make($this->birth())->resolve(),
            'death' => $this->death() === null ? null : EventResource::make($this->death())->resolve(),
            'parents' => $this->whenLoaded('parents', fn (): array => RelativeResource::collection(
                $this->sortRelatives($this->parents)
            )->resolve()),
            'children' => $this->whenLoaded('children', fn (): array => RelativeResource::collection(
                $this->sortRelatives($this->children)
            )->resolve()),
            'marriages' => $this->whenLoaded('marriages', fn (): array => $this->sortMarriages($this->marriages)
                ->map(fn (Event $marriage): array => MarriageResource::make($marriage, $this->resource)->resolve())
                ->all()),
        ];
    }

    /**
     * Order relatives by birth date (known year first, then year/month/day
     * ascending with a missing month/day sorting before a known one within
     * the same year), then by name.
     *
     * @param  Collection<int, Person>  $relatives
     * @return Collection<int, Person>
     */
    private function sortRelatives(Collection $relatives): Collection
    {
        return $relatives->sort(
            fn (Person $a, Person $b): int => $this->relativeSortKey($a) <=> $this->relativeSortKey($b)
        )->values();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: string, 6: string, 7: int}
     */
    private function relativeSortKey(Person $person): array
    {
        $date = $person->birth()?->date;

        return [
            $date?->year === null ? 1 : 0,
            $date->year ?? 0,
            $date->month ?? 0,
            $date->day ?? 0,
            $person->last_name === null ? 1 : 0,
            $person->last_name ?? '',
            $person->first_name ?? '',
            $person->id,
        ];
    }

    /**
     * Order marriages by date (known year first, then year/month/day
     * ascending with a missing month/day sorting before a known one within
     * the same year), then by id.
     *
     * @param  Collection<int, Event>  $marriages
     * @return Collection<int, Event>
     */
    private function sortMarriages(Collection $marriages): Collection
    {
        return $marriages->sort(
            fn (Event $a, Event $b): int => $this->marriageSortKey($a) <=> $this->marriageSortKey($b)
        )->values();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function marriageSortKey(Event $marriage): array
    {
        return [
            $marriage->date_year === null ? 1 : 0,
            $marriage->date_year ?? 0,
            $marriage->date_month ?? 0,
            $marriage->date_day ?? 0,
            $marriage->id,
        ];
    }
}
