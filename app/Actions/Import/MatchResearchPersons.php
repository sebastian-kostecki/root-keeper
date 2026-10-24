<?php

namespace App\Actions\Import;

use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\MatchElement;
use App\Enums\MatchVerdict;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Support\Import\MatchCandidate;
use App\Support\Import\NameKey;
use App\Support\Import\PersonMatch;
use App\Support\Import\PersonMatches;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\YearSpan;
use Illuminate\Support\Collection;

class MatchResearchPersons
{
    public const BLOCKED_REASON = 'Drzewo konta ma już osoby z importu. Ponowny import (S-04) jeszcze nie działa; nic nie zostanie zapisane.';

    private const MIN_AGREEING_FOR_SURE = 2;

    private const EMPTY_VALUE = '—';

    /**
     * Compare every repo person with the persons of the tree and classify it as new, sure or doubtful, without writing.
     *
     * A repo person is sure when exactly one tree person carries the same first name and a matching surname and agrees
     * on at least two independent elements besides the name, with no contradiction. Any other case with a candidate is
     * left to the author.
     */
    public function handle(Tree $tree, ResearchRepoReading $reading): PersonMatches
    {
        if ($tree->people()->whereNotNull('repo_id')->exists()) {
            return new PersonMatches(blockedReason: self::BLOCKED_REASON);
        }

        $byFirstName = $this->indexByFirstName(
            $tree->people()
                ->with(['events.locality', 'events.participants', 'parents'])
                ->orderBy('id')
                ->get()
        );

        $matches = [];

        foreach ($reading->persons as $repoId => $repoPerson) {
            $candidates = [];

            foreach ($byFirstName[NameKey::firstName($repoPerson->givenNames) ?? ''] ?? [] as $person) {
                if ($this->sameName($person, $repoPerson)) {
                    $candidates[] = $this->compare($repoPerson, $person, $reading);
                }
            }

            $matches[$repoId] = new PersonMatch($repoPerson, $this->verdictFor($candidates), $candidates);
        }

        return new PersonMatches($this->demoteSharedCandidates($matches));
    }

    /**
     * @param  Collection<int, Person>  $people
     * @return array<string, list<Person>>
     */
    private function indexByFirstName(Collection $people): array
    {
        $index = [];

        foreach ($people as $person) {
            $index[NameKey::firstName($person->first_name) ?? ''][] = $person;
        }

        return $index;
    }

    /**
     * @param  list<MatchCandidate>  $candidates
     */
    private function verdictFor(array $candidates): MatchVerdict
    {
        if ($candidates === []) {
            return MatchVerdict::New;
        }

        if (count($candidates) === 1
            && count($candidates[0]->agreeing) >= self::MIN_AGREEING_FOR_SURE
            && $candidates[0]->contradictions === []) {
            return MatchVerdict::Sure;
        }

        return MatchVerdict::Doubtful;
    }

    /**
     * Two repo persons cannot both be a tree person; when both look sure about the same one, the author decides.
     *
     * @param  array<string, PersonMatch>  $matches
     * @return array<string, PersonMatch>
     */
    private function demoteSharedCandidates(array $matches): array
    {
        $claims = [];

        foreach ($matches as $match) {
            if ($match->verdict === MatchVerdict::Sure) {
                $claims[$match->candidates[0]->person->id][] = $match->person->id;
            }
        }

        foreach ($claims as $repoIds) {
            if (count($repoIds) < 2) {
                continue;
            }

            foreach ($repoIds as $repoId) {
                $matches[$repoId] = new PersonMatch($matches[$repoId]->person, MatchVerdict::Doubtful, $matches[$repoId]->candidates);
            }
        }

        return $matches;
    }

    private function compare(ResearchPerson $repo, Person $person, ResearchRepoReading $reading): MatchCandidate
    {
        $agreeing = [];
        $contradictions = [];

        $this->compareYears($repo->birth?->date, $person->birth(), MatchElement::BirthYear, $agreeing, $contradictions);
        $this->compareYears($repo->death?->date, $person->death(), MatchElement::DeathYear, $agreeing, $contradictions);

        $repoPlace = NameKey::normalize($repo->birth?->place);

        if ($repoPlace !== null && $repoPlace === NameKey::normalize($person->birth()?->locality?->name)) {
            $agreeing[] = MatchElement::BirthPlace;
        }

        $this->compareParent($repo->fatherId, Gender::Male, MatchElement::Father, $person, $reading, $agreeing, $contradictions);
        $this->compareParent($repo->motherId, Gender::Female, MatchElement::Mother, $person, $reading, $agreeing, $contradictions);

        $repoSpouses = $this->repoSpouses($repo, $reading);
        $treeSpouses = $this->treeSpouses($person);

        foreach ($treeSpouses as $treeSpouse) {
            foreach ($repoSpouses as $repoSpouse) {
                if ($this->sameName($treeSpouse, $repoSpouse)) {
                    $agreeing[] = MatchElement::Spouse;

                    break 2;
                }
            }
        }

        if ($repo->gender !== null && $person->gender !== null && $repo->gender !== $person->gender) {
            $contradictions[] = "płeć: repo {$repo->gender->label()}, drzewo {$person->gender->label()}";
        }

        return new MatchCandidate($person, $agreeing, $contradictions, $this->rows($repo, $person, $reading, $repoSpouses, $treeSpouses));
    }

    /**
     * @param  list<MatchElement>  $agreeing
     * @param  list<string>  $contradictions
     */
    private function compareYears(?ResearchDate $repoDate, ?Event $treeEvent, MatchElement $element, array &$agreeing, array &$contradictions): void
    {
        $repoSpan = YearSpan::fromDate($repoDate);
        $treeSpan = YearSpan::fromEvent($treeEvent);

        if ($repoSpan === null || $treeSpan === null) {
            return;
        }

        if ($repoSpan->agreesWith($treeSpan)) {
            $agreeing[] = $element;
        } elseif ($repoSpan->contradicts($treeSpan)) {
            $contradictions[] = "{$element->label()}: repo {$this->repoDateLabel($repoDate)}, drzewo {$this->eventDateLabel($treeEvent)}";
        }
    }

    /**
     * A parent agrees when a tree parent whose known gender fits the role has the same name as the repo parent, and
     * contradicts when the tree parent of that gender has another first name. The surname never contradicts, because
     * it may be written down after the husband.
     *
     * @param  list<MatchElement>  $agreeing
     * @param  list<string>  $contradictions
     */
    private function compareParent(?string $repoParentId, Gender $role, MatchElement $element, Person $person, ResearchRepoReading $reading, array &$agreeing, array &$contradictions): void
    {
        $repoParent = $repoParentId === null ? null : ($reading->persons[$repoParentId] ?? null);

        if ($repoParent === null) {
            return;
        }

        foreach ($person->parents as $parent) {
            if (($parent->gender === null || $parent->gender === $role) && $this->sameName($parent, $repoParent)) {
                $agreeing[] = $element;

                return;
            }
        }

        $repoFirstName = NameKey::firstName($repoParent->givenNames);

        foreach ($person->parents as $parent) {
            $treeFirstName = NameKey::firstName($parent->first_name);

            if ($parent->gender === $role && $repoFirstName !== null && $treeFirstName !== null && $repoFirstName !== $treeFirstName) {
                $contradictions[] = "{$element->label()}: repo {$this->repoPersonLabel($repoParent)}, drzewo {$this->treePersonLabel($parent)}";
            }
        }
    }

    /**
     * @return list<ResearchPerson>
     */
    private function repoSpouses(ResearchPerson $repo, ResearchRepoReading $reading): array
    {
        $spouses = [];

        foreach ($reading->marriages as $marriage) {
            if (! in_array($repo->id, $marriage->personIds, true)) {
                continue;
            }

            $spouseId = $marriage->personIds[0] === $repo->id ? $marriage->personIds[1] : $marriage->personIds[0];

            if (isset($reading->persons[$spouseId])) {
                $spouses[] = $reading->persons[$spouseId];
            }
        }

        return $spouses;
    }

    /**
     * @return list<Person>
     */
    private function treeSpouses(Person $person): array
    {
        $spouses = [];

        foreach ($person->events as $event) {
            if ($event->type !== EventType::Marriage) {
                continue;
            }

            foreach ($event->participants as $participant) {
                if ($participant->id !== $person->id) {
                    $spouses[] = $participant;
                }
            }
        }

        return $spouses;
    }

    /**
     * @param  list<ResearchPerson>  $repoSpouses
     * @param  list<Person>  $treeSpouses
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function rows(ResearchPerson $repo, Person $person, ResearchRepoReading $reading, array $repoSpouses, array $treeSpouses): array
    {
        $birth = $person->birth();
        $death = $person->death();

        $rows = [
            ['Imiona', $repo->givenNames ?? self::EMPTY_VALUE, $person->first_name ?? self::EMPTY_VALUE],
            ['Nazwisko', $repo->surname ?? self::EMPTY_VALUE, $person->last_name ?? self::EMPTY_VALUE],
            ['Płeć', $repo->gender?->label() ?? self::EMPTY_VALUE, $person->gender?->label() ?? self::EMPTY_VALUE],
            ['Urodzenie', $this->repoDateLabel($repo->birth?->date), $this->eventDateLabel($birth)],
            ['Miejsce urodzenia', $repo->birth->place ?? self::EMPTY_VALUE, $birth->locality->name ?? self::EMPTY_VALUE],
            ['Zgon', $this->repoDateLabel($repo->death?->date), $this->eventDateLabel($death)],
            ['Ojciec', $this->repoParentLabel($repo->fatherId, $reading), $this->treeParentsLabel($person, Gender::Male)],
            ['Matka', $this->repoParentLabel($repo->motherId, $reading), $this->treeParentsLabel($person, Gender::Female)],
        ];

        $unknownGender = $this->treeParentsLabel($person, null);

        if ($unknownGender !== self::EMPTY_VALUE) {
            $rows[] = ['Rodzic bez płci', self::EMPTY_VALUE, $unknownGender];
        }

        $rows[] = ['Małżonkowie', $this->joinLabels(array_map($this->repoPersonLabel(...), $repoSpouses)), $this->joinLabels(array_map($this->treePersonLabel(...), $treeSpouses))];
        $rows[] = ['Id', $repo->id, "#{$person->id}"];

        return $rows;
    }

    private function repoParentLabel(?string $repoParentId, ResearchRepoReading $reading): string
    {
        if ($repoParentId === null) {
            return self::EMPTY_VALUE;
        }

        $parent = $reading->persons[$repoParentId] ?? null;

        return $parent === null ? $repoParentId : $this->repoPersonLabel($parent);
    }

    private function treeParentsLabel(Person $person, ?Gender $gender): string
    {
        return $this->joinLabels(
            $person->parents
                ->filter(fn (Person $parent): bool => $parent->gender === $gender)
                ->map(fn (Person $parent): string => $this->treePersonLabel($parent))
                ->values()
                ->all()
        );
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function joinLabels(array $labels): string
    {
        return $labels === [] ? self::EMPTY_VALUE : implode('; ', $labels);
    }

    private function sameName(Person $person, ResearchPerson $repo): bool
    {
        return NameKey::firstName($person->first_name) === NameKey::firstName($repo->givenNames)
            && in_array(NameKey::surname($person->last_name), NameKey::surnames($repo), true);
    }

    private function repoPersonLabel(ResearchPerson $person): string
    {
        return $this->nameLabel($person->givenNames, $person->surname);
    }

    private function treePersonLabel(Person $person): string
    {
        return $this->nameLabel($person->first_name, $person->last_name);
    }

    private function nameLabel(?string $givenNames, ?string $surname): string
    {
        $name = trim("{$givenNames} {$surname}");

        return $name === '' ? self::EMPTY_VALUE : $name;
    }

    private function repoDateLabel(?ResearchDate $date): string
    {
        return $date?->label() ?? self::EMPTY_VALUE;
    }

    /**
     * The date of a stored event in the block grammar.
     */
    private function eventDateLabel(?Event $event): string
    {
        if ($event === null || $event->date_year === null) {
            return self::EMPTY_VALUE;
        }

        $qualifier = $event->date_qualifier === DateQualifier::Range && $event->date_until_year === null
            ? DateQualifier::Exact
            : $event->date_qualifier;

        return (new ResearchDate($qualifier, $event->date_year, $event->date_month, $event->date_day, $event->date_until_year))->label();
    }
}
