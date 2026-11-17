// Risk (context/changes/fact-confidence-level/plan.md, Phase 4; test-plan risk 1, "a fact changes silently after an edit"):
// a confidence level set in one section survives that save, the save of another section of the same person, and a fresh open.
// Sections replace the whole event, so a payload that drops the level would clear it without an error.
// Data is prepared through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createPerson } from './support/api';
import { openPerson, sourcesGroup } from './support/navigation';

test('fact-confidence-level: a birth level survives saving another section and a fresh open', async ({
    page,
    suffix,
}) => {
    const personName = `Pewność ${suffix}`;

    const personId = await createPerson(page, {
        first_name: 'Pewność',
        last_name: suffix,
        birth: { year: 1850 },
    });

    // 1. The birth section takes a level and saves.
    const panel = await openPerson(page, personId, personName);
    await panel.getByRole('button', { name: 'Edytuj Urodzenie' }).click();
    const birthEditor = panel.getByRole('region', { name: 'Urodzenie' });
    await birthEditor
        .getByRole('combobox', { name: 'Poziom pewności' })
        .click();
    await page
        .getByRole('option', { name: 'B — indeks / źródło wtórne' })
        .click();
    await birthEditor.getByRole('button', { name: 'Zapisz' }).click();
    await expect(
        panel.getByRole('button', { name: 'Edytuj Urodzenie' }),
    ).toBeVisible();

    // 2. Saving another section of the same person must not touch the level.
    const personal = panel.getByRole('region', {
        name: 'Dane osobowe',
        exact: true,
    });
    await panel.getByRole('button', { name: 'Edytuj Dane osobowe' }).click();
    await personal
        .getByRole('textbox', { name: 'Nazwisko rodowe' })
        .fill(`${suffix}x`);
    await personal.getByRole('button', { name: 'Zapisz' }).click();
    await expect(personal.getByText(`${suffix}x`)).toBeVisible();

    // 3. A fresh open: the badge is on the sources tab and on the overview card, the view and the edit field still read the level.
    const reopened = await openPerson(page, personId, personName);
    const birthSources = await sourcesGroup(reopened, 'Urodzenie');
    await expect(birthSources.getByText('Poziom B')).toBeVisible();

    await reopened.getByRole('tab', { name: 'Przegląd' }).click();
    const birthCard = reopened.getByRole('region', { name: 'Urodzenie' });
    await expect(birthCard.getByText('Poziom B')).toBeVisible();
    await expect(
        birthCard.getByText('B — indeks / źródło wtórne', { exact: true }),
    ).toBeVisible();
    await reopened.getByRole('button', { name: 'Edytuj Urodzenie' }).click();
    await expect(
        reopened
            .getByRole('region', { name: 'Urodzenie' })
            .getByRole('combobox', { name: 'Poziom pewności' }),
    ).toHaveText('B — indeks / źródło wtórne');
});
