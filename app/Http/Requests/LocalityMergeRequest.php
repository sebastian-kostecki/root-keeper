<?php

namespace App\Http\Requests;

use App\Models\Locality;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LocalityMergeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Runs before the rules, so a foreign locality is a 404 even with an empty body.
     */
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('locality'));
    }

    /**
     * Get the validation rules that apply to the request: the target must be another locality of the user's tree.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Locality $locality */
        $locality = $this->route('locality');

        return [
            'target_id' => [
                'required',
                'integer',
                Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id),
                Rule::notIn([$locality->id]),
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
            'target_id.exists' => __('Wybierz miejscowość ze spisu.'),
            'target_id.not_in' => __('Wybierz inną miejscowość niż scalana.'),
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
            'target_id' => 'miejscowość docelowa',
        ];
    }
}
