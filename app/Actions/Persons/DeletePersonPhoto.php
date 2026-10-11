<?php

namespace App\Actions\Persons;

use App\Models\Person;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeletePersonPhoto
{
    /**
     * Clear the photo columns of the person, then delete the photo and its thumbnail from the private disk.
     * A file that cannot be deleted is reported, not thrown: the columns are already cleared.
     */
    public function handle(Person $person): void
    {
        $files = array_values(array_filter([$person->photo_path, $person->photo_thumbnail_path]));

        $person->update([
            'photo_path' => null,
            'photo_thumbnail_path' => null,
        ]);

        if ($files !== [] && ! Storage::disk('local')->delete($files)) {
            report(new RuntimeException('Unable to delete person photo file(s) ['.implode(', ', $files).'].'));
        }
    }
}
