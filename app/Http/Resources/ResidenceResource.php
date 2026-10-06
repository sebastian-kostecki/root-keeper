<?php

namespace App\Http\Resources;

use App\Models\Residence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Residence $resource
 *
 * @mixin Residence
 */
class ResidenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, locality: array{id: int, name: string}, year_from: int|null, year_to: int|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'locality' => ['id' => $this->locality->id, 'name' => $this->locality->name],
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
        ];
    }
}
