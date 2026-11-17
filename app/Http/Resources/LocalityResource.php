<?php

namespace App\Http\Resources;

use App\Models\Locality;
use App\Models\LocalityAlias;
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
     * Transform the resource into an array. The `usage` counts are included only when all four were loaded with withCount(), the `aliases` only when the relation was loaded.
     *
     * @return array{id: int, name: string, kind: string|null, kind_label: string|null, latitude: float|null, longitude: float|null, historical_affiliation: string|null, description: string|null, is_defunct: bool, defunct_note: string|null, aliases?: list<string>, usage?: array{facts: int, parishes: int, residences: int, cemeteries: int, total: int}}
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'historical_affiliation' => $this->historical_affiliation,
            'description' => $this->description,
            'is_defunct' => $this->is_defunct,
            'defunct_note' => $this->defunct_note,
        ];

        if ($this->resource->relationLoaded('aliases')) {
            $aliases = array_values($this->resource->aliases->map(fn (LocalityAlias $alias): string => $alias->name)->all());
            usort($aliases, fn (string $a, string $b): int => mb_strtolower($a) <=> mb_strtolower($b));

            $data['aliases'] = $aliases;
        }

        if ($this->hasUsageCounts()) {
            $facts = (int) $this->events_count;
            $parishes = (int) $this->parishes_count;
            $residences = (int) $this->residences_count;
            $cemeteries = (int) $this->cemeteries_count;

            $data['usage'] = [
                'facts' => $facts,
                'parishes' => $parishes,
                'residences' => $residences,
                'cemeteries' => $cemeteries,
                'total' => $facts + $parishes + $residences + $cemeteries,
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
            && array_key_exists('cemeteries_count', $attributes);
    }
}
