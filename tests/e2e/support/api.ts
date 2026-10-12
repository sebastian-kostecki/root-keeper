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
    birth?: { year?: number; locality_id?: number };
    death?: { year?: number };
};

export type SourceKindValue = 'metrical_record' | 'gravestone' | 'other';

export type PersonPreview = {
    person: {
        id: number;
        display_name: string;
        burial_place: string | null;
        gravestones?: { id: number; cemetery_name: string | null }[];
        birth: {
            id: number;
            sources?: { id: number; entry_number: string | null }[];
        } | null;
        marriages: {
            id: number;
            date: string | null;
            spouse: { id: number } | null;
        }[];
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

/**
 * Headers of a write: the decoded `XSRF-TOKEN` cookie (one `GET /` first when
 * the context has none) and `Accept: application/json`. With JSON the app
 * answers a failed validation with 422 and a lost session with 401, instead of
 * a 302 back to a URL taken from the session all parallel tests share; a
 * successful write is still a 302 with its own `Location`.
 */
async function writeHeaders(page: Page): Promise<Record<string, string>> {
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

    return {
        'X-XSRF-TOKEN': decodeURIComponent(cookie.value),
        Accept: 'application/json',
    };
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
    if (fields.birth?.locality_id !== undefined)
        form['birth[locality_id]'] = fields.birth.locality_id;

    if (fields.death) {
        form.is_deceased = 1;
        if (fields.death.year !== undefined)
            form['death[year]'] = fields.death.year;
    }

    return form;
}

/** A successful write: a 302, and when given, a `Location` whose path matches. */
async function expectRedirect(
    response: APIResponse,
    path?: RegExp,
): Promise<void> {
    expect(
        response.status(),
        `${response.url()} answered ${response.status()}: ${await response.text()}`,
    ).toBe(302);
    if (path) {
        const location = new URL(
            response.headers()['location'] ?? '',
            response.url(),
        );
        expect(location.pathname).toMatch(path);
    }
}

/** `persons.store`; the id is read from `?person=` of the redirect to `/persons`. */
export async function createPerson(
    page: Page,
    fields: PersonFields,
): Promise<number> {
    const response = await page.request.post('/persons', {
        headers: await writeHeaders(page),
        form: personForm(fields),
        maxRedirects: 0,
    });
    await expectRedirect(response, /^\/persons$/);

    return idFromLocation(response, 'person');
}

/** `persons.spouses.store`: a new person married to `focusId`; the id comes from `?spouse=`. */
export async function createSpouse(
    page: Page,
    focusId: number,
    fields: PersonFields,
): Promise<number> {
    const response = await page.request.post(`/persons/${focusId}/spouses`, {
        headers: await writeHeaders(page),
        form: personForm(fields),
        maxRedirects: 0,
    });
    await expectRedirect(response, /^\/tree\/\d+$/);

    return idFromLocation(response, 'spouse');
}

/**
 * `persons.parents.store`: a new parent of `childId`. The redirect opens the
 * new parent's panel (`?person=`), so the id is read from its `Location`.
 */
export async function createParent(
    page: Page,
    childId: number,
    fields: PersonFields & { first_name: string; last_name: string },
    role: 'father' | 'mother' | 'parent' = 'parent',
): Promise<number> {
    const response = await page.request.post(`/persons/${childId}/parents`, {
        headers: await writeHeaders(page),
        form: { ...personForm(fields), role },
        maxRedirects: 0,
    });
    await expectRedirect(response, /^\/tree\/\d+$/);

    return idFromLocation(response, 'person');
}

/**
 * `persons.children.store`: a new child of `parentId`, and of `otherParentId`
 * when given. The redirect opens the new child's panel (`?person=`), so the id
 * is read from its `Location`.
 */
async function createChild(
    page: Page,
    parentId: number,
    fields: PersonFields & { first_name: string; last_name: string },
    otherParentId?: number,
): Promise<number> {
    const response = await page.request.post(`/persons/${parentId}/children`, {
        headers: await writeHeaders(page),
        form: {
            ...personForm(fields),
            ...(otherParentId !== undefined && {
                other_parent_id: otherParentId,
            }),
        },
        maxRedirects: 0,
    });
    await expectRedirect(response, /^\/tree\/\d+$/);

    return idFromLocation(response, 'person');
}

/** A child of both spouses of a marriage: `persons.children.store` with `other_parent_id`. */
export function createChildOfCouple(
    page: Page,
    husbandId: number,
    wifeId: number,
    fields: PersonFields & { first_name: string; last_name: string },
): Promise<number> {
    return createChild(page, husbandId, fields, wifeId);
}

/** A child of one parent only: `persons.children.store` without `other_parent_id`. */
export function createChildOfOneParent(
    page: Page,
    parentId: number,
    fields: PersonFields & { first_name: string; last_name: string },
): Promise<number> {
    return createChild(page, parentId, fields);
}

/**
 * Sources this worker created through the API since the last cleanup. They are
 * deleted by id, so cleanup does not depend on how the app names a source.
 */
const createdSourceIds = new Set<number>();

/** `localities.inline.store`; the sweep removes it through `localities.destroy` once nothing uses it. */
export async function createLocality(
    page: Page,
    name: string,
): Promise<number> {
    const response = await page.request.post('/localities/inline', {
        headers: await writeHeaders(page),
        form: { name },
    });
    expect(
        response.status(),
        `${response.url()} answered ${response.status()}: ${await response.text()}`,
    ).toBe(201);

    return ((await response.json()) as { id: number }).id;
}

/** `parishes.inline.store` with a seat locality; the sweep removes it through `parishes.destroy`. */
export async function createParish(
    page: Page,
    parish: { name: string; localityId: number },
): Promise<number> {
    const response = await page.request.post('/parishes/inline', {
        headers: await writeHeaders(page),
        form: {
            name: parish.name,
            religion: 'roman_catholic',
            locality_id: parish.localityId,
        },
    });
    expect(
        response.status(),
        `${response.url()} answered ${response.status()}: ${await response.text()}`,
    ).toBe(201);

    return ((await response.json()) as { data: { id: number } }).data.id;
}

/** `sources.store` with the fixture file as the original scan. */
export async function createSource(
    page: Page,
    source: {
        kind: SourceKindValue;
        name: string;
        fixture: string;
        year?: number;
        parish_id?: number;
        record_type?: 'birth' | 'marriage' | 'death';
    },
): Promise<number> {
    const response = await page.request.post('/sources', {
        headers: await writeHeaders(page),
        multipart: {
            kind: source.kind,
            name: source.name,
            ...(source.year !== undefined && { year: String(source.year) }),
            ...(source.parish_id !== undefined && {
                parish_id: String(source.parish_id),
            }),
            ...(source.record_type !== undefined && {
                record_type: source.record_type,
            }),
            file: {
                name: basename(source.fixture),
                mimeType: 'image/jpeg',
                buffer: readFileSync(source.fixture),
            },
        },
        maxRedirects: 0,
    });
    await expectRedirect(response, /^\/sources$/);
    const id = idFromLocation(response, 'source');
    createdSourceIds.add(id);

    return id;
}

/** `events.sources.store`: link the source to the event as proof of it. */
export async function linkSource(
    page: Page,
    eventId: number,
    sourceId: number,
): Promise<void> {
    const response = await page.request.post(`/events/${eventId}/sources`, {
        headers: await writeHeaders(page),
        form: { source_id: sourceId },
        maxRedirects: 0,
    });
    await expectRedirect(response);
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

export type FoundPerson = { id: number; display_name: string };

/**
 * People whose name carries the text (`/persons?q=` matches first and last
 * name); how a spec finds a person it created through the UI.
 */
export async function findPersons(
    page: Page,
    text: string,
): Promise<FoundPerson[]> {
    const props = await pageProps(
        page,
        `/persons?q=${encodeURIComponent(text)}`,
    );

    return (props.persons as FoundPerson[] | undefined) ?? [];
}

/**
 * A DELETE as a browser form sends it: `POST` with `_method=DELETE`. A
 * successful delete is a 302; it is not followed, because the person delete
 * redirects to a page taken from the shared session, maybe another test's.
 */
async function deleteRecord(
    page: Page,
    headers: Record<string, string>,
    path: string,
): Promise<void> {
    await expectRedirect(
        await page.request.post(path, {
            headers,
            form: { _method: 'DELETE' },
            maxRedirects: 0,
        }),
    );
}

/**
 * Removes the sources this worker created through the API, every other source
 * whose name carries the suffix, then every person whose name carries it, then
 * parishes and localities whose name carries it. Runs as the `suffix` fixture's
 * teardown, so it must work after a red assertion; each DELETE is checked.
 * Sources go first (`DeleteSource` removes links and files), then people
 * (`DeletePerson` removes marriages, events and parent-child relations), then
 * parishes (they reference localities), then localities.
 */
export async function cleanupBySuffix(
    page: Page,
    suffix: string,
): Promise<void> {
    // An empty or foreign suffix would match the whole E2E tree.
    if (!suffix.startsWith(`${TEST_PREFIX}-`)) {
        throw new Error(`Refusing to clean up by suffix "${suffix}"`);
    }
    const headers = await writeHeaders(page);

    const sources =
        ((await pageProps(page, '/sources')).sources as
            | { id: number; display_name: string }[]
            | undefined) ?? [];
    const sourceIds = new Set(createdSourceIds);
    for (const source of sources) {
        if (source.display_name.includes(suffix)) {
            sourceIds.add(source.id);
        }
    }
    for (const id of sourceIds) {
        await deleteRecord(page, headers, `/sources/${id}`);
        createdSourceIds.delete(id);
    }

    for (const person of await findPersons(page, suffix)) {
        if (!person.display_name.includes(suffix)) {
            continue;
        }
        await deleteRecord(page, headers, `/persons/${person.id}`);
    }

    const places = await pageProps(page, '/places');
    const parishes =
        (places.parishes as { id: number; name: string }[] | undefined) ?? [];
    for (const parish of parishes) {
        if (parish.name.includes(suffix)) {
            await deleteRecord(page, headers, `/parishes/${parish.id}`);
        }
    }

    const localities =
        (places.localities as { id: number; name: string }[] | undefined) ?? [];
    for (const locality of localities) {
        if (locality.name.includes(suffix)) {
            await deleteRecord(page, headers, `/localities/${locality.id}`);
        }
    }
}
