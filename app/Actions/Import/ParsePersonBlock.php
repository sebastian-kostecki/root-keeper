<?php

namespace App\Actions\Import;

use App\Enums\ConfidenceLevel;
use App\Enums\Gender;
use App\Support\Import\ImportIssue;
use App\Support\Import\ParsedPersonBlock;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchMarriage;
use App\Support\Import\ResearchPerson;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ParsePersonBlock
{
    private const SUPPORTED_VERSION = 1;

    private const ID_PATTERN = '/^O-\d{4,}$/';

    private const EVENT_KEYS = ['data', 'miejsce', 'poziom', 'notatka'];

    private const ROOT_KEYS = [
        'wersja', 'id', 'ahnentafel', 'imiona', 'nazwisko', 'warianty_nazwiska', 'plec',
        'rodzice', 'urodzenie', 'zgon', 'pochowek', 'malzenstwa',
    ];

    private const EVENT_FIELDS = ['urodzenie', 'zgon', 'pochowek'];

    public function handle(string $file, string $contents): ParsedPersonBlock
    {
        $contents = str_replace("\r\n", "\n", preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents);

        if (preg_match('/\A---[ \t]*\n/', $contents) !== 1) {
            return ParsedPersonBlock::withoutBlock($file, $this->misplacedBlockWarnings($file, $contents));
        }

        $yaml = $this->extractFrontmatter($contents);

        if ($yaml === null) {
            return ParsedPersonBlock::skipped($file, 'Niezamknięty frontmatter: brak zamykającej linii "---"');
        }

        try {
            $block = Yaml::parse($yaml, Yaml::PARSE_DATETIME);
        } catch (ParseException $exception) {
            return ParsedPersonBlock::skipped(
                $file,
                sprintf('Błąd składni YAML w linii %d (%s)', max($exception->getParsedLine(), 0) + 1, $exception->getMessage()),
            );
        }

        if (! is_array($block) || ($block !== [] && array_is_list($block))) {
            return ParsedPersonBlock::skipped($file, 'Blok nie jest mapą klucz: wartość');
        }

        $dateObjectPaths = [];
        $block = $this->normalizeDates($block, $dateObjectPaths);

        if ($dateObjectPaths !== []) {
            return ParsedPersonBlock::skipped($file, sprintf(
                'Pole %s: ujmij datę w cudzysłów: "RRRR-MM-DD"',
                implode(', ', $dateObjectPaths),
            ));
        }

        $validator = Validator::make($block, $this->rules((string) ($block['id'] ?? '')), $this->messages());

        if ($validator->fails()) {
            return ParsedPersonBlock::skipped($file, implode('; ', $validator->errors()->all()));
        }

        $warnings = array_map(
            fn (string $key): ImportIssue => new ImportIssue($file, "Nieznany klucz: {$key}"),
            $this->unknownKeys($block),
        );

        return ParsedPersonBlock::person($this->buildPerson($file, $block), $warnings);
    }

    private function extractFrontmatter(string $contents): ?string
    {
        $body = preg_replace('/\A---[ \t]*\n/', '', $contents);

        if (preg_match('/^---[ \t]*$/m', (string) $body, $closing, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return substr((string) $body, 0, $closing[0][1]);
    }

    /**
     * @return list<ImportIssue>
     */
    private function misplacedBlockWarnings(string $file, string $contents): array
    {
        if (preg_match('/^(id: O-\d+|wersja:)/m', $contents) !== 1) {
            return [];
        }

        return [new ImportIssue($file, 'Blok nie zaczyna się w pierwszej linii pliku, więc plik liczy się jako bez bloku')];
    }

    /**
     * Turn a bare year into text and collect dates that YAML parsed into date objects.
     *
     * @param  array<mixed>  $block
     * @param  list<string>  $dateObjectPaths
     * @return array<mixed>
     */
    private function normalizeDates(array $block, array &$dateObjectPaths): array
    {
        foreach (self::EVENT_FIELDS as $field) {
            if (is_array($block[$field] ?? null)) {
                $block[$field] = $this->normalizeEventDate($block[$field], $field, $dateObjectPaths);
            }
        }

        if (is_array($block['malzenstwa'] ?? null)) {
            foreach ($block['malzenstwa'] as $index => $marriage) {
                if (is_array($marriage)) {
                    $block['malzenstwa'][$index] = $this->normalizeEventDate($marriage, "malzenstwa.{$index}", $dateObjectPaths);
                }
            }
        }

        return $block;
    }

    /**
     * @param  array<mixed>  $event
     * @param  list<string>  $dateObjectPaths
     * @return array<mixed>
     */
    private function normalizeEventDate(array $event, string $path, array &$dateObjectPaths): array
    {
        foreach (self::EVENT_KEYS as $key) {
            if (($event[$key] ?? null) instanceof DateTimeInterface) {
                $dateObjectPaths[] = "{$path}.{$key}";
            }
        }

        if (is_int($event['data'] ?? null)) {
            $event['data'] = (string) $event['data'];
        }

        return $event;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(string $ownId): array
    {
        $rules = [
            'wersja' => ['required', 'integer', 'in:'.self::SUPPORTED_VERSION],
            'id' => ['required', 'string', 'regex:'.self::ID_PATTERN],
            'ahnentafel' => ['nullable', 'array', $this->mustBeList()],
            'ahnentafel.*' => ['integer', 'min:1'],
            'imiona' => ['nullable', 'string', 'required_without:nazwisko'],
            'nazwisko' => ['nullable', 'string'],
            'warianty_nazwiska' => ['nullable', 'array', $this->mustBeList()],
            'warianty_nazwiska.*' => ['string'],
            'plec' => ['nullable', 'in:M,K'],
            'rodzice' => ['nullable', 'array'],
            'rodzice.ojciec' => ['nullable', 'string', 'regex:'.self::ID_PATTERN, $this->mustNotBe($ownId)],
            'rodzice.matka' => ['nullable', 'string', 'regex:'.self::ID_PATTERN, $this->mustNotBe($ownId)],
            'malzenstwa' => ['nullable', 'array', $this->mustBeList()],
            'malzenstwa.*' => ['array'],
            'malzenstwa.*.z' => ['required', 'string', 'regex:'.self::ID_PATTERN, $this->mustNotBe($ownId)],
        ];

        foreach (['urodzenie', 'zgon', 'pochowek', 'malzenstwa.*'] as $path) {
            if ($path !== 'malzenstwa.*') {
                $rules[$path] = ['nullable', 'array'];
            }

            $rules["{$path}.data"] = ['nullable', 'string', $this->mustBeResearchDate()];
            $rules["{$path}.miejsce"] = ['nullable', 'string'];
            $rules["{$path}.poziom"] = ['nullable', 'in:A,B,C,D'];
            $rules["{$path}.notatka"] = ['nullable', 'string'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => 'Brak pola :attribute',
            'required_without' => 'Brak imion i nazwiska (wymagane jest co najmniej jedno)',
            'string' => 'Pole :attribute musi być tekstem',
            'integer' => 'Pole :attribute musi być liczbą',
            'array' => 'Pole :attribute musi być zagnieżdżoną strukturą',
            'min' => 'Pole :attribute musi być liczbą dodatnią',
            'in' => 'Pole :attribute ma niedozwoloną wartość',
            'regex' => 'Pole :attribute ma niewłaściwy format',
            'wersja.in' => 'Obsługiwana jest tylko wersja bloku 1',
            'id.regex' => 'Pole id musi mieć postać O-NNNN (np. O-0042)',
            'rodzice.ojciec.regex' => 'Pole rodzice.ojciec musi mieć postać O-NNNN',
            'rodzice.matka.regex' => 'Pole rodzice.matka musi mieć postać O-NNNN',
            'malzenstwa.*.z.regex' => 'Pole :attribute musi mieć postać O-NNNN',
            'plec.in' => 'Pole plec musi mieć wartość M albo K',
            '*.poziom.in' => 'Pole :attribute musi mieć wartość A, B, C albo D',
            '*.*.poziom.in' => 'Pole :attribute musi mieć wartość A, B, C albo D',
        ];
    }

    private function mustBeList(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_array($value) && ! array_is_list($value)) {
                $fail("Pole {$attribute} musi być listą");
            }
        };
    }

    private function mustNotBe(string $ownId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ownId): void {
            if ($value === $ownId) {
                $fail("Pole {$attribute} wskazuje na tę samą osobę ({$ownId})");
            }
        };
    }

    private function mustBeResearchDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ResearchDate::parse($value) === null) {
                $fail("Pole {$attribute}: „{$value}” jest poza gramatyką dat albo niemożliwe");
            }
        };
    }

    /**
     * @param  array<mixed>  $block
     * @return list<string>
     */
    private function unknownKeys(array $block): array
    {
        $unknown = [];
        $collect = function (array $data, array $allowed, string $prefix) use (&$unknown): void {
            foreach (array_keys($data) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $unknown[] = $prefix.$key;
                }
            }
        };

        $collect($block, self::ROOT_KEYS, '');

        if (is_array($block['rodzice'] ?? null)) {
            $collect($block['rodzice'], ['ojciec', 'matka'], 'rodzice.');
        }

        foreach (self::EVENT_FIELDS as $field) {
            if (is_array($block[$field] ?? null)) {
                $collect($block[$field], self::EVENT_KEYS, "{$field}.");
            }
        }

        foreach ($block['malzenstwa'] ?? [] as $index => $marriage) {
            $collect($marriage, ['z', ...self::EVENT_KEYS], "malzenstwa.{$index}.");
        }

        return $unknown;
    }

    /**
     * @param  array<mixed>  $block
     */
    private function buildPerson(string $file, array $block): ResearchPerson
    {
        $id = (string) $block['id'];

        $marriages = [];

        foreach ($block['malzenstwa'] ?? [] as $marriage) {
            $marriages[] = new ResearchMarriage(
                ResearchMarriage::pair($id, (string) $marriage['z']),
                $this->buildEvent($marriage),
                [$file],
            );
        }

        return new ResearchPerson(
            id: $id,
            file: $file,
            ahnentafel: array_values(array_map(intval(...), $block['ahnentafel'] ?? [])),
            givenNames: $this->text($block['imiona'] ?? null),
            surname: $this->text($block['nazwisko'] ?? null),
            surnameVariants: array_values(array_filter(array_map($this->text(...), $block['warianty_nazwiska'] ?? []))),
            gender: match ($block['plec'] ?? null) {
                'M' => Gender::Male,
                'K' => Gender::Female,
                default => null,
            },
            fatherId: $this->text($block['rodzice']['ojciec'] ?? null),
            motherId: $this->text($block['rodzice']['matka'] ?? null),
            birth: $this->buildOptionalEvent($block, 'urodzenie'),
            death: $this->buildOptionalEvent($block, 'zgon'),
            burial: $this->buildOptionalEvent($block, 'pochowek'),
            marriages: $marriages,
        );
    }

    /**
     * An event key that is present (even empty) means the event took place.
     *
     * @param  array<mixed>  $block
     */
    private function buildOptionalEvent(array $block, string $key): ?ResearchEvent
    {
        if (! array_key_exists($key, $block)) {
            return null;
        }

        return $this->buildEvent($block[$key] ?? []);
    }

    /**
     * @param  array<mixed>  $event
     */
    private function buildEvent(array $event): ResearchEvent
    {
        $date = $this->text($event['data'] ?? null);
        $level = $this->text($event['poziom'] ?? null);

        return new ResearchEvent(
            date: $date === null ? null : ResearchDate::parse($date),
            place: $this->text($event['miejsce'] ?? null),
            level: $level === null ? null : ConfidenceLevel::from($level),
            note: $this->text($event['notatka'] ?? null),
        );
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }
}
