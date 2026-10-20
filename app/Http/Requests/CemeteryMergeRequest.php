<?php

namespace App\Http\Requests;

use App\Models\Cemetery;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CemeteryMergeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Runs before the rules, so a foreign cemetery is a 404 even with an empty body.
     */
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('cemetery'));
    }

    /**
     * Get the validation rules that apply to the request: the target must be another cemetery of the user's tree.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Cemetery $cemetery */
        $cemetery = $this->route('cemetery');

        return [
            'target_id' => [
                'required',
                'integer',
                Rule::exists('cemeteries', 'id')->where('tree_id', $this->user()->tree->id),
                Rule::notIn([$cemetery->id]),
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
            'target_id.exists' => __('Wybierz cmentarz ze spisu.'),
            'target_id.not_in' => __('Wybierz inny cmentarz niż scalany.'),
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
            'target_id' => 'cmentarz docelowy',
        ];
    }
}
