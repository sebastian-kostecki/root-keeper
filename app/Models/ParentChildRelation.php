<?php

namespace App\Models;

use Database\Factories\ParentChildRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property int $parent_id
 * @property int $child_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'parent_id', 'child_id'])]
class ParentChildRelation extends Model
{
    /** @use HasFactory<ParentChildRelationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'child_id');
    }
}
