<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class TreeProbandStoreRequest extends PersonStoreRequest
{
    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->user()->tree->root_person_id !== null) {
                $validator->errors()->add('proband', 'Drzewo ma już probanta.');
            }
        });
    }
}
