<?php

namespace App\Http\Controllers;

use App\Actions\Trees\BuildFamilySheet;
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
    public function index(Request $request, BuildFamilySheet $buildFamilySheet): Response
    {
        $tree = $request->user()->tree;
        $proband = $tree->rootPerson;

        return Inertia::render('tree/show', [
            'sheet' => $proband === null ? null : $buildFamilySheet->handle($proband, $this->spouseParameter($request)),
            'rootPersonId' => $tree->root_person_id,
        ]);
    }

    /**
     * Display the family sheet centred on the given person.
     */
    public function show(Request $request, Person $person, BuildFamilySheet $buildFamilySheet): Response
    {
        Gate::authorize('view', $person);

        return Inertia::render('tree/show', [
            'sheet' => $buildFamilySheet->handle($person, $this->spouseParameter($request)),
            'rootPersonId' => $request->user()->tree->root_person_id,
        ]);
    }

    private function spouseParameter(Request $request): ?string
    {
        $spouse = $request->query('spouse');

        return is_string($spouse) ? $spouse : null;
    }
}
