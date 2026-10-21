<?php

namespace App\Http\Controllers;

use App\Actions\Persons\CreatePerson;
use App\Actions\Persons\DeletePerson;
use App\Actions\Persons\UpdatePerson;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Http\Requests\PersonStoreRequest;
use App\Http\Requests\PersonUpdateRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Support\PersonPanelRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PersonController extends Controller
{
    /**
     * Display a listing of the tree's people.
     */
    public function index(Request $request): Response
    {
        $query = $this->normalizeSearchQuery($request->query('q'));
        $view = $this->normalizeView($request->query('view'));
        $sort = $view === 'lista' ? 'nazwisko' : $this->normalizeSort($request->query('sort'));
        $tree = $request->user()->tree;

        return Inertia::render('persons/index', [
            'persons' => fn (): array => PersonResource::collection(
                $tree->people()
                    ->with(['events' => fn ($events) => $events
                        ->whereIn('type', [EventType::Birth, EventType::Death, EventType::Burial])
                        ->withExists('sources')
                        ->with(['locality', 'cemetery.locality'])])
                    ->when($query !== '', function ($builder) use ($query): void {
                        $nameWithoutMaidenMarker = preg_replace('/(?<!\S)z\s+d\.?(?!\S)/iu', ' ', $query) ?? $query;
                        $words = preg_split('/\s+/u', $nameWithoutMaidenMarker, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                        foreach ($words as $word) {
                            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';

                            $builder->where(fn ($builder) => $builder
                                ->whereLike('first_name', $pattern)
                                ->orWhereLike('last_name', $pattern)
                                ->orWhereLike('maiden_name', $pattern));
                        }
                    })
                    ->orderByRaw('last_name is null')
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->orderBy('id')
                    ->get()
            )->resolve(),
            'filters' => ['q' => $query, 'view' => $view, 'sort' => $sort],
            'rootPersonId' => $tree->root_person_id,
            'genders' => Gender::options(),
            'religions' => Religion::options(),
        ]);
    }

    /**
     * Normalize the raw `view` query parameter: `tabela` or the default `lista`.
     */
    private function normalizeView(mixed $view): string
    {
        return $view === 'tabela' ? 'tabela' : 'lista';
    }

    /**
     * Normalize the raw `sort` query parameter: one of the six sort keys or the default `nazwisko`.
     */
    private function normalizeSort(mixed $sort): string
    {
        $allowed = ['nazwisko', '-nazwisko', 'urodzenie', '-urodzenie', 'zgon', '-zgon'];

        return is_string($sort) && in_array($sort, $allowed, true) ? $sort : 'nazwisko';
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
     * Store a newly created person in the tree.
     *
     * Returns to the referring page (the persons list with its filters) with the new person's preview open;
     * without a usable referrer it goes to the persons list.
     */
    public function store(PersonStoreRequest $request, CreatePerson $createPerson): RedirectResponse
    {
        $person = $createPerson->handle($request->user()->tree, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano osobę')]);

        return PersonPanelRedirect::to($request, $person, route('persons.index'));
    }

    /**
     * Update the given person.
     */
    public function update(PersonUpdateRequest $request, Person $person, UpdatePerson $updatePerson): RedirectResponse
    {
        $updatePerson->handle($person, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano zmiany')]);

        return redirect()->back(fallback: route('persons.index'));
    }

    /**
     * Remove the given person.
     */
    public function destroy(Request $request, Person $person, DeletePerson $deletePerson): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $deletePerson->handle($person);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto osobę')]);

        return redirect($this->urlAfterDelete($request, $person));
    }

    /**
     * Resolve where to go after deleting the person: the referring page without its `person` preview key.
     *
     * Falls back to the persons list when the referrer is missing, external or unmatchable; a tree or ancestors view centred on the deleted person returns to the proband's tree.
     */
    private function urlAfterDelete(Request $request, Person $person): string
    {
        $fallback = route('persons.index');
        $parts = parse_url(url()->previous($fallback));

        if ($parts === false || ($parts['host'] ?? null) !== $request->getHost() || ! isset($parts['path']) || ! preg_match('#^/(?!/)#', $parts['path'])) {
            return $fallback;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($parts['path']));
        } catch (HttpException) {
            return $fallback;
        }

        $name = $route->getName();

        if (in_array($name, ['tree.show', 'tree.ancestors'], true) && $route->originalParameter('person') === (string) $person->id) {
            return route('tree.index');
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['person']);

        return $request->getSchemeAndHttpHost().$parts['path'].($query === [] ? '' : '?'.http_build_query($query));
    }
}
