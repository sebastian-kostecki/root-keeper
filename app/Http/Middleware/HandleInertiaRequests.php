<?php

namespace App\Http\Middleware;

use App\Actions\Persons\BuildPersonPreview;
use App\Actions\Relations\ListRelationCandidates;
use App\Http\Resources\ScanResource;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'personPreview' => function () use ($request): ?array {
                $person = $this->previewPerson($request);

                return $person === null ? null : app(BuildPersonPreview::class)->handle($person, $request->user());
            },
            'availableScans' => Inertia::optional(fn (): array => $request->user() === null ? [] : ScanResource::collection(
                $request->user()->tree->scans()->latest()->orderByDesc('id')->get()
            )->resolve()),
            'relationCandidates' => Inertia::optional(function () use ($request): array {
                $person = $this->previewPerson($request) ?? $this->routePerson($request);

                return $person === null ? [] : app(ListRelationCandidates::class)->handle($person);
            }),
        ];
    }

    /**
     * Resolve the person named by the `person` query parameter from the user's own tree.
     *
     * Returns null without an authenticated user or a numeric parameter; a missing or foreign id is a 404.
     */
    private function previewPerson(Request $request): ?Person
    {
        $user = $request->user();
        $id = $request->query('person');

        if ($user === null || ! is_string($id) || ! ctype_digit($id)) {
            return null;
        }

        $person = $user->tree->people()->findOrFail((int) $id);

        Gate::authorize('view', $person);

        return $person;
    }

    /**
     * The person bound to the legacy `persons.show` page, until that page is removed.
     */
    private function routePerson(Request $request): ?Person
    {
        $person = $request->route('person');

        return $request->route()?->getName() === 'persons.show' && $person instanceof Person ? $person : null;
    }
}
