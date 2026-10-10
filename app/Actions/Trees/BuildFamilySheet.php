<?php

namespace App\Actions\Trees;

use App\Enums\EventType;
use App\Enums\Gender;
use App\Http\Resources\SheetPersonResource;
use App\Models\Event;
use App\Models\Person;
use App\Support\ParentChildGraph;
use App\Support\PersonOrdering;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the fixed-shape family sheet of a focus person: four rows of
 * slots for one selected union, using a constant number of queries.
 *
 * @phpstan-type SheetPerson array{id: int, display_name: string, gender: 'male'|'female'|null, lifespan: string|null, is_deceased: bool, age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null}
 * @phpstan-type ParentOf array{id: int, display_name: string, last_name: string|null, role: 'father'|'mother'|'parent', excluded_ids: list<int>}
 * @phpstan-type Slot array{kind: 'person', person: SheetPerson}|array{kind: 'unknown', label: string, parent_of?: ParentOf}|array{kind: 'empty'}
 * @phpstan-type AddChild array{parents: list<array{id: int, display_name: string}>, default_last_name: string|null, excluded_ids: list<int>, blocked_ids: list<int>}
 * @phpstan-type AddSpouse array{excluded_ids: list<int>}
 * @phpstan-type UnmarriedParents array{focus: bool, partner: bool}
 * @phpstan-type Union array{key: string, partner_id: int|null, partner_name: string|null, is_marriage: bool, marriage_id: int|null, marriage_year: string|null, children_count: int}
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
     *     add_child: AddChild,
     *     add_spouse: AddSpouse,
     *     unmarried_parents: UnmarriedParents,
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
                'partner_name' => $partner->displayName(),
                'is_marriage' => $marriage !== null,
                'marriage_id' => $marriage?->id,
                'marriage_year' => $marriageYear,
                'children_count' => count($childrenByOtherParent[$partnerId] ?? []),
            ];
        }

        if (isset($childrenByOtherParent[0])) {
            $unions[] = [
                'key' => self::UNKNOWN_UNION,
                'partner_id' => null,
                'partner_name' => null,
                'is_marriage' => false,
                'marriage_id' => null,
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
        $parentSlots = [
            'focus' => $this->parentSlots($focusPerson),
            'partner' => $this->parentSlots($partnerPerson),
        ];

        return [
            'focus' => $this->render($focusPerson),
            'unions' => $unions,
            'selected_union' => $selected['key'] ?? null,
            'partner' => $partner,
            'focus_side' => $partnerPerson !== null && PersonOrdering::parentRank($partnerPerson) < PersonOrdering::parentRank($focusPerson) ? 'right' : 'left',
            'parents' => $parentSlots,
            'grandparents' => [
                'focus' => $this->grandparentSlots($focusPerson),
                'partner' => $this->grandparentSlots($partnerPerson),
            ],
            'children' => array_map(
                fn (Person $child) => $this->render($child),
                array_values($children->all()),
            ),
            'add_child' => $this->addChild($focusPerson, $partnerPerson),
            'add_spouse' => [
                'excluded_ids' => array_values(array_unique([
                    $focus->id,
                    ...$marriagePartnerIds,
                    ...$this->graph->ancestorsOf($focus->id),
                    ...$this->graph->descendantsOf($focus->id),
                ])),
            ],
            'unmarried_parents' => $this->unmarriedParents($focus, $parentSlots),
        ];
    }

    /**
     * Whether each shown parent pair is two known people without a marriage
     * between them; the marriages of the up to four parents come from one query.
     *
     * @param  array{focus: list<Slot>, partner: list<Slot>}  $parentSlots
     * @return UnmarriedParents
     */
    private function unmarriedParents(Person $focus, array $parentSlots): array
    {
        $pairs = [];

        foreach (['focus', 'partner'] as $block) {
            $ids = [];

            foreach ($parentSlots[$block] as $slot) {
                if ($slot['kind'] === 'person') {
                    $ids[] = $slot['person']['id'];
                }
            }

            $pairs[$block] = count($ids) === 2 ? $ids : null;
        }

        $parentIds = array_values(array_unique(array_merge(...array_map(
            fn (?array $ids): array => $ids ?? [],
            array_values($pairs),
        ))));

        $participantsByMarriage = DB::table('event_participants')
            ->join('events', 'events.id', '=', 'event_participants.event_id')
            ->where('events.tree_id', $focus->tree_id)
            ->where('events.type', EventType::Marriage->value)
            ->whereIn('event_participants.person_id', $parentIds)
            ->get(['event_participants.event_id', 'event_participants.person_id'])
            ->groupBy('event_id');

        $marriedPairs = $participantsByMarriage
            ->map(fn (Collection $rows): array => $rows->pluck('person_id')->map(fn (mixed $id): int => (int) $id)->all())
            ->values()
            ->all();

        $isUnmarried = fn (?array $ids): bool => $ids !== null
            && collect($marriedPairs)->every(fn (array $married): bool => array_diff($ids, $married) !== []);

        return [
            'focus' => $isUnmarried($pairs['focus']),
            'partner' => $isUnmarried($pairs['partner']),
        ];
    }

    /**
     * What the "add child" card needs for the shown pair: the parents, the
     * people who can never be its child (excluded) and the people whose
     * existing parents would make the link break a rule (blocked).
     *
     * @return AddChild
     */
    private function addChild(Person $focus, ?Person $partner): array
    {
        $parents = $partner === null ? [$focus] : [$focus, $partner];
        $parentIds = array_map(fn (Person $parent): int => $parent->id, $parents);

        $excludedIds = $parentIds;

        foreach ($parentIds as $parentId) {
            array_push($excludedIds, ...$this->graph->ancestorsOf($parentId));
        }

        foreach ($this->graph->childIds() as $childId) {
            if (array_diff($parentIds, $this->graph->parentsOf($childId)) === []) {
                $excludedIds[] = $childId;
            }
        }

        $excludedIds = array_values(array_unique($excludedIds));
        $candidateIds = array_values(array_diff($this->graph->childIds(), $excludedIds));

        $otherParentIds = [];

        foreach ($candidateIds as $childId) {
            array_push($otherParentIds, ...array_diff($this->graph->parentsOf($childId), $parentIds));
        }

        $genders = [];

        foreach ($parents as $parent) {
            $genders[$parent->id] = $parent->gender;
        }

        if ($otherParentIds !== []) {
            $genders += $focus->tree->people()
                ->whereIn('id', array_values(array_unique($otherParentIds)))
                ->pluck('gender', 'id')
                ->all();
        }

        $blockedIds = [];

        foreach ($candidateIds as $childId) {
            $childParentIds = $this->graph->parentsOf($childId);
            $finalParentIds = array_values(array_unique([...$childParentIds, ...$parentIds]));

            if (count($finalParentIds) > 2 || $this->addsSecondParentOfSameSex($parentIds, $childParentIds, $finalParentIds, $genders)) {
                $blockedIds[] = $childId;
            }
        }

        $firstMan = collect($parents)->first(fn (Person $parent): bool => $parent->gender === Gender::Male);

        return [
            'parents' => array_map(fn (Person $parent): array => [
                'id' => $parent->id,
                'display_name' => $parent->displayName(),
            ], $parents),
            'default_last_name' => ($firstMan ?? $focus)->last_name,
            'excluded_ids' => $excludedIds,
            'blocked_ids' => $blockedIds,
        ];
    }

    /**
     * Whether linking the pair would give the child two parents of the same
     * known sex: a newly added parent clashing with a parent the child keeps
     * or with the other newly added one. Unknown sex never conflicts.
     *
     * @param  list<int>  $pairIds
     * @param  list<int>  $currentParentIds
     * @param  list<int>  $finalParentIds
     * @param  array<int, Gender|null>  $genders
     */
    private function addsSecondParentOfSameSex(array $pairIds, array $currentParentIds, array $finalParentIds, array $genders): bool
    {
        foreach (array_diff($pairIds, $currentParentIds) as $newId) {
            $gender = $genders[$newId] ?? null;

            if ($gender === null) {
                continue;
            }

            foreach ($finalParentIds as $otherId) {
                if ($otherId !== $newId && ($genders[$otherId] ?? null) === $gender) {
                    return true;
                }
            }
        }

        return false;
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

        $known = PersonOrdering::parents(
            (new Collection($this->graph->parentsOf($person->id)))
                ->map(fn (int $id): ?Person => $this->people->get($id))
                ->filter()
        );

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
