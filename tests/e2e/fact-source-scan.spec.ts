// Risk: FR-025 fact-source-scan — a fact marked `Potwierdzone` leads to the same
// source and the same scan: the link from the person preview opens the source
// that was linked, the scan has the bytes of the uploaded file, and the source
// page shows the fact back. Guarded before the S-02/S-04/S-06 rebuilds
// (prd.md:209, roadmap.md F-01). Data is prepared through the app's routes
// (tests/e2e/support/api.ts); only the protected path runs in the UI.
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { expect, test } from './support/fixtures';
import {
    createPerson,
    createSource,
    linkSource,
    personPreview,
} from './support/api';
import { openLinkedSource, openPerson } from './support/navigation';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

const sha256 = (bytes: Buffer): string =>
    createHash('sha256').update(bytes).digest('hex');

test('fact-source-scan: a confirmed birth leads to the same source and the same scan', async ({
    page,
    suffix,
}) => {
    const sourceName = `Akt urodzenia ${suffix}`;

    // Setup through the app's routes: a person with a birth, a scanned
    // metrical record, and the birth linked to the record.
    const personId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        birth: { year: 1900, place: 'Kraków' },
    });
    const sourceId = await createSource(page, {
        kind: 'metrical_record',
        name: sourceName,
        fixture: FIXTURE,
    });
    const preview = await personPreview(page, personId);
    const displayName = preview.person.display_name;
    if (!preview.person.birth) {
        throw new Error('The created person has no birth event');
    }
    await linkSource(page, preview.person.birth.id, sourceId);

    // 1. From the person preview, the birth fact is confirmed and links to the source.
    const dialog = await openPerson(page, personId, displayName);
    const birth = dialog.getByRole('region', {
        name: 'Urodzenie',
        exact: true,
    });
    await expect(birth.getByText(/^Potwierdzone/)).toBeVisible();
    await openLinkedSource(birth, sourceName);

    // 2. The source page: the heading is the linked source and the scan
    // points at that source's file.
    await expect(page.getByRole('heading', { name: sourceName })).toBeVisible();
    const scan = page.getByRole('img', { name: sourceName });
    await expect(scan).toHaveAttribute(
        'src',
        new RegExp(`/sources/${sourceId}/`),
    );

    // The scan shown for that source is byte for byte the uploaded fixture.
    const src = await scan.getAttribute('src');
    if (!src) {
        throw new Error(`The scan of ${sourceName} has no src`);
    }
    const file = await page.request.get(src);
    await expect(file).toBeOK();
    expect(sha256(await file.body())).toBe(sha256(readFileSync(FIXTURE)));

    // The fact is shown back on the source.
    await expect(
        page.getByRole('link', { name: `Urodzenie — ${displayName}` }),
    ).toBeVisible();
});
