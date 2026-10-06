<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
     * Drop fully empty residence rows, keeping the keys so that error indexes
     * match the row positions the form sent.
     */
    protected function prepareForValidation(): void
    {
        $residences = $this->input('residences');

        if (! is_array($residences)) {
            return;
        }

        $this->merge([
            'residences' => array_filter($residences, fn (mixed $row): bool => ! is_array($row) || collect(['locality_id', 'year_from', 'year_to'])
                ->contains(fn (string $key): bool => ! in_array($row[$key] ?? null, [null, ''], true))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentYear = (int) Date::now()->year;

        return [
            ...parent::rules(),
            'residences' => ['nullable', 'array'],
            'residences.*' => ['array'],
            'residences.*.locality_id' => [
                'required',
                'integer',
                Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id),
            ],
            'residences.*.year_from' => ['nullable', 'integer', "between:1000,{$currentYear}"],
            'residences.*.year_to' => ['nullable', 'integer', "between:1000,{$currentYear}"],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'residences.*.locality_id.required' => 'Wybierz miejscowość.',
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
            ...parent::attributes(),
            'residences.*.locality_id' => 'miejscowość zamieszkania',
            'residences.*.year_from' => 'rok od',
            'residences.*.year_to' => 'rok do',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $residences = $this->input('residences');

            if (! is_array($residences)) {
                return;
            }

            foreach ($residences as $index => $row) {
                $from = is_array($row) ? ($row['year_from'] ?? null) : null;
                $to = is_array($row) ? ($row['year_to'] ?? null) : null;

                if (is_numeric($from) && is_numeric($to) && (int) $from > (int) $to) {
                    $validator->errors()->add("residences.{$index}.year_to", 'Rok „do” nie może być wcześniejszy niż rok „od”.');
                }
            }
        });

        $validator->after(function (Validator $validator): void {
            if ($this->boolean('is_deceased')) {
                return;
            }

            $person = $this->route('person');

            if (! $person instanceof Person) {
                return;
            }

            $death = $person->death();

            if ($death !== null && $death->sources()->exists()) {
                $validator->errors()->add('is_deceased', __('Najpierw odepnij źródła od zgonu na podglądzie osoby.'));
            }
        });
    }
}
