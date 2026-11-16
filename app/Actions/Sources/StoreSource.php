<?php

namespace App\Actions\Sources;

use App\Enums\SourceKind;
use App\Models\Source;
use App\Models\Tree;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreSource
{
    public function __construct(
        private StoreSourceFile $storeSourceFile,
        private FillBurialPlacesFromGravestone $fillBurialPlacesFromGravestone,
    ) {}

    /**
     * Store the scan (an upload, or a file from disk that stays in place) and, when
     * possible, a thumbnail on the private disk and create the source row. Leaves no
     * orphan files if anything fails once the original is written. A gravestone's
     * buried people are linked and their empty burial places filled in the same
     * transaction as the row.
     *
     * @param  array{name?: string|null, record_type?: string|null, parish_id?: int|null, cemetery_id?: int|null, buried_person_ids?: list<int>|null, year?: int|null, archive?: string|null, signature?: string|null, url?: string|null, note?: string|null, repo_id?: string|null}  $description
     */
    public function handle(Tree $tree, UploadedFile|File $file, SourceKind $kind, array $description = []): Source
    {
        $stored = $this->storeSourceFile->handle($tree, $file);

        try {
            $buriedPersonIds = $description['buried_person_ids'] ?? [];
            unset($description['buried_person_ids']);

            return DB::transaction(function () use ($tree, $description, $kind, $stored, $buriedPersonIds): Source {
                $source = $tree->sources()->create([
                    ...$description,
                    ...$stored,
                    'kind' => $kind,
                ]);

                if ($kind === SourceKind::Gravestone) {
                    $source->buriedPeople()->sync($buriedPersonIds);
                }

                $this->fillBurialPlacesFromGravestone->handle($source);

                return $source;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_filter([$stored['path'], $stored['thumbnail_path']]));

            throw $e;
        }
    }
}
