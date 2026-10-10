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
 * A node is either a person (`kind` 'person', with `person`, `repeated` and `parents`, at most two) or an
 * unknown placeholder (`kind` 'unknown', with `label`). PHPStan cannot express the recursive node shape,
 * so nodes are typed as arrays here and the client type `AncestorNode` (resources/js/types/tree.ts) is the contract.
 *
 * @phpstan-import-type SheetPerson from BuildFamilySheet
 */
class BuildAncestorChart
{
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
     * @return array{focus: SheetPerson, generations: int, root: array<string, mixed>}
     */
    public function handle(Person $person, int $generations): array
    {
        $this->graph = ParentChildGraph::forTree($person->tree);
        $this->generations = $generations;
        $this->occurrences = [];

        $this->people = $person->tree->people()
            ->with('events')
            ->whereIn('id', [$person->id, ...$this->graph->ancestorIdsWithin($person->id, $generations)])
            ->get()
            ->keyBy('id');

        $focusPerson = $this->people->get($person->id) ?? $person;
        $root = $this->markRepeated($this->buildNode($focusPerson, 1));

        return [
            'focus' => $this->render($focusPerson),
            'generations' => $generations,
            'root' => $root,
        ];
    }

    /**
     * A known person; the top generation has no parents, every other one gets two parent nodes.
     *
     * @return array<string, mixed>
     */
    private function buildNode(Person $person, int $generation): array
    {
        $this->occurrences[$person->id] = ($this->occurrences[$person->id] ?? 0) + 1;

        return [
            'kind' => 'person',
            'person' => $this->render($person),
            'repeated' => false,
            'parents' => $generation < $this->generations ? $this->parentNodes($person, $generation) : [],
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
            (new Collection($this->graph->parentsOf($person->id)))
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
        /** @var SheetPerson $sheetPerson */
        $sheetPerson = SheetPersonResource::make($person)->resolve();

        return $sheetPerson;
    }
}
