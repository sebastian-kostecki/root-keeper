<?php

namespace App\Actions\Import;

use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Support\Import\Frontmatter;
use App\Support\Import\ImportIssue;
use App\Support\Import\ParsedSourceBlock;
use App\Support\Import\ResearchParish;
use App\Support\Import\ResearchSource;
use Closure;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ParseSourceBlock
{
    private const SUPPORTED_VERSION = 1;

    private const ID_PATTERN = '/^ZR-\d{4,}$/';

    private const MAX_SCAN_BYTES = 20 * 1024 * 1024;

    private const ROOT_KEYS = ['wersja', 'skany'];

    private const ENTRY_KEYS = ['id', 'plik', 'rodzaj', 'typ', 'nazwa', 'rok', 'parafia', 'archiwum', 'sygnatura', 'link', 'notatka'];

    private const PARISH_KEYS = ['miejscowosc', 'wyznanie', 'rodzaj', 'wezwanie'];

    private const TEXT_FIELDS = ['nazwa', 'archiwum', 'sygnatura', 'notatka'];

    private const RELIGIONS = [
        'rzymskokatolickie' => Religion::RomanCatholic,
        'grekokatolickie' => Religion::GreekCatholic,
        'ewangelickie' => Religion::Evangelical,
        'prawoslawne' => Religion::Orthodox,
        'mojzeszowe' => Religion::Jewish,
        'inne' => Religion::Other,
    ];

    private const PARISH_KINDS = [
        'parafia' => ParishKind::Parish,
        'usc' => ParishKind::CivilRegistry,
        'okreg_bozniczy' => ParishKind::JewishDistrict,
        'inny' => ParishKind::Other,
    ];

    private const RECORD_TYPES = [
        'urodzenie' => SourceRecordType::Birth,
        'slub' => SourceRecordType::Marriage,
        'zgon' => SourceRecordType::Death,
        'inne' => SourceRecordType::Other,
    ];

    /**
     * @var array<string, list<string>> the extensions each image type of the scan may carry
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    /**
     * Read the block of a file under zrodla/ and validate each entry on its own. The scan of an entry is untrusted
     * input: it has to be a regular image file that lies inside the repo, and it is only read, never changed.
     *
     * @param  string  $repoRoot  the root of the research repo
     * @param  string  $file  the path of the file, relative to the repo root; it names the file in issues
     */
    public function handle(string $repoRoot, string $file, string $contents): ParsedSourceBlock
    {
        $contents = Frontmatter::normalize($contents);

        if (! Frontmatter::starts($contents)) {
            return ParsedSourceBlock::withoutBlock($file);
        }

        $yaml = Frontmatter::extract($contents);

        if ($yaml === null) {
            return ParsedSourceBlock::skippedFile($file, 'Niezamknięty frontmatter: brak zamykającej linii "---"');
        }

        try {
            $block = Yaml::parse($yaml, Yaml::PARSE_DATETIME);
        } catch (ParseException $exception) {
            return ParsedSourceBlock::skippedFile(
                $file,
                sprintf('Błąd składni YAML w linii %d (%s)', max($exception->getParsedLine(), 0) + 1, $exception->getMessage()),
            );
        }

        if (! is_array($block) || ($block !== [] && array_is_list($block))) {
            return ParsedSourceBlock::skippedFile($file, 'Blok nie jest mapą klucz: wartość');
        }

        $validator = Validator::make($block, [
            'wersja' => ['required', 'integer', 'in:'.self::SUPPORTED_VERSION],
            'skany' => ['present', 'array', $this->mustBeList()],
        ], [
            'required' => 'Brak pola :attribute',
            'present' => 'Brak pola :attribute',
            'integer' => 'Pole :attribute musi być liczbą',
            'array' => 'Pole :attribute musi być listą',
            'wersja.in' => 'Obsługiwana jest tylko wersja bloku 1',
        ]);

        if ($validator->fails()) {
            return ParsedSourceBlock::skippedFile($file, implode('; ', $validator->errors()->all()));
        }

        $root = realpath($repoRoot) ?: rtrim($repoRoot, '/');
        $sources = [];
        $skipped = [];
        $warnings = array_map(
            fn (string $key): ImportIssue => new ImportIssue($file, "Nieznany klucz: {$key}"),
            array_values(array_diff(array_map(strval(...), array_keys($block)), self::ROOT_KEYS)),
        );

        foreach ($block['skany'] as $index => $entry) {
            if (! is_array($entry) || ($entry !== [] && array_is_list($entry))) {
                $skipped[] = new ImportIssue($file, sprintf('Wpis nr %d na liście skany nie jest mapą klucz: wartość', $index + 1));

                continue;
            }

            $result = $this->parseEntry($root, $file, $entry);

            if ($result['source'] !== null) {
                $sources[] = $result['source'];
            } else {
                $skipped[] = new ImportIssue($file, (string) $result['reason'], $this->validId($entry['id'] ?? null));
            }

            array_push($warnings, ...$result['warnings']);
        }

        return ParsedSourceBlock::parsed($file, $sources, $skipped, $warnings, count($block['skany']));
    }

    /**
     * @param  array<mixed>  $entry
     * @return array{source: ResearchSource|null, reason: string|null, warnings: list<ImportIssue>}
     */
    private function parseEntry(string $root, string $file, array $entry): array
    {
        $entry = $this->normalize($entry);
        $validator = Validator::make($entry, $this->rules(), $this->messages());

        if ($validator->fails()) {
            return ['source' => null, 'reason' => implode('; ', $validator->errors()->all()), 'warnings' => []];
        }

        $id = (string) $entry['id'];

        if ($entry['rodzaj'] === 'nagrobek') {
            return ['source' => null, 'reason' => 'nagrobki: osobna zmiana', 'warnings' => []];
        }

        $scan = $this->resolveScan($root, $this->text($entry['plik'] ?? null));

        if (is_string($scan)) {
            return ['source' => null, 'reason' => $scan, 'warnings' => []];
        }

        $warnings = $this->entryWarnings($file, $id, $entry);
        $isRecord = $entry['rodzaj'] === 'akt';
        $type = $isRecord ? $this->text($entry['typ'] ?? null) : null;

        return [
            'source' => new ResearchSource(
                id: $id,
                file: $file,
                scanPath: $scan['path'],
                scanAbsolutePath: $scan['absolute'],
                scanHash: $scan['hash'],
                kind: $isRecord ? SourceKind::MetricalRecord : SourceKind::Other,
                recordType: $type === null ? null : self::RECORD_TYPES[$type],
                parish: $isRecord ? $this->buildParish($entry['parafia'] ?? null) : null,
                name: $this->text($entry['nazwa'] ?? null),
                year: isset($entry['rok']) ? (int) $entry['rok'] : null,
                archive: $this->text($entry['archiwum'] ?? null),
                signature: $this->text($entry['sygnatura'] ?? null),
                url: $this->text($entry['link'] ?? null),
                note: $this->text($entry['notatka'] ?? null),
            ),
            'reason' => null,
            'warnings' => $warnings,
        ];
    }

    /**
     * Numbers written as text fields (an archive signature such as `123`) read as text.
     *
     * @param  array<mixed>  $entry
     * @return array<mixed>
     */
    private function normalize(array $entry): array
    {
        foreach (self::TEXT_FIELDS as $field) {
            if (is_int($entry[$field] ?? null) || is_float($entry[$field] ?? null)) {
                $entry[$field] = (string) $entry[$field];
            }
        }

        if (is_array($entry['parafia'] ?? null)) {
            foreach (['miejscowosc', 'wezwanie'] as $field) {
                if (is_int($entry['parafia'][$field] ?? null) || is_float($entry['parafia'][$field] ?? null)) {
                    $entry['parafia'][$field] = (string) $entry['parafia'][$field];
                }
            }
        }

        return $entry;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:'.self::ID_PATTERN],
            'plik' => ['nullable', 'string'],
            'rodzaj' => ['required', 'in:akt,inne,nagrobek'],
            'typ' => ['nullable', 'in:'.implode(',', array_keys(self::RECORD_TYPES))],
            'nazwa' => ['nullable', 'string'],
            'rok' => ['nullable', 'integer', 'min:1000', 'max:'.date('Y')],
            'parafia' => ['nullable', 'array'],
            'parafia.miejscowosc' => ['required_with:parafia', 'string'],
            'parafia.wyznanie' => ['nullable', 'in:'.implode(',', array_keys(self::RELIGIONS))],
            'parafia.rodzaj' => ['nullable', 'in:'.implode(',', array_keys(self::PARISH_KINDS))],
            'parafia.wezwanie' => ['nullable', 'string'],
            'archiwum' => ['nullable', 'string'],
            'sygnatura' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'url:http,https'],
            'notatka' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => 'Brak pola :attribute',
            'required_with' => 'Brak pola :attribute',
            'string' => 'Pole :attribute musi być tekstem',
            'integer' => 'Pole :attribute musi być liczbą',
            'array' => 'Pole :attribute musi być zagnieżdżoną strukturą',
            'in' => 'Pole :attribute ma niedozwoloną wartość',
            'regex' => 'Pole :attribute ma niewłaściwy format',
            'id.regex' => 'Pole id musi mieć postać ZR-NNNN (np. ZR-0012)',
            'rodzaj.in' => 'Pole rodzaj musi mieć wartość akt albo inne',
            'typ.in' => 'Pole typ musi mieć wartość urodzenie, slub, zgon albo inne',
            'parafia.wyznanie.in' => 'Pole parafia.wyznanie musi mieć wartość '.implode(', ', array_keys(self::RELIGIONS)),
            'parafia.rodzaj.in' => 'Pole parafia.rodzaj musi mieć wartość '.implode(', ', array_keys(self::PARISH_KINDS)),
            'rok.min' => 'Pole rok musi być w przedziale 1000…'.date('Y'),
            'rok.max' => 'Pole rok musi być w przedziale 1000…'.date('Y'),
            'link.url' => 'Pole link musi być adresem http(s)',
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

    /**
     * @param  array<mixed>  $entry
     * @return list<ImportIssue>
     */
    private function entryWarnings(string $file, string $id, array $entry): array
    {
        $warnings = [];

        foreach (array_diff(array_map(strval(...), array_keys($entry)), self::ENTRY_KEYS) as $key) {
            $warnings[] = new ImportIssue($file, "Nieznany klucz: {$id}.{$key}", $id);
        }

        if (is_array($entry['parafia'] ?? null)) {
            foreach (array_diff(array_map(strval(...), array_keys($entry['parafia'])), self::PARISH_KEYS) as $key) {
                $warnings[] = new ImportIssue($file, "Nieznany klucz: {$id}.parafia.{$key}", $id);
            }
        }

        if ($entry['rodzaj'] === 'inne') {
            foreach (['typ', 'parafia'] as $field) {
                if (! in_array($entry[$field] ?? null, [null, []], true)) {
                    $warnings[] = new ImportIssue($file, "{$id}: pole {$field} dotyczy tylko aktu (rodzaj: akt), więc je pominięto", $id);
                }
            }
        }

        $archive = $this->text($entry['archiwum'] ?? null);

        if ($archive !== null && preg_match('/,\s*\d/u', $archive) === 1) {
            $warnings[] = new ImportIssue($file, "{$id}: archiwum wygląda na sygnaturę („{$archive}”); sygnaturę wpisz w polu sygnatura", $id);
        }

        return $warnings;
    }

    private function buildParish(mixed $parish): ?ResearchParish
    {
        if (! is_array($parish) || $parish === []) {
            return null;
        }

        $religion = $this->text($parish['wyznanie'] ?? null);
        $kind = $this->text($parish['rodzaj'] ?? null);

        return new ResearchParish(
            locality: (string) $this->text($parish['miejscowosc'] ?? null),
            religion: $religion === null ? null : self::RELIGIONS[$religion],
            kind: $kind === null ? ParishKind::Parish : self::PARISH_KINDS[$kind],
            dedication: $this->text($parish['wezwanie'] ?? null),
        );
    }

    /**
     * Check the scan of an entry and hash it.
     *
     * @return array{path: string, absolute: string, hash: string}|string the scan, or the reason it is skipped
     */
    private function resolveScan(string $root, ?string $path): array|string
    {
        if ($path === null) {
            return 'brak skanu — źródło wejdzie, gdy skan pojawi się w repo';
        }

        $outside = 'plik skanu poza repo';

        if (str_contains($path, "\0") || preg_match('#^([/\\\\]|[A-Za-z]:)#', $path) === 1) {
            return $outside;
        }

        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return $outside;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $relative = implode('/', $segments);
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $missing = "plik skanu nie istnieje: {$path} — skopiuj skan do zrodla/skany/";
        $real = $relative === '' ? false : realpath($root.'/'.$relative);

        if ($real === false) {
            return $this->formatProblem($extension) ?? $missing;
        }

        if (! str_starts_with($real, $root.'/')) {
            return $outside;
        }

        if (! is_file($real)) {
            return $missing;
        }

        $problem = $this->formatProblem($extension);

        if ($problem !== null) {
            return $problem;
        }

        if ((int) filesize($real) > self::MAX_SCAN_BYTES) {
            return 'plik skanu większy niż 20 MB';
        }

        $mime = (string) mime_content_type($real);

        if ($mime === 'application/pdf') {
            return 'PDF nie jest obsługiwany — zapisz skan jako JPG';
        }

        if (! in_array($extension, self::MIME_EXTENSIONS[$mime] ?? [], true)) {
            return 'nieobsługiwany format pliku (tylko jpg, png, webp)';
        }

        $hash = hash_file('sha256', $real);

        if ($hash === false) {
            return "plik skanu nie istnieje: {$path} — skopiuj skan do zrodla/skany/";
        }

        return ['path' => $relative, 'absolute' => $real, 'hash' => $hash];
    }

    /**
     * The reason an extension is not accepted, `null` for jpg, png and webp.
     */
    private function formatProblem(string $extension): ?string
    {
        if ($extension === 'pdf') {
            return 'PDF nie jest obsługiwany — zapisz skan jako JPG';
        }

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? null : 'nieobsługiwany format pliku (tylko jpg, png, webp)';
    }

    private function validId(mixed $id): ?string
    {
        return is_string($id) && preg_match(self::ID_PATTERN, $id) === 1 ? $id : null;
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
