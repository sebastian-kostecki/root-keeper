<?php

namespace Database\Factories;

use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Models\Source;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'kind' => SourceKind::MetricalRecord,
            'original_name' => fake()->slug().'.jpg',
            'path' => fn (array $attributes): string => "sources/{$attributes['tree_id']}/".Str::uuid().'.jpg',
            'thumbnail_path' => null,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'width' => fake()->numberBetween(800, 4000),
            'height' => fake()->numberBetween(600, 3000),
        ];
    }

    /**
     * Indicate that the source has a thumbnail.
     */
    public function withThumbnail(): static
    {
        return $this->state([
            'thumbnail_path' => fn (array $attributes): string => "sources/{$attributes['tree_id']}/thumbnails/".Str::uuid().'.jpg',
        ]);
    }

    /**
     * Indicate that the source is a gravestone photo.
     */
    public function gravestone(): static
    {
        return $this->state(['kind' => SourceKind::Gravestone]);
    }

    /**
     * Indicate that the source is of another kind.
     */
    public function other(): static
    {
        return $this->state(['kind' => SourceKind::Other]);
    }

    /**
     * Indicate that the source has every description field filled in (an akt).
     */
    public function described(): static
    {
        return $this->state([
            'kind' => SourceKind::MetricalRecord,
            'name' => 'Urodzenia Lipnica 1878 s. 12',
            'record_type' => SourceRecordType::Birth,
            'year' => 1878,
            'archive' => 'Archiwum Państwowe w Krakowie',
            'signature' => '35/1813/0/2.4/58',
            'url' => 'https://example.com/akt',
            'note' => 'Wpis po prawej stronie rozkładówki.',
        ]);
    }
}
