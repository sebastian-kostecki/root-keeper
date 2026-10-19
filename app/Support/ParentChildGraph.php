<?php

namespace App\Support;

use App\Models\Tree;

/**
 * An in-memory view of a tree's parent-child pairs, answering the
 * structural questions shared by the relation-creating action and the
 * person picker.
 */
final class ParentChildGraph
{
    /**
     * @param  array<int, list<int>>  $parentsByChild
     * @param  array<int, list<int>>  $childrenByParent
     */
    private function __construct(
        private readonly array $parentsByChild,
        private readonly array $childrenByParent,
    ) {}

    public static function forTree(Tree $tree): self
    {
        $pairs = $tree->parentChildRelations()->get(['parent_id', 'child_id']);

        $parentsByChild = [];
        $childrenByParent = [];

        foreach ($pairs as $pair) {
            $parentsByChild[$pair->child_id][] = $pair->parent_id;
            $childrenByParent[$pair->parent_id][] = $pair->child_id;
        }

        return new self($parentsByChild, $childrenByParent);
    }

    /**
     * @return list<int>
     */
    public function parentsOf(int $personId): array
    {
        return $this->parentsByChild[$personId] ?? [];
    }

    /**
     * Every person who has at least one parent.
     *
     * @return list<int>
     */
    public function childIds(): array
    {
        return array_keys($this->parentsByChild);
    }

    /**
     * @return list<int>
     */
    public function childrenOf(int $personId): array
    {
        return $this->childrenByParent[$personId] ?? [];
    }

    /**
     * @return list<int>
     */
    public function descendantsOf(int $personId): array
    {
        return $this->traverse($personId, $this->childrenByParent);
    }

    /**
     * @return list<int>
     */
    public function ancestorsOf(int $personId): array
    {
        return $this->traverse($personId, $this->parentsByChild);
    }

    /**
     * The ids of the ancestors at most `$generations - 1` levels above the person (the person is generation 1),
     * without the person and without duplicates; cycle-safe and independent of the number of parents.
     *
     * @return list<int>
     */
    public function ancestorIdsWithin(int $personId, int $generations): array
    {
        $visited = [$personId => true];
        $ids = [];
        $level = [$personId];

        for ($depth = 1; $depth < $generations && $level !== []; $depth++) {
            $nextLevel = [];

            foreach ($level as $current) {
                foreach ($this->parentsOf($current) as $parentId) {
                    if (isset($visited[$parentId])) {
                        continue;
                    }

                    $visited[$parentId] = true;
                    $ids[] = $parentId;
                    $nextLevel[] = $parentId;
                }
            }

            $level = $nextLevel;
        }

        return $ids;
    }

    /**
     * At most `$count` parents of the person, the lowest ids first: a stable choice that does not need the
     * people loaded, for a view that draws a fixed number of parents.
     *
     * @return list<int>
     */
    public function firstParentsOf(int $personId, int $count): array
    {
        $parentIds = $this->parentsOf($personId);
        sort($parentIds);

        return array_slice($parentIds, 0, $count);
    }

    /**
     * The shortest number of generations from the person to each ancestor, the person included at 0,
     * up to `$maxLevel` and following only `firstParentsOf(…, $parentsPerPerson)`; cycle-safe and linear
     * in the size of the graph.
     *
     * @return array<int, int>
     */
    public function ancestorLevels(int $personId, int $maxLevel, int $parentsPerPerson): array
    {
        $levels = [$personId => 0];
        $level = [$personId];

        for ($depth = 1; $depth <= $maxLevel && $level !== []; $depth++) {
            $nextLevel = [];

            foreach ($level as $current) {
                foreach ($this->firstParentsOf($current, $parentsPerPerson) as $parentId) {
                    if (isset($levels[$parentId])) {
                        continue;
                    }

                    $levels[$parentId] = $depth;
                    $nextLevel[] = $parentId;
                }
            }

            $level = $nextLevel;
        }

        return $levels;
    }

    public function hasRelation(int $parentId, int $childId): bool
    {
        return in_array($parentId, $this->parentsByChild[$childId] ?? [], true);
    }

    /**
     * Walk the given adjacency map from the person, collecting every
     * reachable id without revisiting a node (cycle-safe).
     *
     * @param  array<int, list<int>>  $adjacency
     * @return list<int>
     */
    private function traverse(int $personId, array $adjacency): array
    {
        $visited = [];
        $queue = $adjacency[$personId] ?? [];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $next) {
                if (! isset($visited[$next])) {
                    $queue[] = $next;
                }
            }
        }

        return array_keys($visited);
    }
}
