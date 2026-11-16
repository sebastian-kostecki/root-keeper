<?php

namespace App\Actions\Sources;

use App\Models\Source;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReplaceSourceFile
{
    public function __construct(private StoreSourceFile $storeSourceFile) {}

    /**
     * Store a new scan and thumbnail for the source and point its file columns at them.
     * Returns the paths of the OLD file and thumbnail without deleting them: the caller
     * removes them once its transaction has committed. When the update fails, only the
     * files stored here are removed.
     *
     * @return list<string>
     */
    public function handle(Source $source, File $file): array
    {
        $oldPaths = array_values(array_filter([$source->path, $source->thumbnail_path]));

        $stored = $this->storeSourceFile->handle($source->tree, $file);

        try {
            $source->update($stored);
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_filter([$stored['path'], $stored['thumbnail_path']]));

            throw $e;
        }

        return $oldPaths;
    }
}
