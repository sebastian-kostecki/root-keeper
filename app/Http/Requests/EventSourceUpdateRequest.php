<?php

namespace App\Http\Requests;

use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EventSourceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $event = $this->route('event');

        if ($event instanceof Event && ! $event->type->acceptsSources()) {
            return Response::denyAsNotFound();
        }

        $source = $this->route('source');

        if ($source instanceof Source && $source->kind === SourceKind::Gravestone) {
            return Response::denyAsNotFound();
        }

        $response = Gate::inspect('update', $event);

        return $response->allowed() ? Gate::inspect('view', $source) : $response;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'entry_number' => 'numer wpisu',
        ];
    }
}
