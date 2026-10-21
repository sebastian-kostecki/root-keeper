// Risk: S-03 act-described-by-parish-and-archive — adding a parish / office (with a new
// seat locality) from the panel of a new act must not lose what was already
// typed: the modal saves over plain HTTP, so the source form keeps its fields,
// the new parish is selected, and the saved act shows parish and archive. Pest
// sees the endpoints; only the browser sees the portal modal, the form state
// and the selection together. Data through the UI; the source name carries the
// suffix, so the fixture's cleanup sweep removes it, and the parish and the
// locality it creates with it (the sweep deletes them once nothing uses them).
import { expect, test } from './support/fixtures';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';

test('source-panel-parish: a parish added with a new seat keeps the typed act fields and is saved with the act', async ({
    page,
    suffix,
}) => {
    const sourceName = `Akt ${suffix}`;
    const typedText = `Parafia ${suffix}`;
    const localityName = `Miejscowość ${suffix}`;
    const dedication = `Fara ${suffix}`;
    // The parish has no name of its own: its label is the seat plus the dedication.
    const parishLabel = `${localityName} ${dedication}`;
    const archive = `Archiwum ${suffix}`;

    // 1. Open "Dodaj źródło", upload a scan, choose the kind "akt metrykalny".
    await page.goto('/sources');
    // The page offers the same link twice (header and empty state); either opens the panel.
    await page.getByRole('link', { name: 'Dodaj źródło' }).first().click();
    const panel = page.getByRole('dialog', { name: 'Nowe źródło' });
    await expect(panel).toBeVisible();
    await panel.getByLabel('Plik').setInputFiles(FIXTURE);
    await panel.getByRole('radio', { name: 'Akt metrykalny' }).click();

    // 2. Type the act's name and year before touching the parish.
    await panel.getByLabel('Nazwa', { exact: true }).fill(sourceName);
    await panel.getByLabel('Rok').fill('1878');

    // 3. Parish / office: type new text, offer "Dodaj parafię / urząd", the modal opens.
    await panel
        .getByRole('combobox', { name: 'Parafia / urząd' })
        .fill(typedText);
    await page
        .getByRole('option', { name: `Dodaj parafię / urząd „${typedText}”` })
        .click();
    const modal = page.getByRole('dialog', { name: 'Nowa parafia / urząd' });
    await expect(modal).toBeVisible();

    // 4. Seat (required): a new locality added from the suggestion list, a dedication, then the modal is saved.
    await modal
        .getByRole('combobox', { name: 'Miejscowość siedziby' })
        .fill(localityName);
    await modal
        .getByRole('option', { name: `Dodaj miejscowość „${localityName}”` })
        .click();
    await expect(
        modal.getByRole('combobox', { name: 'Miejscowość siedziby' }),
    ).toHaveValue(localityName);
    await modal.getByLabel('Wezwanie / wyróżnik').fill(dedication);
    await modal.getByRole('button', { name: 'Dodaj', exact: true }).click();
    await expect(modal).toBeHidden();

    // 5. The risk: the source form kept its fields and shows the new parish.
    await expect(panel.getByLabel('Nazwa', { exact: true })).toHaveValue(
        sourceName,
    );
    await expect(panel.getByLabel('Rok')).toHaveValue('1878');
    await expect(
        panel.getByRole('combobox', { name: 'Parafia / urząd' }),
    ).toHaveValue(parishLabel);

    // 6. Archive, save the source.
    await panel.getByLabel('Archiwum').fill(archive);
    await panel.getByRole('button', { name: 'Zapisz źródło' }).click();

    // 7. Oracle on data: a fresh request of the saved act shows parish and archive.
    const saved = page.getByRole('dialog', { name: sourceName });
    await expect(saved).toBeVisible();
    await page.reload();
    const reopened = page.getByRole('dialog', { name: sourceName });
    await expect(reopened).toBeVisible();
    await expect(
        reopened.getByRole('combobox', { name: 'Parafia / urząd' }),
    ).toHaveValue(parishLabel);
    await expect(reopened.getByLabel('Archiwum')).toHaveValue(archive);
    await expect(reopened.getByLabel('Rok')).toHaveValue('1878');
});
