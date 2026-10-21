<?php

namespace App\Models;

use App\Enums\ParishKind;
use App\Enums\Religion;
use Database\Factories\ParishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property string|null $name The former name, read-only; cleared by the author once moved into the seat and dedication.
 * @property ParishKind $kind
 * @property string|null $dedication
 * @property Religion|null $religion
 * @property int|null $locality_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'name', 'kind', 'dedication', 'religion', 'locality_id'])]
class Parish extends Model
{
    /** @use HasFactory<ParishFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ParishKind::class,
            'religion' => Religion::class,
        ];
    }

    /**
     * "{seat}[ {kind suffix}][ {dedication}][ ({short religion})]", as in Geneteka; a legacy row without a seat shows
     * its former name instead of the seat. Needs the locality relation loaded.
     */
    public function label(): string
    {
        $parts = $this->locality_id === null
            ? [$this->name]
            : [$this->locality->name, $this->kind->labelSuffix(), $this->dedication];

        $label = implode(' ', array_filter($parts, fn (?string $part): bool => $part !== null && $part !== ''));

        return $this->religion === null ? $label : "{$label} ({$this->religion->shortLabel()})";
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
}
