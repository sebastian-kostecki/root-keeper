<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class PersonPhotoStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Runs before the rules, so a foreign person is a 404 even with an empty body.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('person'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(20 * 1024)],
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
            'photo' => 'zdjęcie',
        ];
    }
}
