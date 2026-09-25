<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class PersonUpdateRequest extends PersonStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('person'));
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->boolean('is_deceased')) {
                return;
            }

            $person = $this->route('person');

            if (! $person instanceof Person) {
                return;
            }

            $death = $person->death();

            if ($death !== null && $death->scans()->exists()) {
                $validator->errors()->add('is_deceased', __('Najpierw odepnij skany od zgonu na podglądzie osoby.'));
            }
        });
    }
}
