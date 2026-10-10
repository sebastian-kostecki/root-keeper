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
    private const MIN_GENERATIONS = 2;

    private const DEFAULT_GENERATIONS = 4;

    private const MAX_GENERATIONS = 6;

    /**
     * Display the given person and their ancestors, up to the requested number of generations.
     */
    public function __invoke(Request $request, Person $person, BuildAncestorChart $buildAncestorChart): Response
    {
        Gate::authorize('view', $person);

        return Inertia::render('tree/ancestors', [
            'chart' => $buildAncestorChart->handle($person, $this->generationsParameter($request)),
        ]);
    }

    /**
     * Anything outside the supported range falls back to the default.
     */
    private function generationsParameter(Request $request): int
    {
        $generations = $request->integer('generations', self::DEFAULT_GENERATIONS);

        return $generations >= self::MIN_GENERATIONS && $generations <= self::MAX_GENERATIONS
            ? $generations
            : self::DEFAULT_GENERATIONS;
    }
}
