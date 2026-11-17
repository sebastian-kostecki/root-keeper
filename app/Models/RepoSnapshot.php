<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What the repo held for one key at the last import. Keyed like `repo_id`, independent of the record's existence.
 *
 * @property int $id
 * @property int $tree_id
 * @property string $repo_id
 * @property array<string, string|null> $values field => normalized repo text, `null` for an empty field
 * @property bool $deletion_kept
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'repo_id', 'values', 'deletion_kept'])]
class RepoSnapshot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'deletion_kept' => 'bool',
        ];
    }

    /**
     * A short text that changes whenever the entry does, so that a write can tell the entry moved since the plan saw it.
     */
    public function fingerprint(): string
    {
        return ($this->updated_at?->format('Y-m-d H:i:s.u') ?? '').'|'.($this->deletion_kept ? '1' : '0').'|'.md5(json_encode($this->values, JSON_THROW_ON_ERROR));
    }

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }
}
