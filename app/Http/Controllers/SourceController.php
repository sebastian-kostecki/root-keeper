<?php

namespace App\Http\Controllers;

use App\Actions\Sources\DeleteSource;
use App\Actions\Sources\StoreSource;
use App\Actions\Sources\UpdateSource;
use App\Actions\Trees\ListPersonCandidates;
use App\Enums\Religion;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Http\Requests\SourceStoreRequest;
use App\Http\Requests\SourceUpdateRequest;
use App\Http\Resources\ParishResource;
use App\Http\Resources\SourceResource;
use App\Models\Event;
use App\Models\EventSource;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SourceController extends Controller
{
    /**
     * Display a listing of the tree's sources.
     */
    public function index(Request $request, ListPersonCandidates $listPersonCandidates): Response
    {
        $query = $this->normalizeSearchQuery($request->query('q'));
        $kind = $this->normalizeKind($request->query('kind'));
        $view = $this->normalizeView($request->query('view'));
        $sort = $view === 'tabela' ? $this->normalizeSort($request->query('sort')) : '-dodano';
        $tree = $request->user()->tree;

        return Inertia::render('sources/index', [
            'sources' => fn (): array => SourceResource::collection(
                $tree->sources()->with(['parish.locality', 'cemetery'])->withCount('events')->latest()->orderByDesc('id')->get()
            )->resolve(),
            'personCandidates' => Inertia::optional(fn (): array => $listPersonCandidates->handle($tree)),
            'filters' => ['q' => $query, 'kind' => $kind, 'view' => $view, 'sort' => $sort],
            'kinds' => SourceKind::options(),
            'recordTypes' => SourceRecordType::options(),
            'sourcePanel' => function () use ($request): ?array {
                $key = $request->query('source');

                if ($key === 'new') {
                    return ['source' => null, 'confirms' => [], ...$this->panelOptions($request)];
                }

                if (! is_string($key) || ! ctype_digit($key)) {
                    return null;
                }

                $source = $request->user()->tree->sources()->findOrFail((int) $key);

                Gate::authorize('view', $source);

                $source->load(['parish.locality', 'cemetery', 'buriedPeople', 'events' => fn ($query) => $query->withCount('sources')->with('participants')]);

                return [
                    'source' => SourceResource::make($source)->resolve(),
                    'confirms' => $this->confirms($source),
                    ...$this->panelOptions($request),
                ];
            },
        ]);
    }

    /**
     * The suggestion lists the source panel needs: the tree's parishes, localities and archives, and the religions.
     *
     * @return array{parishes: array<int, array<string, mixed>>, localities: array<int, array{id: int, name: string}>, archives: array<int, string>, religions: array<int, array{value: string, label: string}>}
     */
    private function panelOptions(Request $request): array
    {
        $tree = $request->user()->tree;

        return [
            'parishes' => ParishResource::collection($tree->parishes()->with('locality')->orderBy('name')->get())->resolve(),
            'localities' => $tree->localities()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Locality $locality): array => ['id' => $locality->id, 'name' => $locality->name])
                ->all(),
            'archives' => $tree->sources()
                ->whereNotNull('archive')
                ->whereRaw("btrim(archive) <> ''")
                ->distinct()
                ->orderBy('archive')
                ->pluck('archive')
                ->all(),
            'religions' => Religion::options(),
        ];
    }

    /**
     * The list filters carried by the previous page, when it was the sources list; defaults and blanks are omitted.
     *
     * Reads the Referer only: url()->previous() falls back to an address stored in the session.
     *
     * @return array<string, string>
     */
    private function listQuery(Request $request): array
    {
        $previous = parse_url((string) $request->headers->get('referer'));
        $listPath = parse_url(route('sources.index'), PHP_URL_PATH);

        if (
            ! is_array($previous)
            || ($previous['path'] ?? null) !== $listPath
            || (isset($previous['host']) && $previous['host'] !== $request->getHost())
        ) {
            return [];
        }

        parse_str($previous['query'] ?? '', $params);

        $query = [];
        $q = $this->normalizeSearchQuery($params['q'] ?? null);
        $kind = $this->normalizeKind($params['kind'] ?? null);

        if ($q !== '') {
            $query['q'] = $q;
        }

        if ($kind !== 'wszystkie') {
            $query['kind'] = $kind;
        }

        if ($this->normalizeView($params['view'] ?? null) === 'tabela') {
            $query['view'] = 'tabela';
            $sort = $this->normalizeSort($params['sort'] ?? null);

            if ($sort !== '-dodano') {
                $query['sort'] = $sort;
            }
        }

        return $query;
    }

    /**
     * The event-source link an event carries when it was loaded through `Source::events()`.
     */
    private function pivotOf(Event $event): EventSource
    {
        /** @var EventSource $pivot */
        $pivot = $event->getRelation('pivot');

        return $pivot;
    }

    /**
     * The events the source confirms, one row per participant.
     *
     * @return array<int, array{event_id: int, event_type: string, event_label: string, is_last_source: bool, entry_number: string|null, person: array{id: int, display_name: string}}>
     */
    private function confirms(Source $source): array
    {
        return $source->events->flatMap(
            fn (Event $event): Collection => $event->participants->map(fn (Person $person): array => [
                'event_id' => $event->id,
                'event_type' => $event->type->value,
                'event_label' => $event->type->label(),
                'is_last_source' => $event->sources_count === 1,
                'entry_number' => $this->pivotOf($event)->entry_number,
                'person' => [
                    'id' => $person->id,
                    'display_name' => $person->displayName(),
                ],
            ])
        )->values()->all();
    }

    /**
     * Normalize the raw `q` query parameter into a trimmed, length-capped search string.
     */
    private function normalizeSearchQuery(mixed $q): string
    {
        if (! is_string($q)) {
            return '';
        }

        return rtrim(mb_substr(trim($q), 0, 100));
    }

    /**
     * The list filters without `kind` and `q` when they would hide the given source, so its open card stays on the list.
     *
     * Matches the list's own filter (`filterSources` in resources/js/lib/sources-index.ts): every search word is in the display name.
     *
     * @param  array<string, string>  $query
     * @return array<string, string>
     */
    private function filtersShowing(Source $source, array $query): array
    {
        $kindFilter = match ($source->kind) {
            SourceKind::MetricalRecord => 'akty',
            SourceKind::Gravestone => 'nagrobki',
            SourceKind::Other => 'inne',
        };

        if (isset($query['kind']) && $query['kind'] !== $kindFilter) {
            unset($query['kind']);
        }

        $name = mb_strtolower($source->displayName());
        $words = preg_split('/\s+/u', mb_strtolower($query['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! collect($words)->every(fn (string $word): bool => str_contains($name, $word))) {
            unset($query['q']);
        }

        return $query;
    }

    /**
     * Normalize the raw `kind` query parameter: one of the three kind filters or the default `wszystkie`.
     */
    private function normalizeKind(mixed $kind): string
    {
        return is_string($kind) && in_array($kind, ['akty', 'nagrobki', 'inne'], true) ? $kind : 'wszystkie';
    }

    /**
     * Normalize the raw `view` query parameter: `tabela` or the default `galeria`.
     */
    private function normalizeView(mixed $view): string
    {
        return $view === 'tabela' ? 'tabela' : 'galeria';
    }

    /**
     * Normalize the raw `sort` query parameter: one of the ten sort keys or the default `-dodano`.
     */
    private function normalizeSort(mixed $sort): string
    {
        $allowed = [
            'nazwa', '-nazwa', 'rodzaj', '-rodzaj', 'rok', '-rok',
            'potwierdza', '-potwierdza', 'dodano', '-dodano',
        ];

        return is_string($sort) && in_array($sort, $allowed, true) ? $sort : '-dodano';
    }

    /**
     * Store a newly uploaded source in the tree.
     */
    public function store(SourceStoreRequest $request, StoreSource $storeSource): RedirectResponse
    {
        $source = $storeSource->handle(
            $request->user()->tree,
            $request->file('file'),
            SourceKind::from($request->validated('kind')),
            $request->safe()->except(['kind', 'file']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano źródło')]);

        return to_route('sources.index', [...$this->filtersShowing($source, $this->listQuery($request)), 'source' => $source->id]);
    }

    /**
     * Redirect to the source's panel on the list.
     */
    public function show(Source $source): RedirectResponse
    {
        Gate::authorize('view', $source);

        return to_route('sources.index', ['source' => $source->id]);
    }

    /**
     * Update the kind and description of the given source.
     */
    public function update(SourceUpdateRequest $request, Source $source, UpdateSource $updateSource): RedirectResponse
    {
        $updateSource->handle(
            $source,
            SourceKind::from($request->validated('kind')),
            $request->safe()->except(['kind', 'discard_record_fields']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano źródło')]);

        return back();
    }

    /**
     * Remove the given source.
     */
    public function destroy(Request $request, Source $source, DeleteSource $deleteSource): RedirectResponse
    {
        Gate::authorize('delete', $source);

        $deleteSource->handle($source);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto źródło')]);

        return to_route('sources.index', $this->listQuery($request));
    }

    /**
     * Serve the original source file.
     */
    public function file(Source $source): StreamedResponse
    {
        Gate::authorize('view', $source);

        if (! Storage::disk('local')->exists($source->path)) {
            abort(404);
        }

        $asciiFallback = str_replace('%', '', Str::ascii($source->original_name));
        $downloadName = $asciiFallback !== '' ? $source->original_name : "zrodlo-{$source->id}.".pathinfo($source->path, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($source->path, $downloadName, $this->fileHeaders($source->mime_type));
    }

    /**
     * Serve the source's thumbnail.
     */
    public function thumbnail(Source $source): StreamedResponse
    {
        Gate::authorize('view', $source);

        if ($source->thumbnail_path === null || ! Storage::disk('local')->exists($source->thumbnail_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($source->thumbnail_path, null, $this->fileHeaders('image/jpeg'));
    }

    /**
     * @return array<string, string>
     */
    private function fileHeaders(string $mimeType): array
    {
        return [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
