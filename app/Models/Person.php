<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\Religion;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $residence
 * @property Religion|null $religion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'first_name', 'last_name', 'residence', 'religion'])]
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
     * @return BelongsToMany<Event, $this, EventParticipant>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_participants')
            ->using(EventParticipant::class)
            ->withPivot('role');
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

    public function displayName(): string
    {
        return trim(($this->first_name ?? 'NN').' '.($this->last_name ?? 'NN'));
    }
}
