<?php

namespace App\Http\Middleware;

use App\Actions\Persons\BuildPersonPreview;
use App\Actions\Relations\BuildMarriagePanel;
use App\Enums\CemeteryManager;
use App\Enums\EventType;
use App\Enums\MarriageForm;
use App\Http\Resources\SourceResource;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
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
            'navCounts' => fn (): ?array => $request->user() === null ? null : [
                'people' => $request->user()->tree->people()->count(),
                'sources' => $request->user()->tree->sources()->count(),
                'localities' => $request->user()->tree->localities()->count(),
                'parishes' => $request->user()->tree->parishes()->count(),
                'cemeteries' => $request->user()->tree->cemeteries()->count(),
            ],
            'personPreview' => function () use ($request): ?array {
                $person = $this->previewPerson($request);

                return $person === null ? null : app(BuildPersonPreview::class)->handle($person, $request->user());
            },
            'marriagePanel' => function () use ($request): ?array {
                $marriage = $this->marriagePanelEvent($request);

                return $marriage === null ? null : app(BuildMarriagePanel::class)->handle($marriage);
            },
            'localityOptions' => Inertia::optional(fn (): array => $request->user() === null ? [] : $request->user()->tree->localities()
                ->orderBy('name')
                ->get(['id', 'name', 'is_defunct'])
                ->map(fn (Locality $locality): array => ['id' => $locality->id, 'name' => $locality->name, 'is_defunct' => $locality->is_defunct])
                ->all()),
            'cemeteryManagers' => fn (): array => CemeteryManager::options(),
            'marriageForms' => fn (): array => MarriageForm::options(),
            'cemeteryOptions' => Inertia::optional(fn (): array => $request->user() === null ? [] : $request->user()->tree->cemeteries()
                ->with('locality')
                ->get()
                ->sortBy(fn (Cemetery $cemetery): string => mb_strtolower($cemetery->label()))
                ->map(fn (Cemetery $cemetery): array => $cemetery->toOption())
                ->values()
                ->all()),
            'availableSources' => Inertia::optional(fn (): array => $request->user() === null ? [] : SourceResource::collection(
                $request->user()->tree->sources()->with(['parish.locality', 'cemetery.locality'])->latest()->orderByDesc('id')->get()
            )->resolve()),
        ];
    }

    /**
     * Resolve the person named by the `person` query parameter from the user's own tree.
     *
     * Returns null without an authenticated user or a numeric parameter; a missing or foreign id is a 404.
     * The resolved person is memoized on the request.
     */
    private function previewPerson(Request $request): ?Person
    {
        $user = $request->user();
        $id = $request->query('person');

        if ($user === null || ! is_string($id) || ! ctype_digit($id)) {
            return null;
        }

        if ($request->attributes->has('previewPerson')) {
            return $request->attributes->get('previewPerson');
        }

        $person = $user->tree->people()->findOrFail((int) $id);

        Gate::authorize('view', $person);

        $request->attributes->set('previewPerson', $person);

        return $person;
    }

    /**
     * Resolve the marriage named by the `marriage` query parameter from the user's own tree.
     *
     * Returns null without an authenticated user or a numeric parameter; a missing, foreign, non-marriage
     * or not-two-spouse id is a 404. The resolved marriage is memoized on the request.
     */
    private function marriagePanelEvent(Request $request): ?Event
    {
        $user = $request->user();
        $id = $request->query('marriage');

        if ($user === null || ! is_string($id) || ! ctype_digit($id)) {
            return null;
        }

        if ($request->attributes->has('marriagePanelEvent')) {
            return $request->attributes->get('marriagePanelEvent');
        }

        $marriage = $user->tree->events()->where('type', EventType::Marriage)->findOrFail((int) $id);

        Gate::authorize('view', $marriage);

        $marriage->loadMissing('participants');
        abort_unless($marriage->participants->count() === 2, 404);

        $request->attributes->set('marriagePanelEvent', $marriage);

        return $marriage;
    }
}
