<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventSourceStoreRequest;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventSourceController extends Controller
{
    /**
     * Link a source of the user's own tree to the given event.
     */
    public function store(EventSourceStoreRequest $request, Event $event): RedirectResponse
    {
        try {
            $event->sources()->syncWithoutDetaching([$request->validated('source_id')]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request already created the link; linking is idempotent.
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Powiązano źródło')]);

        return back();
    }

    /**
     * Detach a source from the given event.
     */
    public function destroy(Event $event, Source $source): RedirectResponse
    {
        Gate::authorize('update', $event);
        Gate::authorize('view', $source);

        $detached = $event->sources()->detach($source);

        if ($detached === 0) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Odpięto źródło')]);

        return back();
    }
}
