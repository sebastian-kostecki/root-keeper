<?php

namespace App\Actions\Trees;

use App\Enums\Gender;
use App\Http\Resources\SheetPersonResource;
use App\Models\Person;
use App\Support\ParentChildGraph;
use App\Support\PersonOrdering;
use Illuminate\Support\Collection;

/**
 * Assembles the ancestor chart of a focus person: a tree of person and unknown nodes going up from the
 * focus, using a constant number of queries (one for the graph, one for the people).
 *
 * The chart goes as deep as the data allows (`available_generations`), up to a technical ceiling. A person who
 * appears more than once (a shared ancestor or a cycle in the stored relations) has parents only at the first
 * occurrence in their nearest generation; every other occurrence is shown without parents. The chart therefore
 * grows with the number of people, not with the number of lines leading to them.
 *
 * A node is either a person (`kind` 'person', with `person`, `repeated` and `parents`, at most two) or an
 * unknown placeholder (`kind` 'unknown', with `label`). PHPStan cannot express the recursive node shape,
 * so nodes are typed as arrays here and the client type `AncestorNode` (resources/js/types/tree.ts) is the contract.
 *
 * @phpstan-import-type SheetPerson from BuildFamilySheet
 */
class BuildAncestorChart
{
    /**
     * Safeguard against runaway data, not a product limit.
     */
    private const MAX_GENERATIONS = 20;

    private const DEFAULT_GENERATIONS = 4;

    /**
     * The smallest chart: the person and a row of parents or placeholders.
     */
    public const MIN_GENERATIONS = 2;

    /**
     * The chart draws two parents; with more stored (not possible through the app), the lowest ids win.
     */
    private const PARENTS_PER_PERSON = 2;

    /**
     * @var Collection<int, Person>
     */
    private Collection $people;

    private ParentChildGraph $graph;

    private int $generations;

    /**
     * @var array<int, int>
     */
    private array $occurrences = [];

    /**
     * The shortest number of generations from the focus to each ancestor (the focus is 0).
     *
     * @var array<int, int>
     */
    private array $levels = [];

    /**
     * @var array<int, true>
     */
    private array $expanded = [];

    /**
     * @var array<int, SheetPerson>
     */
    private array $rendered = [];

    /**
     * @param  ?int  $generations  the requested depth (at least 2); null means the default. It is capped at the available depth.
     * @return array{focus: SheetPerson, generations: int, min_generations: int, available_generations: int, root: array<string, mixed>}
     */
    public function handle(Person $person, ?int $generations): array
    {
        $this->graph = ParentChildGraph::forTree($person->tree);
        $this->occurrences = [];
        $this->expanded = [];
        $this->rendered = [];
        $this->levels = $this->graph->ancestorLevels($person->id, self::MAX_GENERATIONS - 1, self::PARENTS_PER_PERSON);

        $available = $this->availableGenerations();
        $this->generations = min($generations ?? self::DEFAULT_GENERATIONS, $available);

        $this->people = $person->tree->people()
            ->with('events')
            ->whereIn('id', [$person->id, ...$this->graph->ancestorIdsWithin($person->id, $this->generations)])
            ->get()
            ->keyBy('id');

        $focusPerson = $this->people->get($person->id) ?? $person;
        $root = $this->markRepeated($this->buildNode($focusPerson, 1));

        return [
            'focus' => $this->render($focusPerson),
            'generations' => $this->generations,
            'min_generations' => self::MIN_GENERATIONS,
            'available_generations' => $available,
            'root' => $root,
        ];
    }

    /**
     * The number of rows of the full chart: the deepest generation holding a known person, at least two
     * (a person without parents gets a row of placeholders) and at most the ceiling. A person is given
     * parents in the generation after their nearest one, so their parents' row is their level plus two.
     */
    private function availableGenerations(): int
    {
        $available = self::MIN_GENERATIONS;

        foreach ($this->levels as $personId => $level) {
            if ($this->graph->parentsOf($personId) !== []) {
                $available = max($available, $level + 2);
            }
        }

        return min($available, self::MAX_GENERATIONS);
    }

    /**
     * A known person; parents are given only below the top generation and only at the person's first
     * occurrence in their nearest generation, every other occurrence (a shared ancestor seen again, or a
     * cycle) has none.
     *
     * @return array<string, mixed>
     */
    private function buildNode(Person $person, int $generation): array
    {
        $this->occurrences[$person->id] = ($this->occurrences[$person->id] ?? 0) + 1;

        $withParents = $generation < $this->generations
            && $generation === ($this->levels[$person->id] ?? -1) + 1
            && ! isset($this->expanded[$person->id]);

        if ($withParents) {
            $this->expanded[$person->id] = true;
        }

        return [
            'kind' => 'person',
            'person' => $this->render($person),
            'repeated' => false,
            'parents' => $withParents ? $this->parentNodes($person, $generation) : [],
        ];
    }

    /**
     * Two parent nodes in the family sheet's order: known parents first (at most two), then unknown placeholders.
     *
     * @return list<array<string, mixed>>
     */
    private function parentNodes(Person $person, int $generation): array
    {
        $known = PersonOrdering::parents(
            (new Collection($this->graph->firstParentsOf($person->id, self::PARENTS_PER_PERSON)))
                ->map(fn (int $id): ?Person => $this->people->get($id))
                ->filter()
        );

        if ($known->isEmpty()) {
            return [$this->unknownNode('Nieznany ojciec'), $this->unknownNode('Nieznana matka')];
        }

        if ($known->count() >= 2) {
            return [
                $this->buildNode($known[0], $generation + 1),
                $this->buildNode($known[1], $generation + 1),
            ];
        }

        $only = $known[0];

        return match ($only->gender) {
            Gender::Male => [$this->buildNode($only, $generation + 1), $this->unknownNode('Nieznana matka')],
            Gender::Female => [$this->unknownNode('Nieznany ojciec'), $this->buildNode($only, $generation + 1)],
            null => [$this->buildNode($only, $generation + 1), $this->unknownNode('Nieznany rodzic')],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function unknownNode(string $label): array
    {
        return ['kind' => 'unknown', 'label' => $label];
    }

    /**
     * Flag every person node whose person appears more than once in the chart.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function markRepeated(array $node): array
    {
        if ($node['kind'] === 'unknown') {
            return $node;
        }

        $personId = $node['person']['id'];
        $node['repeated'] = ($this->occurrences[$personId] ?? 0) > 1;
        $node['parents'] = array_map(fn (array $parent): array => $this->markRepeated($parent), $node['parents']);

        return $node;
    }

    /**
     * @return SheetPerson
     */
    private function render(Person $person): array
    {
        if (! isset($this->rendered[$person->id])) {
            /** @var SheetPerson $sheetPerson */
            $sheetPerson = SheetPersonResource::make($person)->resolve();
            $this->rendered[$person->id] = $sheetPerson;
        }

        return $this->rendered[$person->id];
    }
}
