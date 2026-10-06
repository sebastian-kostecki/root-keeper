// Risk: S-09 gravestone-cemetery-and-buried (FR-002, FR-004) — describing a
// gravestone in the source panel (a cemetery from the register and a buried
// person chosen in a dialog, both sent as hidden inputs of the Inertia form) must
// bury a deceased person with no burial place at that cemetery, and the same
// gravestone must then be found in the source picker of her death by place and
// year, shown as "cmentarz …" where an act shows its parish. Pest sees the
// endpoints; only the browser sees the form, the picker dialog and the three views
// together. Data through the app's routes (tests/e2e/support/api.ts). A locality
// has no delete route until S-11, so it stays in the `e2e` database (unique per
// run by the suffix) — see context/foundation/test-stack.md. Source and person go
// with the suffix sweep.
import { expect, test } from './support/fixtures';
import {
    createLocality,
    createPerson,
    createSource,
    personPreview,
} from './support/api';
import { openPerson, sourcePanel, sourcesGroup } from './support/navigation';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';
const DEATH_YEAR = 1950;

test('gravestone-cemetery-and-buried: a person added to a gravestone is buried at its cemetery, and the gravestone is found by that place and year at her death', async ({
    page,
    suffix,
}) => {
    const cemetery = `Luboml ${suffix}`;
    const gravestone = `Nagrobek ${suffix}`;

    // Setup: a deceased person (death year only, no burial place), and a
    // gravestone of the same year with no cemetery yet.
    const personId = await createPerson(page, {
        first_name: 'Maria',
        last_name: suffix,
        death: { year: DEATH_YEAR },
    });
    await createLocality(page, cemetery);
    const gravestoneId = await createSource(page, {
        kind: 'gravestone',
        name: gravestone,
        fixture: FIXTURE,
        year: DEATH_YEAR,
    });
    const displayName = (await personPreview(page, personId)).person
        .display_name;
    expect(
        (await personPreview(page, personId)).person.burial_place,
    ).toBeNull();

    // 1. The gravestone's panel: choose the cemetery from the register.
    await page.goto(`/sources?source=${gravestoneId}`);
    const panel = sourcePanel(page, gravestone);
    await expect(panel).toBeVisible();
    const cemeteryField = panel.getByRole('combobox', { name: 'Cmentarz' });
    await cemeteryField.fill(cemetery);
    await page.getByRole('option', { name: cemetery, exact: true }).click();
    await expect(cemeteryField).toHaveValue(cemetery);

    // 2. Add the person through the dialog; she shows on the list.
    await panel.getByRole('button', { name: 'Dodaj osobę' }).click();
    const picker = page.getByRole('dialog', { name: 'Dodaj osobę pochowaną' });
    await expect(picker).toBeVisible();
    await picker.getByRole('button', { name: new RegExp(displayName) }).click();
    await expect(picker).toBeHidden();
    await expect(
        panel.getByRole('button', { name: `Usuń ${displayName}` }),
    ).toBeVisible();

    // 3. Save: the list and the cemetery go with the form.
    await panel.getByRole('button', { name: 'Zapisz' }).click();
    await expect(
        page.getByText('Nikt nie jest jeszcze na liście.'),
    ).toBeHidden();

    // Oracle on data: in a fresh request she is buried at the cemetery and
    // the gravestone is among hers.
    await expect(async () => {
        const person = (await personPreview(page, personId)).person;
        expect(person.burial_place).toBe(cemetery);
        expect(person.gravestones?.map((g) => g.id)).toEqual([gravestoneId]);
    }).toPass();

    // 4. Her panel shows the burial place and the link to the gravestone.
    const dialog = await openPerson(page, personId, displayName);
    await expect(dialog.getByText('Miejsce pochowania')).toBeVisible();
    await expect(dialog.getByText(cemetery).first()).toBeVisible();
    await expect(
        dialog.getByRole('link', {
            name: `${gravestone} (cmentarz ${cemetery})`,
        }),
    ).toBeVisible();

    // 5. At her death, the source picker finds the gravestone by place and year,
    // shown by its cemetery.
    const death = await sourcesGroup(dialog, 'Zgon');
    await death.getByRole('button', { name: 'Powiąż źródło' }).click();
    const sourcePicker = page.getByRole('dialog', { name: 'Powiąż źródło' });
    await expect(sourcePicker).toBeVisible();
    await expect(sourcePicker.getByLabel('Rok')).toHaveValue(
        String(DEATH_YEAR),
    );
    await sourcePicker.getByLabel('Miejsce').fill(cemetery);
    await expect(
        sourcePicker.getByRole('button', {
            name: new RegExp(`${gravestone}.*cmentarz ${cemetery}`),
        }),
    ).toBeVisible();
});
