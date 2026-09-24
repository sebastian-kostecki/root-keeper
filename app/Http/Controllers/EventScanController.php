<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventScanStoreRequest;
use App\Models\Event;
use App\Models\Scan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventScanController extends Controller
{
    /**
     * Link a scan of the user's own tree to the given event.
     */
    public function store(EventScanStoreRequest $request, Event $event): RedirectResponse
    {
        try {
            $event->scans()->syncWithoutDetaching([$request->validated('scan_id')]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request already created the link; linking is idempotent.
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Powiązano skan')]);

        return back();
    }

    /**
     * Detach a scan from the given event.
     */
    public function destroy(Event $event, Scan $scan): RedirectResponse
    {
        Gate::authorize('update', $event);
        Gate::authorize('view', $scan);

        $detached = $event->scans()->detach($scan);

        if ($detached === 0) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Odpięto skan')]);

        return back();
    }
}
