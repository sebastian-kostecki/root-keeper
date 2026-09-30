<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The preview's view of a marriage: the shared EventResource fields plus
 * the raw date parts for the edit form and the other spouse.
 *
 * @property-read Event $resource
 *
 * @mixin Event
 */
class MarriageResource extends JsonResource
{
    public function __construct(Event $resource, private readonly Person $viewedPerson)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     date: string|null,
     *     place: string|null,
     *     is_confirmed?: bool,
     *     sources?: list<array{id: int, kind: string, original_name: string, display_name: string, has_thumbnail: bool, uploaded_at: string|null}>,
     *     date_parts: array{year: int|null, month: int|null, day: int|null, is_approximate: bool},
     *     spouse: array{id: int, display_name: string, gender: 'male'|'female'|null, birth_date: string|null, death_date: string|null}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $spouse = $this->participants->firstWhere('id', '!=', $this->viewedPerson->id);

        return [
            ...EventResource::make($this->resource)->toArray($request),
            'spouse' => $spouse === null ? null : [
                'id' => $spouse->id,
                'display_name' => $spouse->displayName(),
                'gender' => $spouse->gender?->value,
                'birth_date' => $spouse->birth()?->date->format(),
                'death_date' => $spouse->death()?->date->format(),
            ],
        ];
    }
}
