// Risk: S-02 source-side-panel — a source opened from the list in the wide side
// panel can be edited without losing work: closing the panel with unsaved
// changes asks first and keeps the typed value on "Wróć do edycji", and "Zapisz"
// persists the change (the panel title and the data after a fresh request show
// it). Pest sees the props contract; only the browser sees the panel, the
// guard dialog and the form together. Data through support/api.ts; the new
// name keeps the test suffix, so the cleanup sweep still finds the source.
import { expect, test } from './support/fixtures';
import { createSource } from './support/api';
import { openSourceFromList, sourcePanel } from './support/navigation';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

test('source-panel-edit: unsaved changes are guarded on close and a save persists the new name', async ({
    page,
    suffix,
}) => {
    const sourceName = `Akt ${suffix}`;
    const newName = `Akt zmieniony ${suffix}`;

    const sourceId = await createSource(page, {
        kind: 'metrical_record',
        name: sourceName,
        fixture: FIXTURE,
    });

    // 1. From the list: search by name, open the card, the panel shows the scan.
    const panel = await openSourceFromList(page, sourceName);
    await expect(panel.getByRole('img', { name: sourceName })).toHaveAttribute(
        'src',
        new RegExp(`/sources/${sourceId}/`),
    );

    // 2. Change the name, try to close: the guard asks, "Wróć do edycji" keeps the value.
    await panel.getByLabel('Nazwa').fill(newName);
    await panel.getByRole('button', { name: 'Zamknij panel' }).click();
    const guard = page.getByRole('dialog', {
        name: 'Odrzucić niezapisane zmiany?',
    });
    await expect(guard).toBeVisible();
    await guard.getByRole('button', { name: 'Wróć do edycji' }).click();
    await expect(guard).toBeHidden();
    await expect(panel.getByLabel('Nazwa')).toHaveValue(newName);

    // 3. Save: the panel is titled with the new name.
    await panel.getByRole('button', { name: 'Zapisz' }).click();
    await expect(sourcePanel(page, newName)).toBeVisible();

    // 4. Oracle on data: a fresh request shows the saved name in the title and the field.
    await page.goto(`/sources?source=${sourceId}`);
    const reopened = sourcePanel(page, newName);
    await expect(reopened).toBeVisible();
    await expect(reopened.getByLabel('Nazwa')).toHaveValue(newName);
});
