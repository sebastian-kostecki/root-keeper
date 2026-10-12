<?php

namespace App\Actions\Sources;

use App\Models\Source;

class ClearLegacyEntryNumber
{
    /**
     * Clear the old per-source entry number. This is the only way the value is removed.
     */
    public function handle(Source $source): Source
    {
        $source->update(['entry_number' => null]);

        return $source;
    }
}
