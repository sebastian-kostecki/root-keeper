<?php

namespace App\Http\Controllers;

use App\Actions\Persons\CreatePerson;
use App\Actions\Persons\DeletePerson;
use App\Actions\Persons\UpdatePerson;
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
        $people = $request->user()->tree->people()
            ->with('events')
            ->orderByRaw('last_name is null')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        return Inertia::render('persons/index', [
            'persons' => PersonResource::collection($people)->resolve(),
        ]);
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
    public function show(Request $request, Person $person): Response
    {
        Gate::authorize('view', $person);

        $person->load('events.scans');

        return Inertia::render('persons/show', [
            'person' => PersonResource::make($person)->resolve(),
            'availableScans' => Inertia::optional(fn (): array => ScanResource::collection(
                $request->user()->tree->scans()->latest()->orderByDesc('id')->get()
            )->resolve()),
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
