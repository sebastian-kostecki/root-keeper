<?php

namespace App\Http\Controllers;

use App\Actions\Scans\StoreScan;
use App\Http\Requests\ScanStoreRequest;
use App\Http\Resources\ScanResource;
use App\Models\Scan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScanController extends Controller
{
    /**
     * Display a listing of the tree's scans.
     */
    public function index(Request $request): Response
    {
        $scans = $request->user()->tree->scans()->latest()->orderByDesc('id')->get();

        return Inertia::render('scans/index', [
            'scans' => ScanResource::collection($scans)->resolve(),
        ]);
    }

    /**
     * Show the form for uploading a new scan.
     */
    public function create(): Response
    {
        return Inertia::render('scans/create');
    }

    /**
     * Store a newly uploaded scan in the tree.
     */
    public function store(ScanStoreRequest $request, StoreScan $storeScan): RedirectResponse
    {
        $scan = $storeScan->handle($request->user()->tree, $request->file('scan'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano skan')]);

        return to_route('scans.show', $scan);
    }

    /**
     * Display the given scan.
     */
    public function show(Scan $scan): Response
    {
        Gate::authorize('view', $scan);

        return Inertia::render('scans/show', [
            'scan' => ScanResource::make($scan)->resolve(),
        ]);
    }

    /**
     * Serve the original scan file.
     */
    public function file(Scan $scan): StreamedResponse
    {
        Gate::authorize('view', $scan);

        if (! Storage::disk('local')->exists($scan->path)) {
            abort(404);
        }

        $asciiFallback = str_replace('%', '', Str::ascii($scan->original_name));
        $downloadName = $asciiFallback !== '' ? $scan->original_name : "skan-{$scan->id}.".pathinfo($scan->path, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($scan->path, $downloadName, $this->fileHeaders($scan->mime_type));
    }

    /**
     * Serve the scan's thumbnail.
     */
    public function thumbnail(Scan $scan): StreamedResponse
    {
        Gate::authorize('view', $scan);

        if ($scan->thumbnail_path === null || ! Storage::disk('local')->exists($scan->thumbnail_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($scan->thumbnail_path, null, $this->fileHeaders('image/jpeg'));
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
