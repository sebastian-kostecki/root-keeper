<?php

namespace App\Http\Requests;

use App\Concerns\PartialDateValidationRules;
use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class MarriageUpdateRequest extends FormRequest
{
    use PartialDateValidationRules;

    /**
     * Determine if the user is authorized to make this request. A non-marriage
     * event is reported as not found before any validation runs.
     */
    public function authorize(): Response
    {
        $marriage = $this->route('marriage');

        if (! $marriage instanceof Event || $marriage->type !== EventType::Marriage) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('update', $marriage);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->partialDateRules('marriage');
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'marriage.year' => 'rok ślubu',
            'marriage.month' => 'miesiąc ślubu',
            'marriage.day' => 'dzień ślubu',
            'marriage.locality_id' => 'miejsce ślubu',
            'marriage.is_approximate' => 'przybliżona data ślubu',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateFullDate($validator, 'marriage');
        });
    }
}
