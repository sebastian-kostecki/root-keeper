<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\UpdateMarriage;
use App\Enums\EventType;
use App\Http\Requests\MarriageStoreRequest;
use App\Http\Requests\MarriageUpdateRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MarriageController extends Controller
{
    /**
     * Create a marriage between the two given people in the user's own tree.
     */
    public function store(MarriageStoreRequest $request, CreateMarriage $createMarriage): RedirectResponse
    {
        $tree = $request->user()->tree;

        $person = $tree->people()->findOrFail($request->safe()->integer('person_id'));
        $spouse = $tree->people()->findOrFail($request->safe()->integer('spouse_id'));

        $createMarriage->handle($tree, $person, $spouse, $request->validated('marriage', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano małżeństwo')]);

        return back();
    }

    /**
     * Update the given marriage's date and place.
     */
    public function update(MarriageUpdateRequest $request, Event $marriage, UpdateMarriage $updateMarriage): RedirectResponse
    {
        $updateMarriage->handle($marriage, $request->validated('marriage', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano zmiany')]);

        return back();
    }

    /**
     * Remove the given marriage and return to the referring page without its `marriage` panel key.
     */
    public function destroy(Request $request, Event $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);
        abort_unless($marriage->type === EventType::Marriage, 404);

        $marriage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto małżeństwo')]);

        return redirect($this->urlAfterDelete($request));
    }

    /**
     * The referring page without its `marriage` key; the tree index when the Referer is missing or external.
     *
     * Reads the Referer only: url()->previous() falls back to an address stored in the session.
     */
    private function urlAfterDelete(Request $request): string
    {
        $fallback = route('tree.index');
        $parts = parse_url((string) $request->headers->get('referer'));

        if (
            ! is_array($parts)
            || ! isset($parts['path'])
            || ! preg_match('#^/(?!/)#', $parts['path'])
            || (isset($parts['host']) && $parts['host'] !== $request->getHost())
        ) {
            return $fallback;
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['marriage']);

        return $request->getSchemeAndHttpHost().$parts['path'].($query === [] ? '' : '?'.http_build_query($query));
    }
}
