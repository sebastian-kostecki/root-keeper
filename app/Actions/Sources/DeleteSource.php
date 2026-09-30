<?php

namespace App\Actions\Sources;

use App\Models\Source;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteSource
{
    /**
     * Delete a source permanently: its row (whose event_source rows cascade,
     * leaving any event that had only this source unconfirmed) and then its
     * original and thumbnail files. Events and persons are never touched. A file
     * that cannot be deleted is reported, not thrown: the row is already gone.
     */
    public function handle(Source $source): void
    {
        $files = array_filter([$source->path, $source->thumbnail_path]);

        $source->delete();

        if (! Storage::disk('local')->delete($files)) {
            report(new RuntimeException('Unable to delete source file(s) ['.implode(', ', $files).'].'));
        }
    }
}
