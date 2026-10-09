<?php

namespace App\Http\Resources;

use App\Enums\SourceKind;
use App\Models\Person;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Source $resource
 *
 * @mixin Source
 */
class SourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `locality_name` is the place of the source: the gravestone's cemetery, or the seat of an act's parish.
     * `parish_locality_id` is the id of that seat. Callers eager-load `parish.locality` and `cemetery`; the panel also `buriedPeople`.
     *
     * @return array{
     *     id: int,
     *     kind: string,
     *     kind_label: string,
     *     kind_short_label: string,
     *     name: string|null,
     *     display_name: string,
     *     record_type: string|null,
     *     record_type_label: string|null,
     *     legacy_entry_number: string|null,
     *     year: int|null,
     *     parish_id: int|null,
     *     parish_label: string|null,
     *     parish_name: string|null,
     *     parish_locality_id: int|null,
     *     locality_name: string|null,
     *     cemetery_locality_id: int|null,
     *     cemetery_name: string|null,
     *     buried_people?: list<array{id: int, display_name: string}>,
     *     archive: string|null,
     *     url: string|null,
     *     note: string|null,
     *     original_name: string,
     *     mime_type: string,
     *     size: int,
     *     width: int|null,
     *     height: int|null,
     *     has_thumbnail: bool,
     *     uploaded_at: string|null,
     *     events_count?: int,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'kind_short_label' => $this->kind->shortLabel(),
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'record_type' => $this->record_type?->value,
            'record_type_label' => $this->record_type?->label(),
            'legacy_entry_number' => $this->entry_number,
            'year' => $this->year,
            'parish_id' => $this->parish_id,
            'parish_label' => $this->parish?->label(),
            'parish_name' => $this->parish?->name,
            'parish_locality_id' => $this->parish?->locality_id,
            'locality_name' => $this->kind === SourceKind::Gravestone ? $this->cemetery?->name : $this->parish?->locality?->name,
            'cemetery_locality_id' => $this->cemetery_locality_id,
            'cemetery_name' => $this->cemetery?->name,
            'buried_people' => $this->whenLoaded('buriedPeople', fn (): array => $this->buriedPeople
                ->map(fn (Person $person): array => ['id' => $person->id, 'display_name' => $person->displayName()])
                ->values()
                ->all()),
            'archive' => $this->archive,
            'url' => $this->url,
            'note' => $this->note,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'has_thumbnail' => $this->thumbnail_path !== null,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'events_count' => $this->whenCounted('events'),
        ];
    }
}
