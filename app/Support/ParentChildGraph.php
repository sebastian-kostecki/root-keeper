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
