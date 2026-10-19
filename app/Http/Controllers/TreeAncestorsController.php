<?php

namespace App\Http\Controllers;

use App\Actions\Trees\BuildAncestorChart;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TreeAncestorsController extends Controller
{
    /**
     * Display the given person and their ancestors, up to the requested number of generations (default 4, at most the available depth).
     */
    public function __invoke(Request $request, Person $person, BuildAncestorChart $buildAncestorChart): Response
    {
        Gate::authorize('view', $person);

        return Inertia::render('tree/ancestors', [
            'ancestorChart' => $buildAncestorChart->handle($person, $this->generationsParameter($request)),
        ]);
    }

    /**
     * A missing, non-numeric or too small value means "the default depth" (null); the action caps larger
     * values at the depth the person's ancestors actually have.
     */
    private function generationsParameter(Request $request): ?int
    {
        $generations = $request->integer('generations');

        return $generations >= BuildAncestorChart::MIN_GENERATIONS ? $generations : null;
    }
}
