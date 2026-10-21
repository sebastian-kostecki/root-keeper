<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonPanelRedirect
{
    /**
     * Query keys that open a side panel. Mirrors the registry in
     * resources/js/lib/side-panel-url.ts (PANEL_PROPS plus the client-only `add` key).
     *
     * @var list<string>
     */
    private const PANEL_KEYS = ['person', 'marriage', 'add', 'source', 'locality', 'parish', 'cemetery', 'demo'];

    /**
     * Redirect back to the referring page with the given person's panel open.
     *
     * Keeps the path and the other query parameters of the Referer (e.g. `spouse`) and
     * drops every panel key. Reads the Referer only: url()->previous() falls back to an
     * address stored in the session. Without a usable Referer (missing, external host or
     * a protocol-relative path) the fallback URL is used with the same panel key.
     */
    public static function to(Request $request, Person $person, string $fallbackUrl): RedirectResponse
    {
        $parts = parse_url((string) $request->headers->get('referer'));

        if (
            ! is_array($parts)
            || ! isset($parts['path'])
            || ! preg_match('#^/(?!/)#', $parts['path'])
            || (isset($parts['host']) && $parts['host'] !== $request->getHost())
        ) {
            return redirect()->to($fallbackUrl.(str_contains($fallbackUrl, '?') ? '&' : '?').'person='.$person->id);
        }

        parse_str($parts['query'] ?? '', $query);
        $query = array_diff_key($query, array_flip(self::PANEL_KEYS));
        $query['person'] = $person->id;

        return redirect()->to($request->getSchemeAndHttpHost().$parts['path'].'?'.http_build_query($query));
    }
}
