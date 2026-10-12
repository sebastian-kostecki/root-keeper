<?php

namespace App\Http\Requests;

use App\Models\Parish;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ParishMergeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Runs before the rules, so a foreign parish is a 404 even with an empty body.
     */
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('parish'));
    }

    /**
     * Get the validation rules that apply to the request: the target must be another parish of the user's tree.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Parish $parish */
        $parish = $this->route('parish');

        return [
            'target_id' => [
                'required',
                'integer',
                Rule::exists('parishes', 'id')->where('tree_id', $this->user()->tree->id),
                Rule::notIn([$parish->id]),
            ],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_id.exists' => __('Wybierz parafię ze spisu.'),
            'target_id.not_in' => __('Wybierz inną parafię niż scalana.'),
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
            'target_id' => 'parafia docelowa',
        ];
    }
}
