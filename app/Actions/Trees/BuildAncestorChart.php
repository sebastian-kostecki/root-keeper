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
 * is already on the path from the focus (a cycle in the stored relations) is shown once more without parents.
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

    private const MIN_GENERATIONS = 2;

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
     * @param  ?int  $generations  the requested depth (at least 2); null means the default. It is capped at the available depth.
     * @return array{focus: SheetPerson, generations: int, available_generations: int, root: array<string, mixed>}
     */
    public function handle(Person $person, ?int $generations): array
    {
        $this->graph = ParentChildGraph::forTree($person->tree);
        $this->occurrences = [];

        $available = max(self::MIN_GENERATIONS, $this->graph->ancestorGenerations($person->id, self::MAX_GENERATIONS));
        $this->generations = min($generations ?? self::DEFAULT_GENERATIONS, $available);

        $this->people = $person->tree->people()
            ->with('events')
            ->whereIn('id', [$person->id, ...$this->graph->ancestorIdsWithin($person->id, $this->generations)])
            ->get()
            ->keyBy('id');

        $focusPerson = $this->people->get($person->id) ?? $person;
        $root = $this->markRepeated($this->buildNode($focusPerson, 1, []));

        return [
            'focus' => $this->render($focusPerson),
            'generations' => $this->generations,
            'available_generations' => $available,
            'root' => $root,
        ];
    }

    /**
     * A known person; the top generation and a person already on the path (a cycle) have no parents,
     * every other one gets two parent nodes.
     *
     * @param  array<int, true>  $path  the people from the focus down to this person's child
     * @return array<string, mixed>
     */
    private function buildNode(Person $person, int $generation, array $path): array
    {
        $this->occurrences[$person->id] = ($this->occurrences[$person->id] ?? 0) + 1;

        $withParents = $generation < $this->generations && ! isset($path[$person->id]);

        return [
            'kind' => 'person',
            'person' => $this->render($person),
            'repeated' => false,
            'parents' => $withParents ? $this->parentNodes($person, $generation, $path + [$person->id => true]) : [],
        ];
    }

    /**
     * Two parent nodes in the family sheet's order: known parents first (at most two), then unknown placeholders.
     *
     * @param  array<int, true>  $path
     * @return list<array<string, mixed>>
     */
    private function parentNodes(Person $person, int $generation, array $path): array
    {
        $known = PersonOrdering::parents(
            (new Collection($this->graph->parentsOf($person->id)))
                ->map(fn (int $id): ?Person => $this->people->get($id))
                ->filter()
        );

        if ($known->isEmpty()) {
            return [$this->unknownNode('Nieznany ojciec'), $this->unknownNode('Nieznana matka')];
        }

        if ($known->count() >= 2) {
            return [
                $this->buildNode($known[0], $generation + 1, $path),
                $this->buildNode($known[1], $generation + 1, $path),
            ];
        }

        $only = $known[0];

        return match ($only->gender) {
            Gender::Male => [$this->buildNode($only, $generation + 1, $path), $this->unknownNode('Nieznana matka')],
            Gender::Female => [$this->unknownNode('Nieznany ojciec'), $this->buildNode($only, $generation + 1, $path)],
            null => [$this->buildNode($only, $generation + 1, $path), $this->unknownNode('Nieznany rodzic')],
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
        /** @var SheetPerson $sheetPerson */
        $sheetPerson = SheetPersonResource::make($person)->resolve();

        return $sheetPerson;
    }
}
