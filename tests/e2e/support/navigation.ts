// Everything about getting to a person, the tree's slots and union node, and
// from a fact to its source lives here. Panel and grid rebuilds change this
// file, not the specs. Tree cards, the panel close button and the panel's
// actions are clicked by the person-side-panel and person-panel-* specs
// directly, not through a helper.
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

/** Opens the marriage panel of the union shown on the tree (`Szczegóły małżeństwa`). */
export async function openMarriageFromUnion(page: Page): Promise<void> {
    await chooseUnionMenuItem(page, 'Szczegóły małżeństwa');
}

/** The marriage side panel, named `{husband} i {wife}`; a side panel, not a modal. */
export function marriagePanel(page: Page, title: string): Locator {
    return page.getByRole('dialog', { name: title });
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

    return dialog.getByRole('region', { name });
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

/** One fact's group on the person dialog's `Źródła` tab (`Urodzenie`, `Małżeństwo z …`); a region named by its heading, like the family sections. */
export async function sourcesGroup(
    dialog: Locator,
    heading: string,
): Promise<Locator> {
    await dialog.getByRole('tab', { name: /^Źródła/ }).click();

    return dialog.getByRole('region', { name: heading });
}
