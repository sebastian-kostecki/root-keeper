<?php

namespace App\Http\Controllers;

use App\Actions\Sources\DeleteSource;
use App\Actions\Sources\StoreSource;
use App\Actions\Sources\UpdateSource;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Http\Requests\SourceStoreRequest;
use App\Http\Requests\SourceUpdateRequest;
use App\Http\Resources\SourceResource;
use App\Models\Event;
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
    public function index(Request $request): Response
    {
        $sources = $request->user()->tree->sources()->latest()->orderByDesc('id')->get();

        return Inertia::render('sources/index', [
            'sources' => SourceResource::collection($sources)->resolve(),
            'kinds' => SourceKind::options(),
            'recordTypes' => SourceRecordType::options(),
        ]);
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

        return to_route('sources.show', $source);
    }

    /**
     * Display the given source.
     */
    public function show(Source $source): Response
    {
        Gate::authorize('view', $source);

        $source->load(['events' => fn ($query) => $query->withCount('sources')->with('participants')]);

        return Inertia::render('sources/show', [
            'source' => SourceResource::make($source)->resolve(),
            'kinds' => SourceKind::options(),
            'recordTypes' => SourceRecordType::options(),
            'confirms' => $source->events->flatMap(
                fn (Event $event): Collection => $event->participants->map(fn (Person $person): array => [
                    'event_id' => $event->id,
                    'event_type' => $event->type->value,
                    'event_label' => $event->type->label(),
                    'is_last_source' => $event->sources_count === 1,
                    'person' => [
                        'id' => $person->id,
                        'display_name' => $person->displayName(),
                    ],
                ])
            )->values()->all(),
        ]);
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
    public function destroy(Source $source, DeleteSource $deleteSource): RedirectResponse
    {
        Gate::authorize('delete', $source);

        $deleteSource->handle($source);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto źródło')]);

        return to_route('sources.index');
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
