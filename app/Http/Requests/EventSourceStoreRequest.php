<?php

namespace App\Http\Requests;

use App\Enums\SourceKind;
use App\Models\Source;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EventSourceStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('sources', 'id')->where('tree_id', $this->user()->tree->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isMetricalRecord = Source::query()
                        ->whereKey($value)
                        ->where('kind', SourceKind::MetricalRecord)
                        ->exists();

                    if (! $isMetricalRecord) {
                        $fail(__('Do wydarzenia można na razie powiązać tylko akt metrykalny.'));
                    }
                },
            ],
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
            'source_id' => 'źródło',
        ];
    }
}
