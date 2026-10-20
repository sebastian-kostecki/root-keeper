<?php

namespace App\Http\Resources;

use App\Models\Cemetery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Cemetery $resource
 *
 * @mixin Cemetery
 */
class CemeteryResource extends JsonResource
{
    /**
     * Transform the resource into an array. The locality relation must be loaded (the label needs it).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'locality_id' => $this->locality_id,
            'locality_name' => $this->locality->name,
            'manager' => $this->manager?->value,
            'manager_label' => $this->manager?->label(),
            'religion' => $this->religion?->value,
            'religion_label' => $this->religion?->label(),
            'description' => $this->description,
            'label' => $this->resource->label(),
            'sources_count' => $this->whenCounted('sources'),
            'burials_count' => $this->whenCounted('burials'),
        ];
    }
}
