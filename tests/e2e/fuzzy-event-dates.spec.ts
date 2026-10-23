// Risk (context/changes/fuzzy-event-dates/plan.md, Phase 4; test-plan risk 1, "a fact changes silently after an edit"):
// a fuzzy date written in one section survives that save, the save of another section of the same person, and a fresh open.
// Sections replace the whole event, so a payload that drops the qualifier would turn the date back to exact without an error.
// Data is prepared through the app's routes (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createPerson } from './support/api';
import { openPerson } from './support/navigation';

test('fuzzy-event-dates: a birth range and a death "before" survive each other’s save and a fresh open', async ({
    page,
    suffix,
}) => {
    const personName = `Zakres ${suffix}`;

    const personId = await createPerson(page, {
        first_name: 'Zakres',
        last_name: suffix,
        birth: { year: 1800 },
        death: { year: 1900 },
    });

    // 1. The birth section takes a range and saves.
    const panel = await openPerson(page, personId, personName);
    await panel.getByRole('button', { name: 'Edytuj Urodzenie' }).click();
    const birthEditor = panel.getByRole('region', { name: 'Urodzenie' });
    await birthEditor.getByRole('textbox', { name: 'Data' }).fill('1805–1811');
    await birthEditor.getByRole('button', { name: 'Zapisz' }).click();
    await expect(
        panel.getByRole('button', { name: 'Edytuj Urodzenie' }),
    ).toBeVisible();

    // 2. Saving another section of the same person must not touch the birth date.
    await panel.getByRole('button', { name: 'Edytuj Zgon' }).click();
    const deathEditor = panel.getByRole('region', { name: 'Zgon' });
    await deathEditor.getByRole('textbox', { name: 'Data' }).fill('przed 1870');
    await deathEditor.getByRole('button', { name: 'Zapisz' }).click();
    await expect(
        panel.getByRole('button', { name: 'Edytuj Zgon' }),
    ).toBeVisible();

    // 3. A fresh open: the birth field still reads the range, the death field the "before" date.
    const reopened = await openPerson(page, personId, personName);
    await reopened.getByRole('button', { name: 'Edytuj Urodzenie' }).click();
    await expect(
        reopened
            .getByRole('region', { name: 'Urodzenie' })
            .getByRole('textbox', { name: 'Data' }),
    ).toHaveValue('1805–1811');
    await reopened.getByRole('button', { name: 'Anuluj' }).click();
    await reopened.getByRole('button', { name: 'Edytuj Zgon' }).click();
    await expect(
        reopened
            .getByRole('region', { name: 'Zgon' })
            .getByRole('textbox', { name: 'Data' }),
    ).toHaveValue('przed 1870');
    await reopened.getByRole('button', { name: 'Anuluj' }).click();

    // 4. The timeline shows both kinds.
    await reopened.getByRole('tab', { name: 'Wydarzenia' }).click();
    await expect(
        reopened.getByRole('listitem').filter({ hasText: 'Urodzenie' }),
    ).toContainText('między 1805 a 1811');
    await expect(
        reopened.getByRole('listitem').filter({ hasText: 'Zgon' }),
    ).toContainText('przed');
});
