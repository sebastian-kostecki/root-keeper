<?php

namespace App\Http\Requests;

use App\Concerns\CemeteryValidationRules;
use App\Models\Cemetery;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CemeteryUpdateRequest extends FormRequest
{
    use CemeteryValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('cemetery'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Cemetery $cemetery */
        $cemetery = $this->route('cemetery');

        return $this->cemeteryRules($cemetery);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->cemeteryAttributes();
    }
}
