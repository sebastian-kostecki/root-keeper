<?php

namespace App\Actions\Sources;

use App\Models\Source;
use Illuminate\Support\Facades\Storage;

class DeleteSource
{
    /**
     * Delete a source permanently: its row (whose event_source rows cascade,
     * leaving any event that had only this source unconfirmed) and then its
     * original and thumbnail files. Events and persons are never touched.
     */
    public function handle(Source $source): void
    {
        $path = $source->path;
        $thumbnailPath = $source->thumbnail_path;

        $source->delete();

        Storage::disk('local')->delete(array_filter([$path, $thumbnailPath]));
    }
}
