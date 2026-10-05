<?php

namespace App\Http\Requests;

use App\Concerns\SourceDescriptionValidationRules;
use App\Enums\SourceKind;
use App\Models\Source;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SourceUpdateRequest extends FormRequest
{
    use SourceDescriptionValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('source'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => [
                'required',
                Rule::enum(SourceKind::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (SourceKind::tryFrom((string) $value) !== null && $this->wouldEraseRecordFields($value) && ! $this->boolean('discard_record_fields')) {
                        $fail(__('Zmiana rodzaju usunie typ aktu, numer wpisu lub parafię. Potwierdź zmianę.'));
                    }
                },
            ],
            'discard_record_fields' => ['sometimes', 'boolean'],
            ...$this->sourceDescriptionRules(),
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
            'kind' => 'rodzaj',
            ...$this->sourceDescriptionAttributes(),
        ];
    }

    /**
     * Whether saving the submitted kind would erase a stored record type, entry number or parish.
     */
    private function wouldEraseRecordFields(mixed $submittedKind): bool
    {
        /** @var Source $source */
        $source = $this->route('source');

        return $source->kind === SourceKind::MetricalRecord
            && $submittedKind !== SourceKind::MetricalRecord->value
            && ($source->record_type !== null || $source->entry_number !== null || $source->parish_id !== null);
    }
}
