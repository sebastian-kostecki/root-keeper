<?php

namespace App\Actions\Trees;

use App\Enums\Gender;
use App\Http\Resources\SheetPersonResource;
use App\Models\Event;
use App\Models\Person;
use App\Support\ParentChildGraph;
use App\Support\PersonOrdering;
use Illuminate\Support\Collection;

/**
 * Assembles the fixed-shape family sheet of a focus person: four rows of
 * slots for one selected union, using a constant number of queries.
 *
 * @phpstan-type SheetPerson array{id: int, display_name: string, gender: 'male'|'female'|null, lifespan: string|null, is_deceased: bool}
 * @phpstan-type ParentOf array{id: int, display_name: string, last_name: string|null, role: 'father'|'mother'|'parent', excluded_ids: list<int>}
 * @phpstan-type Slot array{kind: 'person', person: SheetPerson}|array{kind: 'unknown', label: string, parent_of?: ParentOf}|array{kind: 'empty'}
 * @phpstan-type Union array{key: string, partner_id: int|null, label: string, is_marriage: bool, marriage_year: string|null, children_count: int}
 */
class BuildFamilySheet
{
    private const UNKNOWN_UNION = 'unknown';

    /**
     * @var Collection<int, Person>
     */
    private Collection $people;

    private ParentChildGraph $graph;

    /**
     * @return array{
     *     focus: SheetPerson,
     *     unions: list<Union>,
     *     selected_union: string|null,
     *     partner: Slot,
     *     focus_side: 'left'|'right',
     *     parents: array{focus: list<Slot>, partner: list<Slot>},
     *     grandparents: array{focus: list<Slot>, partner: list<Slot>},
     *     children: list<SheetPerson>,
     * }
     */
    public function handle(Person $focus, ?string $spouse): array
    {
        $this->graph = ParentChildGraph::forTree($focus->tree);

        /** @var Collection<int, Event> $marriages */
        $marriages = $focus->marriages()->with('participants')->get();

        $marriagePartnerIds = [];
        $marriageByPartner = [];

        foreach (PersonOrdering::marriages($marriages) as $marriage) {
            $partner = $marriage->participants->firstWhere('id', '!=', $focus->id);

            if ($partner === null || isset($marriageByPartner[$partner->id])) {
                continue;
            }

            $marriageByPartner[$partner->id] = $marriage;
            $marriagePartnerIds[] = $partner->id;
        }

        $childrenByOtherParent = [];

        foreach ($this->graph->childrenOf($focus->id) as $childId) {
            $others = array_values(array_diff($this->graph->parentsOf($childId), [$focus->id]));
            $childrenByOtherParent[$others[0] ?? 0][] = $childId;
        }

        $coParentIds = array_values(array_diff(
            array_filter(array_keys($childrenByOtherParent)),
            $marriagePartnerIds,
        ));

        $this->loadPeople($focus, [...$marriagePartnerIds, ...$coParentIds]);

        $coParentIds = PersonOrdering::relatives(
            (new Collection($coParentIds))
                ->map(fn (int $id): ?Person => $this->people->get($id))
                ->filter()
        )->map(fn (Person $person): int => $person->id)->values()->all();

        $unions = [];

        foreach ([...$marriagePartnerIds, ...$coParentIds] as $partnerId) {
            $partner = $this->people->get($partnerId);

            if ($partner === null) {
                continue;
            }

            $marriage = $marriageByPartner[$partnerId] ?? null;
            $marriageYear = $marriage?->date->formatYear();

            $unions[] = [
                'key' => "person-{$partnerId}",
                'partner_id' => $partnerId,
                'label' => $marriage === null
                    ? "{$partner->displayName()} · bez ślubu"
                    : "{$partner->displayName()} · ślub ".($marriageYear ?? '?'),
                'is_marriage' => $marriage !== null,
                'marriage_year' => $marriageYear,
                'children_count' => count($childrenByOtherParent[$partnerId] ?? []),
            ];
        }

        if (isset($childrenByOtherParent[0])) {
            $unions[] = [
                'key' => self::UNKNOWN_UNION,
                'partner_id' => null,
                'label' => 'Nieznany partner',
                'is_marriage' => false,
                'marriage_year' => null,
                'children_count' => count($childrenByOtherParent[0]),
            ];
        }

        $selected = $this->selectUnion($unions, $spouse);
        $partner = $this->partnerSlot($selected);
        $partnerPerson = $partner['kind'] === 'person' ? $this->people->get($selected['partner_id'] ?? 0) : null;

        $childIds = $selected === null ? [] : ($childrenByOtherParent[$selected['partner_id'] ?? 0] ?? []);
        $children = PersonOrdering::relatives(
            (new Collection($childIds))
                ->map(fn (int $id): ?Person => $this->people->get($id))
                ->filter()
        );

        $focusPerson = $this->people->get($focus->id) ?? $focus;

        return [
            'focus' => $this->render($focusPerson),
            'unions' => $unions,
            'selected_union' => $selected['key'] ?? null,
            'partner' => $partner,
            'focus_side' => $partnerPerson !== null && $this->rank($partnerPerson) < $this->rank($focusPerson) ? 'right' : 'left',
            'parents' => [
                'focus' => $this->parentSlots($focusPerson),
                'partner' => $this->parentSlots($partnerPerson),
            ],
            'grandparents' => [
                'focus' => $this->grandparentSlots($focusPerson),
                'partner' => $this->grandparentSlots($partnerPerson),
            ],
            'children' => array_map(
                fn (Person $child) => $this->render($child),
                array_values($children->all()),
            ),
        ];
    }

    /**
     * Batch-load every person the sheet can render, with their events.
     *
     * @param  list<int>  $partnerIds
     */
    private function loadPeople(Person $focus, array $partnerIds): void
    {
        $ids = [$focus->id, ...$partnerIds, ...$this->graph->childrenOf($focus->id)];

        foreach ([$focus->id, ...$partnerIds] as $rootId) {
            foreach ($this->graph->parentsOf($rootId) as $parentId) {
                $ids[] = $parentId;
                array_push($ids, ...$this->graph->parentsOf($parentId));
            }
        }

        $this->people = $focus->tree->people()
            ->with('events')
            ->whereIn('id', array_values(array_unique($ids)))
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<Union>  $unions
     * @return Union|null
     */
    private function selectUnion(array $unions, ?string $spouse): ?array
    {
        if ($unions === []) {
            return null;
        }

        $key = $spouse === self::UNKNOWN_UNION ? self::UNKNOWN_UNION : "person-{$spouse}";

        foreach ($unions as $union) {
            if ($union['key'] === $key) {
                return $union;
            }
        }

        return $unions[0];
    }

    /**
     * @param  Union|null  $selected
     * @return Slot
     */
    private function partnerSlot(?array $selected): array
    {
        if ($selected === null) {
            return ['kind' => 'empty'];
        }

        $partner = $selected['partner_id'] === null ? null : $this->people->get($selected['partner_id']);

        if ($partner === null) {
            return ['kind' => 'unknown', 'label' => 'Nieznany partner'];
        }

        return ['kind' => 'person', 'person' => $this->render($partner)];
    }

    /**
     * The two parent slots above a known person (male left, female right);
     * empty slots when there is no known person.
     *
     * @return list<Slot>
     */
    private function parentSlots(?Person $person): array
    {
        if ($person === null) {
            return [['kind' => 'empty'], ['kind' => 'empty']];
        }

        $known = PersonOrdering::relatives(
            (new Collection($this->graph->parentsOf($person->id)))
                ->map(fn (int $id): ?Person => $this->people->get($id))
                ->filter()
        )->sortBy(fn (Person $parent): int => $this->rank($parent))->values();

        if ($known->count() >= 2) {
            return [$this->personSlot($known[0]), $this->personSlot($known[1])];
        }

        if ($known->isEmpty()) {
            return [
                $this->unknownSlot('Nieznany ojciec', 'father', $person),
                $this->unknownSlot('Nieznana matka', 'mother', $person),
            ];
        }

        $only = $known[0];

        return match ($only->gender) {
            Gender::Male => [$this->personSlot($only), $this->unknownSlot('Nieznana matka', 'mother', $person)],
            Gender::Female => [$this->unknownSlot('Nieznany ojciec', 'father', $person), $this->personSlot($only)],
            null => [$this->personSlot($only), $this->unknownSlot('Nieznany rodzic', 'parent', $person)],
        };
    }

    /**
     * Four slots: the parents of each parent slot; empty above a placeholder.
     *
     * @return list<Slot>
     */
    private function grandparentSlots(?Person $person): array
    {
        $slots = [];

        foreach ($this->parentSlots($person) as $parentSlot) {
            $parent = $parentSlot['kind'] === 'person' ? $this->people->get($parentSlot['person']['id']) : null;

            array_push($slots, ...$this->parentSlots($parent));
        }

        return $slots;
    }

    /**
     * @return array{kind: 'person', person: SheetPerson}
     */
    private function personSlot(Person $person): array
    {
        return ['kind' => 'person', 'person' => $this->render($person)];
    }

    /**
     * An empty parent slot above the given person, carrying what the client
     * needs to fill it: whose parent, in which role, and whom the relation
     * rules exclude (the person, their parents and their descendants).
     *
     * @param  'father'|'mother'|'parent'  $role
     * @return array{kind: 'unknown', label: string, parent_of: ParentOf}
     */
    private function unknownSlot(string $label, string $role, Person $person): array
    {
        return [
            'kind' => 'unknown',
            'label' => $label,
            'parent_of' => [
                'id' => $person->id,
                'display_name' => $person->displayName(),
                'last_name' => $person->last_name,
                'role' => $role,
                'excluded_ids' => array_values(array_unique([
                    $person->id,
                    ...$this->graph->parentsOf($person->id),
                    ...$this->graph->descendantsOf($person->id),
                ])),
            ],
        ];
    }

    private function rank(Person $person): int
    {
        return match ($person->gender) {
            Gender::Male => 0,
            null => 1,
            Gender::Female => 2,
        };
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
