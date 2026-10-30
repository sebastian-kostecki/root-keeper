<?php

namespace App\Models;

use Database\Factories\LocalityAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property int $locality_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'locality_id', 'name'])]
class LocalityAlias extends Model
{
    /** @use HasFactory<LocalityAliasFactory> */
    use HasFactory;

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
}
