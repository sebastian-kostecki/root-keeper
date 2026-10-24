<?php

namespace App\Actions\Import;

use App\Actions\Persons\MapsEventInput;
use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Person;
use App\Models\Tree;
use App\Support\Import\FirstImportPlan;
use App\Support\Import\FirstImportResult;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyFirstImport
{
    use MapsEventInput;

    public function __construct(
        private CreateParentChildRelation $createRelation,
        private CreateMarriage $createMarriage,
    ) {}

    /**
     * Write a first-import plan into the tree, all or nothing. Any exception rolls the whole write back.
     *
     * @throws RuntimeException when the plan is blocked or the tree got persons after planning
     */
    public function handle(Tree $tree, FirstImportPlan $plan): FirstImportResult
    {
        if ($plan->blockedReason !== null) {
            throw new RuntimeException($plan->blockedReason);
        }

        return DB::transaction(function () use ($tree, $plan): FirstImportResult {
            Tree::whereKey($tree->id)->lockForUpdate()->firstOrFail();

            if ($tree->people()->exists()) {
                throw new RuntimeException('Drzewo konta ma już osoby; nic nie zostało zapisane.');
            }

            $people = [];
            $births = 0;
            $deaths = 0;

            foreach ($plan->persons as $planned) {
                $research = $planned->person;

                $person = $tree->people()->create([
                    'first_name' => $research->givenNames,
                    'last_name' => $research->surname,
                    'gender' => $research->gender,
                    'note' => $planned->note,
                    'repo_id' => $research->id,
                ]);
                $people[$research->id] = $person;

                if ($research->birth !== null) {
                    $this->createEvent($tree, $person, EventType::Birth, $research->birth, $planned->birthRepoId);
                    $births++;
                }

                if ($research->death !== null) {
                    $this->createEvent($tree, $person, EventType::Death, $research->death, $planned->deathRepoId);
                    $deaths++;
                }
            }

            foreach ($plan->relations as $planned) {
                $relation = $this->createRelation->handle(
                    $tree,
                    $this->personFor($people, $planned->parentId),
                    $this->personFor($people, $planned->childId),
                );
                $relation->update(['repo_id' => $planned->repoId]);
            }

            foreach ($plan->marriages as $planned) {
                [$first, $second] = $planned->marriage->personIds;

                $event = $this->createMarriage->handle(
                    $tree,
                    $this->personFor($people, $first),
                    $this->personFor($people, $second),
                    $this->dateInput($planned->marriage->event->date),
                );
                $event->update(['repo_id' => $planned->repoId]);
            }

            return new FirstImportResult(
                persons: count($people),
                births: $births,
                deaths: $deaths,
                marriages: count($plan->marriages),
                relations: count($plan->relations),
                events: $births + $deaths + count($plan->marriages),
            );
        });
    }

    private function createEvent(Tree $tree, Person $person, EventType $type, ResearchEvent $research, ?string $repoId): void
    {
        $event = $tree->events()->create([
            'type' => $type,
            ...$this->eventAttributesFrom($this->dateInput($research->date)),
            'repo_id' => $repoId,
        ]);

        $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    }

    /**
     * @return array{year?: int, month?: int|null, day?: int|null, qualifier?: DateQualifier, until_year?: int|null}
     */
    private function dateInput(?ResearchDate $date): array
    {
        if ($date === null) {
            return [];
        }

        return [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'qualifier' => $date->qualifier,
            'until_year' => $date->untilYear,
        ];
    }

    /**
     * @param  array<string, Person>  $people
     */
    private function personFor(array $people, string $repoId): Person
    {
        return $people[$repoId] ?? throw new RuntimeException("Plan odwołuje się do osoby {$repoId}, której nie ma w planie.");
    }
}
