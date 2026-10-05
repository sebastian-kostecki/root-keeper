// Everything about getting to a person or the tree lives here. The side-panel,
// grid and source-panel rebuilds (S-06, S-05, S-02) change this file, not the
// specs. Deliberately not used: `Podgląd`, `Wstecz`, clicking a card, the close button.
import { expect, type Locator, type Page } from '@playwright/test';

export type FamilySectionName = 'Rodzice' | 'Małżeństwa' | 'Dzieci';

/** The tree centred on the person; waits for the page heading with the centre person's name. */
export async function gotoTree(page: Page, personId: number): Promise<void> {
    await page.goto(`/tree/${personId}`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}

/** Opens the person preview through `?person=` and returns its dialog. */
export async function openPerson(
    page: Page,
    personId: number,
    displayName: string,
): Promise<Locator> {
    await page.goto(`/tree/${personId}?person=${personId}`);
    const dialog = page.getByRole('dialog', { name: displayName });
    await expect(dialog).toBeVisible();

    return dialog;
}

/** One section of the `Rodzina` tab, scoped to the person dialog. */
export async function familySection(
    dialog: Locator,
    name: FamilySectionName,
): Promise<Locator> {
    await dialog.getByRole('tab', { name: /^Rodzina/ }).click();

    // The section has no accessible name (a bare <section> is not a region), so it is
    // found through its heading. This is the one structural locator; keep it here.
    return dialog
        .locator('section')
        .filter({ has: dialog.getByRole('heading', { name, level: 3 }) });
}
