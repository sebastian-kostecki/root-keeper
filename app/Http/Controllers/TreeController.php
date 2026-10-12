<?php

namespace App\Http\Controllers;

use App\Actions\Trees\BuildFamilySheet;
use App\Actions\Trees\ListPersonCandidates;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TreeController extends Controller
{
    /**
     * Display the family sheet of the tree's proband.
     */
    public function index(Request $request, BuildFamilySheet $buildFamilySheet, ListPersonCandidates $listPersonCandidates): Response
    {
        $tree = $request->user()->tree;
        $proband = $tree->rootPerson;

        return Inertia::render('tree/show', [
            'sheet' => $proband === null ? null : $buildFamilySheet->handle($proband, $this->spouseParameter($request)),
            'hasPeople' => $tree->people()->exists(),
            'rootPersonId' => $tree->root_person_id,
            'personCandidates' => Inertia::optional(fn (): array => $listPersonCandidates->handle($tree)),
            'genders' => Gender::options(),
            'religions' => Religion::options(),
        ]);
    }

    /**
     * Display the family sheet centred on the given person.
     */
    public function show(Request $request, Person $person, BuildFamilySheet $buildFamilySheet, ListPersonCandidates $listPersonCandidates): Response
    {
        Gate::authorize('view', $person);

        $tree = $request->user()->tree;

        return Inertia::render('tree/show', [
            'sheet' => $buildFamilySheet->handle($person, $this->spouseParameter($request)),
            'rootPersonId' => $tree->root_person_id,
            'personCandidates' => Inertia::optional(fn (): array => $listPersonCandidates->handle($tree)),
            'genders' => Gender::options(),
            'religions' => Religion::options(),
        ]);
    }

    private function spouseParameter(Request $request): ?string
    {
        $spouse = $request->query('spouse');

        return is_string($spouse) ? $spouse : null;
    }
}
