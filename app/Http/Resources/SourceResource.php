<?php

namespace App\Http\Resources;

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
     * @return array{
     *     id: int,
     *     kind: string,
     *     kind_label: string,
     *     kind_short_label: string,
     *     name: string|null,
     *     display_name: string,
     *     record_type: string|null,
     *     record_type_label: string|null,
     *     entry_number: string|null,
     *     year: int|null,
     *     provenance: string|null,
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
            'entry_number' => $this->entry_number,
            'year' => $this->year,
            'provenance' => $this->provenance,
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
