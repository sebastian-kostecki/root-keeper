<?php

namespace App\Models;

use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\MarriageForm;
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
 * @property int|null $date_until_year
 * @property DateQualifier $date_qualifier
 * @property bool $date_is_approximate
 * @property int|null $locality_id
 * @property int|null $cemetery_id
 * @property MarriageForm|null $marriage_form
 * @property-read PartialDate $date
 * @property string|null $repo_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'type', 'date_year', 'date_month', 'date_day', 'date_until_year', 'date_qualifier', 'locality_id', 'cemetery_id', 'marriage_form', 'repo_id'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * `date_is_approximate` is derived from `date_qualifier` on model save only; a bulk query-builder
     * update of `date_qualifier` would leave it stale, so change the qualifier through the model
     * until the flag is dropped.
     */
    protected static function booted(): void
    {
        static::saving(function (Event $event): void {
            $event->date_is_approximate = $event->date_qualifier === DateQualifier::Approximate;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'date_qualifier' => DateQualifier::class,
            'date_until_year' => 'integer',
            'date_is_approximate' => 'boolean',
            'marriage_form' => MarriageForm::class,
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
     * @return BelongsTo<Cemetery, $this>
     */
    public function cemetery(): BelongsTo
    {
        return $this->belongsTo(Cemetery::class);
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
                qualifier: $this->date_qualifier,
                untilYear: $this->date_until_year,
            ),
        );
    }
}
