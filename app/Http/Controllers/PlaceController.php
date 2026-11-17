<?php

namespace App\Http\Controllers;

use App\Enums\CemeteryManager;
use App\Enums\LocalityKind;
use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Http\Resources\CemeteryResource;
use App\Http\Resources\LocalityResource;
use App\Http\Resources\ParishResource;
use App\Models\Locality;
use App\Models\Parish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    /**
     * Display the tree's places: the gazetteer, the parish register and the cemeteries on one page, with the side panel named by `?locality=`, `?parish=` or `?cemetery=` resolved lazily.
     */
    public function index(Request $request): Response
    {
        $tree = $request->user()->tree;

        return Inertia::render('places/index', [
            'localities' => fn (): array => LocalityResource::collection(
                $tree->localities()->with('aliases')->withCount(['events', 'parishes', 'residences', 'cemeteries'])->orderBy('name')->get()
            )->resolve(),
            'parishes' => fn (): array => ParishResource::collection(
                $tree->parishes()->with('locality')->withCount('sources')->get()
                    ->sortBy(fn (Parish $parish): string => mb_strtolower($parish->label()))
                    ->values()
            )->resolve(),
            'cemeteries' => fn (): array => CemeteryResource::collection(
                $tree->cemeteries()->with('locality')->withCount(['sources', 'burials'])->get()
            )->resolve(),
            'religions' => Religion::options(),
            'parishKinds' => ParishKind::options(),
            'cemeteryManagers' => CemeteryManager::options(),
            'localityKinds' => LocalityKind::options(),
            'filters' => [
                'q' => $this->normalizeSearchQuery($request->query('q')),
                'kind' => $this->normalizeKind($request->query('kind')),
            ],
            'localityPanel' => function () use ($request): ?array {
                $key = $request->query('locality');

                if ($key === 'new') {
                    return ['locality' => null];
                }

                if (! is_string($key) || ! ctype_digit($key)) {
                    return null;
                }

                $locality = $request->user()->tree->localities()
                    ->with('aliases')
                    ->withCount(['events', 'parishes', 'residences', 'cemeteries'])
                    ->findOrFail((int) $key);

                Gate::authorize('view', $locality);

                return ['locality' => LocalityResource::make($locality)->resolve()];
            },
            'parishPanel' => function () use ($request): ?array {
                $key = $request->query('parish');

                if ($key === 'new') {
                    return ['parish' => null];
                }

                if (! is_string($key) || ! ctype_digit($key)) {
                    return null;
                }

                $parish = $request->user()->tree->parishes()->with('locality')->withCount('sources')->findOrFail((int) $key);

                Gate::authorize('view', $parish);

                return ['parish' => ParishResource::make($parish)->resolve()];
            },
            'cemeteryPanel' => function () use ($request): ?array {
                $key = $request->query('cemetery');

                if ($key === 'new') {
                    return ['cemetery' => null];
                }

                if (! is_string($key) || ! ctype_digit($key)) {
                    return null;
                }

                $cemetery = $request->user()->tree->cemeteries()->with('locality')->withCount(['sources', 'burials'])->findOrFail((int) $key);

                Gate::authorize('view', $cemetery);

                return ['cemetery' => CemeteryResource::make($cemetery)->resolve()];
            },
        ]);
    }

    /**
     * Normalize the raw `q` query parameter into a trimmed, length-capped search string.
     */
    private function normalizeSearchQuery(mixed $q): string
    {
        if (! is_string($q)) {
            return '';
        }

        return rtrim(mb_substr(trim($q), 0, 100));
    }

    /**
     * Normalize the raw `kind` query parameter: `parafie` for the parish register, `cmentarze` for the cemeteries, otherwise the localities.
     */
    private function normalizeKind(mixed $kind): string
    {
        return in_array($kind, ['parafie', 'cmentarze'], true) ? $kind : 'miejscowosci';
    }
}
