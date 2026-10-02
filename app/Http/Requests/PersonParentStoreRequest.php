<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PersonParentStoreRequest extends PersonStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('person'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'role' => ['required', Rule::in(['father', 'mother', 'parent'])],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $expected = match ($this->input('role')) {
                'father' => Gender::Male->value,
                'mother' => Gender::Female->value,
                default => null,
            };

            if ($expected !== null && ! $validator->errors()->has('gender') && $this->input('gender') !== $expected) {
                $validator->errors()->add('gender', 'Płeć musi pasować do miejsca na drzewie.');
            }
        });
    }
}
