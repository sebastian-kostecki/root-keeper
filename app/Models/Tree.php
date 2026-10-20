<?php

namespace App\Models;

use Database\Factories\TreeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $root_person_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'root_person_id'])]
class Tree extends Model
{
    /** @use HasFactory<TreeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function rootPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'root_person_id');
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /**
     * @return HasMany<Locality, $this>
     */
    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }

    /**
     * @return HasMany<Parish, $this>
     */
    public function parishes(): HasMany
    {
        return $this->hasMany(Parish::class);
    }

    /**
     * @return HasMany<Cemetery, $this>
     */
    public function cemeteries(): HasMany
    {
        return $this->hasMany(Cemetery::class);
    }

    /**
     * @return HasMany<ParentChildRelation, $this>
     */
    public function parentChildRelations(): HasMany
    {
        return $this->hasMany(ParentChildRelation::class);
    }
}
