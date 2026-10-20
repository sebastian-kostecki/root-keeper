<?php

namespace App\Models;

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use Database\Factories\CemeteryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property int $locality_id
 * @property string|null $name
 * @property CemeteryManager|null $manager
 * @property Religion|null $religion
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'locality_id', 'name', 'manager', 'religion', 'description'])]
class Cemetery extends Model
{
    /** @use HasFactory<CemeteryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manager' => CemeteryManager::class,
            'religion' => Religion::class,
        ];
    }

    /**
     * "cemetery {locality}" without a name, otherwise "{name}, {locality}". Needs the locality relation loaded.
     */
    public function label(): string
    {
        return $this->name === null
            ? "cmentarz {$this->locality->name}"
            : "{$this->name}, {$this->locality->name}";
    }

    /**
     * The cemetery as offered in a suggestion list. Needs the locality relation loaded.
     *
     * @return array{id: int, label: string, locality_id: int, locality_name: string}
     */
    public function toOption(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'locality_id' => $this->locality_id,
            'locality_name' => $this->locality->name,
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
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function burials(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
