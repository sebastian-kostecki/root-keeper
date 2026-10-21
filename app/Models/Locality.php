<?php

namespace App\Models;

use App\Enums\LocalityKind;
use Database\Factories\LocalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property string $name
 * @property LocalityKind|null $kind
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $historical_affiliation
 * @property string|null $description
 * @property bool $is_defunct
 * @property string|null $defunct_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'name', 'kind', 'latitude', 'longitude', 'historical_affiliation', 'description', 'is_defunct', 'defunct_note'])]
class Locality extends Model
{
    /** @use HasFactory<LocalityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LocalityKind::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'is_defunct' => 'boolean',
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
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Residence, $this>
     */
    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class);
    }
}
