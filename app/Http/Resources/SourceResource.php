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
     *     original_name: string,
     *     mime_type: string,
     *     size: int,
     *     width: int|null,
     *     height: int|null,
     *     has_thumbnail: bool,
     *     uploaded_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'has_thumbnail' => $this->thumbnail_path !== null,
            'uploaded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
