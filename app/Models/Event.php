<?php

namespace App\Models;

use App\Enums\EventType;
use App\Support\PartialDate;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property EventType $type
 * @property int|null $date_year
 * @property int|null $date_month
 * @property int|null $date_day
 * @property bool $date_is_approximate
 * @property string|null $place
 * @property int|null $locality_id
 * @property-read PartialDate $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'type', 'date_year', 'date_month', 'date_day', 'date_is_approximate', 'place', 'locality_id'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'date_is_approximate' => 'boolean',
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
     * @return BelongsTo<Locality, $this>
     */
    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    /**
     * @return BelongsToMany<Person, $this, EventParticipant>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'event_participants')
            ->using(EventParticipant::class)
            ->withPivot('role');
    }

    /**
     * @return BelongsToMany<Source, $this, EventSource>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'event_source')
            ->using(EventSource::class)
            ->withPivot('entry_number')
            ->withTimestamps()
            ->orderBy('event_source.created_at')
            ->orderBy('event_source.id');
    }

    /**
     * @return Attribute<PartialDate, never>
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (): PartialDate => new PartialDate(
                year: $this->date_year,
                month: $this->date_month,
                day: $this->date_day,
                isApproximate: $this->date_is_approximate,
            ),
        );
    }
}
