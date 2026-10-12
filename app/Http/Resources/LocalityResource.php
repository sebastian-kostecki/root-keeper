<?php

namespace App\Http\Resources;

use App\Models\Locality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Locality $resource
 *
 * @mixin Locality
 */
class LocalityResource extends JsonResource
{
    /**
     * Transform the resource into an array. The `usage` counts are included only when all four were loaded with withCount().
     *
     * @return array{id: int, name: string, description: string|null, usage?: array{facts: int, parishes: int, residences: int, gravestones: int, total: int}}
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ($this->hasUsageCounts()) {
            $facts = (int) $this->events_count;
            $parishes = (int) $this->parishes_count;
            $residences = (int) $this->residences_count;
            $gravestones = (int) $this->cemetery_sources_count;

            $data['usage'] = [
                'facts' => $facts,
                'parishes' => $parishes,
                'residences' => $residences,
                'gravestones' => $gravestones,
                'total' => $facts + $parishes + $residences + $gravestones,
            ];
        }

        return $data;
    }

    /**
     * Whether every usage count was loaded on the model.
     */
    private function hasUsageCounts(): bool
    {
        $attributes = $this->resource->getAttributes();

        return array_key_exists('events_count', $attributes)
            && array_key_exists('parishes_count', $attributes)
            && array_key_exists('residences_count', $attributes)
            && array_key_exists('cemetery_sources_count', $attributes);
    }
}
