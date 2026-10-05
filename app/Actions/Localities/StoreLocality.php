<?php

namespace App\Actions\Localities;

use App\Models\Locality;
use App\Models\Tree;

class StoreLocality
{
    /**
     * Add a locality to the tree's gazetteer.
     */
    public function handle(Tree $tree, string $name, ?string $description): Locality
    {
        return $tree->localities()->create([
            'name' => $name,
            'description' => $description,
        ]);
    }
}
