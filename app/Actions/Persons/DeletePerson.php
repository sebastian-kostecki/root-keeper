<?php

namespace App\Actions\Persons;

use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeletePerson
{
    /**
     * Delete a person, removing every marriage they were part of and every
     * other event whose only participant is this person, before deleting
     * the person itself. Events shared with other participants keep their
     * remaining participant rows. Sources are never touched; only the
     * event_source rows of removed events cascade away. Parent-child
     * relations where this person is a parent or a child are removed
     * automatically by the foreign key cascade on people.
     *
     * The photo and thumbnail files are removed after the outermost transaction commits, so a caller that
     * rolls back keeps them; a file that cannot be deleted is reported, not thrown, because the rows are
     * already gone.
     */
    public function handle(Person $person): void
    {
        $photoFiles = array_values(array_filter([$person->photo_path, $person->photo_thumbnail_path]));

        DB::transaction(function () use ($person, $photoFiles): void {
            $person->marriages()
                ->get()
                ->each(fn ($event) => $event->delete());

            $person->events()
                ->has('participants', '=', 1)
                ->get()
                ->each(fn ($event) => $event->delete());

            $person->delete();

            if ($photoFiles !== []) {
                DB::afterCommit(function () use ($photoFiles): void {
                    if (! Storage::disk('local')->delete($photoFiles)) {
                        report(new RuntimeException('Unable to delete person photo file(s) ['.implode(', ', $photoFiles).'].'));
                    }
                });
            }
        });
    }
}
