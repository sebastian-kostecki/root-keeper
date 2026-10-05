<?php

namespace App\Http\Requests;

use App\Concerns\LocalityValidationRules;
use App\Models\Locality;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LocalityUpdateRequest extends FormRequest
{
    use LocalityValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('locality'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Locality $locality */
        $locality = $this->route('locality');

        return $this->localityRules($locality);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->localityAttributes();
    }
}
