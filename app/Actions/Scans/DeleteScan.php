<?php

namespace App\Actions\Scans;

use App\Models\Scan;
use Illuminate\Support\Facades\Storage;

class DeleteScan
{
    /**
     * Delete a scan permanently: its row (whose event_scan rows cascade,
     * leaving any event that had only this scan unconfirmed) and then its
     * original and thumbnail files. Events and persons are never touched.
     */
    public function handle(Scan $scan): void
    {
        $path = $scan->path;
        $thumbnailPath = $scan->thumbnail_path;

        $scan->delete();

        Storage::disk('local')->delete(array_filter([$path, $thumbnailPath]));
    }
}
