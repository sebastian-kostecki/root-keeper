<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Support\Age;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * @property int $id
 * @property int $tree_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $maiden_name
 * @property Gender|null $gender
 * @property string|null $residence
 * @property Religion|null $religion
 * @property string|null $note
 * @property string|null $photo_path
 * @property string|null $photo_thumbnail_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'first_name', 'last_name', 'maiden_name', 'gender', 'residence', 'religion', 'note', 'photo_path', 'photo_thumbnail_path'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'religion' => Religion::class,
        ];
    }

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }

    /**
     * @return HasMany<Residence, $this>
     */
    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class);
    }

    /**
     * @return BelongsToMany<Event, $this, EventParticipant>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_participants')
            ->using(EventParticipant::class)
            ->withPivot('role');
    }

    /**
     * The gravestones this person is listed on, in the order they were added.
     *
     * @return BelongsToMany<Source, $this>
     */
    public function gravestones(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'buried_people')
            ->withTimestamps()
            ->orderBy('buried_people.created_at')
            ->orderBy('buried_people.id');
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'parent_child_relations', 'child_id', 'parent_id')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'parent_child_relations', 'parent_id', 'child_id')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Event, $this, EventParticipant>
     */
    public function marriages(): BelongsToMany
    {
        return $this->events()->where('type', EventType::Marriage);
    }

    public function birth(): ?Event
    {
        return $this->events->firstWhere('type', EventType::Birth);
    }

    public function death(): ?Event
    {
        return $this->events->firstWhere('type', EventType::Death);
    }

    public function burial(): ?Event
    {
        return $this->events->firstWhere('type', EventType::Burial);
    }

    /**
     * Requires the `events` relation loaded. "Today" is the Polish calendar day.
     */
    public function age(): ?Age
    {
        return Age::from(
            $this->birth()?->date,
            $this->death()?->date,
            $this->death() !== null,
            Date::now('Europe/Warsaw')->toImmutable()->startOfDay(),
        );
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? 'NN').' '.($this->last_name ?? 'NN'));

        $showsMaidenName = filled($this->last_name)
            && filled($this->maiden_name)
            && mb_strtolower($this->last_name) !== mb_strtolower($this->maiden_name);

        return $showsMaidenName ? $name.' z d. '.$this->maiden_name : $name;
    }

    /**
     * The name printed on a tree card: the first name and the birth surname.
     */
    public function cardName(): string
    {
        ['given' => $given, 'surname' => $surname] = $this->cardNameParts();

        return trim($given.' '.$surname);
    }

    /**
     * The two parts of {@see cardName()}, for cards that print them on separate lines.
     *
     * @return array{given: string, surname: string}
     */
    public function cardNameParts(): array
    {
        return [
            'given' => $this->first_name ?? 'NN',
            'surname' => filled($this->maiden_name) ? $this->maiden_name : ($this->last_name ?? 'NN'),
        ];
    }
}
