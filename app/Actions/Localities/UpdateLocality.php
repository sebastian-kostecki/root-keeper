<?php

namespace App\Actions\Localities;

use App\Models\Locality;

class UpdateLocality
{
    /**
     * Update the name and description of a locality; a missing description is cleared.
     */
    public function handle(Locality $locality, string $name, ?string $description): Locality
    {
        $locality->update([
            'name' => $name,
            'description' => $description,
        ]);

        return $locality;
    }
}
