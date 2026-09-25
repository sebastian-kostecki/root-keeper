<?php

namespace App\Http\Controllers;

use App\Actions\Persons\CreatePerson;
use App\Actions\Persons\DeletePerson;
use App\Actions\Persons\UpdatePerson;
use App\Actions\Relations\ListRelationCandidates;
use App\Enums\Religion;
use App\Http\Requests\PersonStoreRequest;
use App\Http\Requests\PersonUpdateRequest;
use App\Http\Resources\PersonFormResource;
use App\Http\Resources\PersonResource;
use App\Http\Resources\ScanResource;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    /**
     * Display a listing of the tree's people.
     */
    public function index(Request $request): Response
    {
        $query = $this->normalizeSearchQuery($request->query('q'));

        $people = $request->user()->tree->people()
            ->with('events')
            ->when($query !== '', function ($builder) use ($query): void {
                $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                foreach ($words as $word) {
                    $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';

                    $builder->where(fn ($builder) => $builder
                        ->whereLike('first_name', $pattern)
                        ->orWhereLike('last_name', $pattern));
                }
            })
            ->orderByRaw('last_name is null')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        return Inertia::render('persons/index', [
            'persons' => PersonResource::collection($people)->resolve(),
            'filters' => ['q' => $query],
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
     * Show the form for creating a new person.
     */
    public function create(): Response
    {
        return Inertia::render('persons/create', [
            'religions' => Religion::options(),
        ]);
    }

    /**
     * Store a newly created person in the tree.
     */
    public function store(PersonStoreRequest $request, CreatePerson $createPerson): RedirectResponse
    {
        $person = $createPerson->handle($request->user()->tree, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano osobę')]);

        return to_route('persons.show', $person);
    }

    /**
     * Display the given person.
     */
    public function show(Request $request, Person $person, ListRelationCandidates $listRelationCandidates): Response
    {
        Gate::authorize('view', $person);

        $person->load(['events.scans', 'parents.events', 'children.events', 'marriages.scans', 'marriages.participants.events']);

        return Inertia::render('persons/show', [
            'person' => PersonResource::make($person)->resolve(),
            'availableScans' => Inertia::optional(fn (): array => ScanResource::collection(
                $request->user()->tree->scans()->latest()->orderByDesc('id')->get()
            )->resolve()),
            'relationCandidates' => Inertia::optional(fn (): array => $listRelationCandidates->handle($person)),
        ]);
    }

    /**
     * Show the form for editing the given person.
     */
    public function edit(Person $person): Response
    {
        Gate::authorize('update', $person);

        $person->load('events.scans');

        return Inertia::render('persons/edit', [
            'person' => PersonFormResource::make($person)->resolve(),
            'religions' => Religion::options(),
        ]);
    }

    /**
     * Update the given person.
     */
    public function update(PersonUpdateRequest $request, Person $person, UpdatePerson $updatePerson): RedirectResponse
    {
        $updatePerson->handle($person, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano zmiany')]);

        return to_route('persons.show', $person);
    }

    /**
     * Remove the given person.
     */
    public function destroy(Person $person, DeletePerson $deletePerson): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $deletePerson->handle($person);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto osobę')]);

        return to_route('persons.index');
    }
}
