<?php

namespace App\Http\Resources;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Scan $resource
 *
 * @mixin Scan
 */
class ScanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
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
