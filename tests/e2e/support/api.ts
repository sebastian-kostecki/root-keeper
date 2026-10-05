// Test data through the app's own routes, so a test walks only the protected
// path in the UI and never touches the database. Every written record carries
// the test's unique suffix: it is what `cleanupBySuffix` finds afterwards.
import { readFileSync } from 'node:fs';
import { basename } from 'node:path';
import {
    expect,
    type APIResponse,
    type Page,
    type TestInfo,
} from '@playwright/test';

/** Prefix of every E2E record; lets phases 2 and 3 count leftovers in the `e2e` database. */
export const TEST_PREFIX = 'e2etest';

export type PersonFields = {
    first_name?: string;
    last_name?: string;
    gender?: 'male' | 'female' | 'unknown';
    birth?: { year?: number; place?: string };
};

export type SourceKindValue = 'metrical_record' | 'gravestone' | 'other';

export type PersonPreview = {
    person: {
        id: number;
        display_name: string;
        birth: { id: number } | null;
    };
};

/**
 * One token without spaces, `%` or `_` (so it is safe in a LIKE and in a
 * locator): prefix, timestamp, worker, repeat and retry. Unique per run.
 */
export function uniqueSuffix(testInfo: TestInfo): string {
    return [
        TEST_PREFIX,
        Date.now(),
        testInfo.workerIndex,
        testInfo.repeatEachIndex,
        testInfo.retry,
    ].join('-');
}

/** Decoded `XSRF-TOKEN` cookie; one `GET /` first when the context has none. */
async function xsrfHeaders(page: Page): Promise<Record<string, string>> {
    const read = async () =>
        (await page.context().cookies()).find(
            (cookie) => cookie.name === 'XSRF-TOKEN',
        );

    let cookie = await read();
    if (!cookie) {
        await expect(await page.request.get('/')).toBeOK();
        cookie = await read();
    }
    if (!cookie) {
        throw new Error('The app issued no XSRF-TOKEN cookie');
    }

    return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) };
}

/** Last numeric id in a redirect `Location`, e.g. `/sources/12` or `?person=12`. */
function idFromLocation(response: APIResponse, key?: string): number {
    const location = response.headers()['location'];
    if (!location) {
        throw new Error(
            `Expected a redirect, got ${response.status()} ${response.url()}`,
        );
    }
    const url = new URL(location, response.url());
    const raw = key
        ? url.searchParams.get(key)
        : url.pathname.split('/').filter(Boolean).pop();
    const id = Number(raw);
    if (!Number.isInteger(id) || id <= 0) {
        throw new Error(`No id in redirect Location ${location}`);
    }

    return id;
}

function personForm(fields: PersonFields): Record<string, string | number> {
    const form: Record<string, string | number> = {};
    if (fields.first_name) form.first_name = fields.first_name;
    if (fields.last_name) form.last_name = fields.last_name;
    if (fields.gender) form.gender = fields.gender;
    if (fields.birth?.year !== undefined)
        form['birth[year]'] = fields.birth.year;
    if (fields.birth?.place !== undefined)
        form['birth[place]'] = fields.birth.place;

    return form;
}

function expectRedirect(response: APIResponse): void {
    expect([301, 302, 303]).toContain(response.status());
}

/** `persons.store`; the id is read from the redirect to `/tree/{id}`. */
export async function createPerson(
    page: Page,
    fields: PersonFields,
): Promise<number> {
    const response = await page.request.post('/persons', {
        headers: await xsrfHeaders(page),
        form: personForm(fields),
        maxRedirects: 0,
    });
    expectRedirect(response);

    return idFromLocation(response, 'person');
}

/** `persons.spouses.store`: a new person married to `focusId`; the id comes from `?spouse=`. */
export async function createSpouse(
    page: Page,
    focusId: number,
    fields: PersonFields,
): Promise<number> {
    const response = await page.request.post(`/persons/${focusId}/spouses`, {
        headers: await xsrfHeaders(page),
        form: personForm(fields),
        maxRedirects: 0,
    });
    expectRedirect(response);

    return idFromLocation(response, 'spouse');
}

/** `sources.store` with the fixture file as the original scan. */
export async function createSource(
    page: Page,
    source: { kind: SourceKindValue; name: string; fixture: string },
): Promise<number> {
    const response = await page.request.post('/sources', {
        headers: await xsrfHeaders(page),
        multipart: {
            kind: source.kind,
            name: source.name,
            file: {
                name: basename(source.fixture),
                mimeType: 'image/jpeg',
                buffer: readFileSync(source.fixture),
            },
        },
        maxRedirects: 0,
    });
    expectRedirect(response);

    return idFromLocation(response);
}

/** `events.sources.store`: link the source to the event as proof of it. */
export async function linkSource(
    page: Page,
    eventId: number,
    sourceId: number,
): Promise<void> {
    const response = await page.request.post(`/events/${eventId}/sources`, {
        headers: await xsrfHeaders(page),
        form: { source_id: sourceId },
        maxRedirects: 0,
    });
    expectRedirect(response);
}

/** Inertia page object embedded in the HTML, read without an `X-Inertia` request (no version check, no 409). */
async function pageProps(
    page: Page,
    path: string,
): Promise<Record<string, unknown>> {
    const response = await page.request.get(path);
    await expect(response).toBeOK();
    const html = await response.text();
    const match = html.match(
        /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/,
    );
    if (!match) {
        throw new Error(`No embedded Inertia page object in ${path}`);
    }

    return (JSON.parse(match[1]) as { props: Record<string, unknown> }).props;
}

/** `props.personPreview` of `/tree/{id}?person={id}`. */
export async function personPreview(
    page: Page,
    personId: number,
): Promise<PersonPreview> {
    const props = await pageProps(page, `/tree/${personId}?person=${personId}`);
    const preview = props.personPreview as PersonPreview | null;
    if (!preview) {
        throw new Error(`No personPreview for person ${personId}`);
    }

    return preview;
}

/**
 * A DELETE as a browser form sends it: `POST` with `_method=DELETE`. The app
 * answers with a 302, which a bare DELETE would follow as another DELETE (405);
 * a POST is followed as a GET, so a successful delete ends in a 200.
 */
function deleteRecord(
    page: Page,
    headers: Record<string, string>,
    path: string,
): Promise<APIResponse> {
    return page.request.post(path, { headers, form: { _method: 'DELETE' } });
}

/**
 * Removes every source, then every person, whose name carries the suffix. Runs
 * in a `finally`, so it must work after a red assertion; each DELETE is checked.
 * Sources go first (`DeleteSource` removes links and files), then people
 * (`DeletePerson` removes marriages, events and parent-child relations).
 */
export async function cleanupBySuffix(
    page: Page,
    suffix: string,
): Promise<void> {
    const headers = await xsrfHeaders(page);

    const sources =
        ((await pageProps(page, '/sources')).sources as
            | { id: number; display_name: string }[]
            | undefined) ?? [];
    for (const source of sources.filter((item) =>
        item.display_name.includes(suffix),
    )) {
        await expect(
            await deleteRecord(page, headers, `/sources/${source.id}`),
        ).toBeOK();
    }

    const persons =
        ((await pageProps(page, `/persons?q=${encodeURIComponent(suffix)}`))
            .persons as { id: number }[] | undefined) ?? [];
    for (const person of persons) {
        await expect(
            await deleteRecord(page, headers, `/persons/${person.id}`),
        ).toBeOK();
    }
}
