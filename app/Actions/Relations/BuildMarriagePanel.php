<?php

namespace App\Actions\Relations;

use App\Enums\Gender;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Person;
use App\Support\ParentChildGraph;
use App\Support\PersonOrdering;
use Illuminate\Support\Collection;

/**
 * Assembles the marriage panel of one marriage event: the wedding with its place and sources,
 * the two spouses in their fixed order, and the children both spouses share.
 *
 * @phpstan-type MarriagePanelPerson array{id: int, display_name: string, gender: 'male'|'female'|null, birth_date: string|null, death_date: string|null}
 */
class BuildMarriagePanel
{
    /**
     * @return array{
     *     marriage: array<string, mixed>,
     *     spouses: list<MarriagePanelPerson>,
     *     children: list<MarriagePanelPerson>,
     * }
     */
    public function handle(Event $marriage): array
    {
        $marriage->load(['locality', 'sources', 'participants.events', 'tree']);

        /** @var Collection<int, Person> $spouses */
        $spouses = PersonOrdering::spouses($marriage->participants);
        $spouseIds = $spouses->pluck('id')->all();

        $graph = ParentChildGraph::forTree($marriage->tree);
        $sharedChildIds = array_values(array_intersect(
            $graph->childrenOf($spouseIds[0]),
            $graph->childrenOf($spouseIds[1]),
        ));

        $children = PersonOrdering::relatives(
            $marriage->tree->people()
                ->with('events')
                ->whereIn('id', $sharedChildIds)
                ->get()
        );

        return [
            'marriage' => EventResource::make($marriage)->resolve(),
            'spouses' => $this->summaries($spouses),
            'children' => $this->summaries($children),
        ];
    }

    /**
     * @param  Collection<int, Person>  $people
     * @return list<MarriagePanelPerson>
     */
    private function summaries(Collection $people): array
    {
        $summaries = [];

        foreach ($people as $person) {
            $summaries[] = $this->summary($person);
        }

        return $summaries;
    }

    /**
     * @return MarriagePanelPerson
     */
    private function summary(Person $person): array
    {
        return [
            'id' => $person->id,
            'display_name' => $person->displayName(),
            'gender' => match ($person->gender) {
                Gender::Male => 'male',
                Gender::Female => 'female',
                null => null,
            },
            'birth_date' => $person->birth()?->date->format(),
            'death_date' => $person->death()?->date->format(),
        ];
    }
}
