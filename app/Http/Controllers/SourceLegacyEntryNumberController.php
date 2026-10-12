<?php

namespace App\Http\Controllers;

use App\Actions\Sources\ClearLegacyEntryNumber;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SourceLegacyEntryNumberController extends Controller
{
    /**
     * Clear the old entry number stored on the source itself.
     */
    public function __invoke(Source $source, ClearLegacyEntryNumber $clearLegacyEntryNumber): RedirectResponse
    {
        Gate::authorize('update', $source);

        $clearLegacyEntryNumber->handle($source);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto stary numer wpisu')]);

        return back();
    }
}
