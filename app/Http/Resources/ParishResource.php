<?php

namespace App\Http\Resources;

use App\Models\Parish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Parish $resource
 *
 * @mixin Parish
 */
class ParishResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'religion' => $this->religion?->value,
            'religion_label' => $this->religion?->label(),
            'locality_id' => $this->locality_id,
            'locality_name' => $this->locality?->name,
            'label' => $this->religion === null ? $this->name : "{$this->name} ({$this->religion->label()})",
            'sources_count' => $this->whenCounted('sources'),
        ];
    }
}
