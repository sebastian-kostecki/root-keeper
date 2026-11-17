<?php

namespace App\Support\Import;

/**
 * The parent-child pairs of the tree together with the pairs the import plans, as one graph. Nodes are strings:
 * "t<id>" for a person of the tree and the repo id for a person the import adds. Existing pairs are never removed.
 */
final class SharedParentGraph
{
    /** @var array<string, list<string>> */
    private array $parents = [];

    /** @var array<string, list<string>> */
    private array $children = [];

    /** @var array<string, list<string>> */
    private array $existingParents = [];

    /** @var array<string, list<string>> */
    private array $existingChildren = [];

    /** @var array<string, int> */
    private array $existingIds = [];

    public static function node(int $personId): string
    {
        return 't'.$personId;
    }

    public function addExisting(string $parent, string $child, int $relationId): void
    {
        $this->parents[$child][] = $parent;
        $this->children[$parent][] = $child;
        $this->existingParents[$child][] = $parent;
        $this->existingChildren[$parent][] = $child;
        $this->existingIds[$parent.'|'.$child] = $relationId;
    }

    public function addPlanned(string $parent, string $child): void
    {
        $this->parents[$child][] = $parent;
        $this->children[$parent][] = $child;
    }

    public function existingId(string $parent, string $child): ?int
    {
        return $this->existingIds[$parent.'|'.$child] ?? null;
    }

    /**
     * @return list<string>
     */
    public function parentsOf(string $child, bool $existingOnly = false): array
    {
        return ($existingOnly ? $this->existingParents : $this->parents)[$child] ?? [];
    }

    /**
     * @return list<string>
     */
    public function childrenOf(string $parent, bool $existingOnly = false): array
    {
        return ($existingOnly ? $this->existingChildren : $this->children)[$parent] ?? [];
    }

    /**
     * Whether a parent-child pair from $parent to $child would close a cycle.
     */
    public function wouldCycle(string $parent, string $child): bool
    {
        if ($parent === $child) {
            return true;
        }

        $visited = [$child => true];
        $queue = [$child];

        while ($queue !== []) {
            $current = array_pop($queue);

            foreach ($this->children[$current] ?? [] as $next) {
                if ($next === $parent) {
                    return true;
                }

                if (! isset($visited[$next])) {
                    $visited[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        return false;
    }
}
