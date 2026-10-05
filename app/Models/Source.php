<?php

namespace App\Models;

use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property SourceKind $kind
 * @property string|null $name
 * @property SourceRecordType|null $record_type
 * @property string|null $entry_number
 * @property int|null $year
 * @property int|null $parish_id
 * @property string|null $archive
 * @property string|null $url
 * @property string|null $note
 * @property string $original_name
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tree_id', 'kind', 'original_name', 'path', 'thumbnail_path', 'mime_type', 'size', 'width', 'height', 'name', 'record_type', 'entry_number', 'year', 'parish_id', 'archive', 'url', 'note'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
            'record_type' => SourceRecordType::class,
            'year' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * The name shown wherever the source appears: its name, or the file name when the name is empty.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->original_name;
    }

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }

    /**
     * @return BelongsTo<Parish, $this>
     */
    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    /**
     * @return BelongsToMany<Event, $this, EventSource>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_source')
            ->using(EventSource::class)
            ->withPivot('entry_number')
            ->withTimestamps()
            ->orderBy('event_source.created_at')
            ->orderBy('event_source.id');
    }
}
