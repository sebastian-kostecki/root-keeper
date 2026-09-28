<?php

namespace App\Actions\Relations;

use App\Enums\Gender;
use App\Models\Person;
use App\Support\ParentChildGraph;

class ListRelationCandidates
{
    /**
     * List every other person in the given person's tree, with the reason
     * each direction (as parent, as child) is blocked, if any — mirroring
     * the structural rules enforced by CreateParentChildRelation.
     *
     * @return list<array{
     *     id: int,
     *     display_name: string,
     *     birth_date: string|null,
     *     as_parent_blocked_reason: string|null,
     *     as_child_blocked_reason: string|null,
     *     as_spouse_blocked_reason: string|null,
     * }>
     */
    public function handle(Person $person): array
    {
        $graph = ParentChildGraph::forTree($person->tree);

        $parentsOfPerson = $graph->parentsOf($person->id);
        $childrenOfPerson = $graph->childrenOf($person->id);
        $descendantsOfPerson = $graph->descendantsOf($person->id);
        $ancestorsOfPerson = $graph->ancestorsOf($person->id);

        $spouseIdsOfPerson = $person->marriages()
            ->with('participants')
            ->get()
            ->flatMap(fn ($marriage) => $marriage->participants->pluck('id'))
            ->reject(fn (int $id): bool => $id === $person->id)
            ->unique()
            ->all();

        $candidates = $person->tree->people()
            ->where('id', '!=', $person->id)
            ->with('events')
            ->orderByRaw('last_name is null')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        $genderById = $candidates->mapWithKeys(fn (Person $candidate): array => [$candidate->id => $candidate->gender])
            ->put($person->id, $person->gender);

        $hasParentOfGender = fn (int $childId, ?Gender $gender): bool => $gender !== null && array_filter(
            $graph->parentsOf($childId),
            fn (int $parentId): bool => $genderById->get($parentId) === $gender,
        ) !== [];

        $sameSexParentReason = fn (?Gender $gender): ?string => match ($gender) {
            Gender::Male => 'Ma już ojca',
            Gender::Female => 'Ma już matkę',
            null => null,
        };

        return array_values($candidates->map(fn (Person $candidate): array => [
            'id' => $candidate->id,
            'display_name' => $candidate->displayName(),
            'birth_date' => $candidate->birth()?->date->format(),
            'as_parent_blocked_reason' => match (true) {
                in_array($candidate->id, $parentsOfPerson, true) => 'Już jest rodzicem',
                in_array($candidate->id, $descendantsOfPerson, true) => 'Jest potomkiem tej osoby',
                $hasParentOfGender($person->id, $candidate->gender) => $sameSexParentReason($candidate->gender),
                default => null,
            },
            'as_child_blocked_reason' => match (true) {
                in_array($candidate->id, $childrenOfPerson, true) => 'Już jest dzieckiem',
                in_array($candidate->id, $ancestorsOfPerson, true) => 'Jest przodkiem tej osoby',
                count($graph->parentsOf($candidate->id)) >= 2 => 'Ma już dwoje rodziców',
                $hasParentOfGender($candidate->id, $person->gender) => $sameSexParentReason($person->gender),
                default => null,
            },
            'as_spouse_blocked_reason' => in_array($candidate->id, $spouseIdsOfPerson, true) ? 'Już jest małżonkiem' : null,
        ])->all());
    }
}
