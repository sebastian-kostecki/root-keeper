<?php

namespace App\Http\Controllers;

use App\Actions\Persons\DeletePersonPhoto;
use App\Actions\Persons\StorePersonPhoto;
use App\Http\Requests\PersonPhotoStoreRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonPhotoController extends Controller
{
    /**
     * Replace the photo of the given person.
     */
    public function store(PersonPhotoStoreRequest $request, Person $person, StorePersonPhoto $storePersonPhoto): RedirectResponse
    {
        $storePersonPhoto->handle($person, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano zdjęcie')]);

        return back();
    }

    /**
     * Remove the photo of the given person.
     */
    public function destroy(Person $person, DeletePersonPhoto $deletePersonPhoto): RedirectResponse
    {
        Gate::authorize('update', $person);

        $deletePersonPhoto->handle($person);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto zdjęcie')]);

        return back();
    }

    /**
     * Serve the thumbnail of the given person's photo.
     */
    public function show(Person $person): StreamedResponse
    {
        Gate::authorize('view', $person);

        if ($person->photo_thumbnail_path === null || ! Storage::disk('local')->exists($person->photo_thumbnail_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($person->photo_thumbnail_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
