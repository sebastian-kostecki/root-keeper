// Everything about getting to a person, the tree's slots and union node, and
// from a fact to its source lives here. The side-panel, grid and source-panel
// rebuilds (S-06, S-05, S-02) change this file, not the specs. Deliberately not
// used: `Podgląd`, `Wstecz`, clicking a card, the close button.
import { expect, type Locator, type Page } from '@playwright/test';

export type FamilySectionName = 'Rodzice' | 'Małżeństwa' | 'Dzieci';

/** Who an empty slot adds, as its label says it: `Dodaj ojca`, `Dodaj dziecko`… */
export type SlotRelative = 'ojca' | 'matkę' | 'dziecko' | 'małżonka';

/** An empty slot of the tree; `who` is the person, or the pair `A i B` for a child. */
export function treeSlot(
    page: Page,
    relative: SlotRelative,
    who: string,
): Locator {
    return page.getByRole('button', {
        name: `Dodaj ${relative}: ${who}`,
        exact: true,
    });
}

/** The dialog a slot or the union menu opens: pick an existing person or create one. */
export function slotDialog(
    page: Page,
    relative: SlotRelative,
    who: string,
): Locator {
    return page.getByRole('dialog', { name: `Dodaj ${relative} — ${who}` });
}

/** Clicks the empty slot and returns its dialog. */
export async function openSlot(
    page: Page,
    relative: SlotRelative,
    who: string,
): Promise<Locator> {
    await treeSlot(page, relative, who).click();

    return slotDialog(page, relative, who);
}

/** Switches the union node to the union with the partner. */
export async function switchUnion(
    page: Page,
    partnerName: string,
): Promise<void> {
    await page.getByRole('button', { name: /^Związek \d z \d/ }).click();
    await page
        .getByRole('menuitemradio', { name: new RegExp(partnerName) })
        .click();
}

/** An action from the union node's menu, e.g. `Dodaj małżonka`. */
export async function chooseUnionMenuItem(
    page: Page,
    item: string,
): Promise<void> {
    await page.getByRole('button', { name: /^Związek \d z \d/ }).click();
    await page.getByRole('menuitem', { name: item }).click();
}

/** From a confirmed fact (its region in the person dialog) to the linked source. */
export async function openLinkedSource(
    fact: Locator,
    sourceName: string,
): Promise<void> {
    await fact
        .getByRole('link', { name: `Otwórz źródło ${sourceName}` })
        .click();
}

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
    // The `has` locator is resolved inside each section, so it is built from the
    // page, not from the dialog.
    return dialog.locator('section').filter({
        has: dialog.page().getByRole('heading', { name, level: 3 }),
    });
}

/** The wide side panel of a source on `/sources`; scoped to its dialog, the list headings stay visible beside it. */
export function sourcePanel(page: Page, sourceName: string): Locator {
    return page.getByRole('dialog', { name: sourceName });
}

/** Finds the source by name in the list (search) and opens its card; returns the panel. */
export async function openSourceFromList(
    page: Page,
    sourceName: string,
): Promise<Locator> {
    await page.goto('/sources');
    await page
        .getByRole('searchbox', { name: 'Szukaj źródła' })
        .fill(sourceName);
    // The list is filtered after a debounce; a click before it lands is dropped.
    await expect(page.getByRole('status')).toHaveText('Znaleziono: 1 źródło');
    await page.getByRole('link', { name: sourceName }).click();
    const panel = sourcePanel(page, sourceName);
    await expect(panel).toBeVisible();

    return panel;
}

/** One fact's group on the person dialog's `Źródła` tab (`Urodzenie`, `Małżeństwo z …`); found through its heading, like the family sections. */
export async function sourcesGroup(
    dialog: Locator,
    heading: string,
): Promise<Locator> {
    await dialog.getByRole('tab', { name: /^Źródła/ }).click();

    return dialog.locator('section').filter({
        has: dialog.page().getByRole('heading', { name: heading, level: 3 }),
    });
}
