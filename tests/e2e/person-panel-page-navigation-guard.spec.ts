// Risk: an unsaved section of the person panel is lost without a word when
// the author navigates from the page beside the panel (impl-review F3 of
// person-side-panel). The panel is not modal, so the sidebar stays clickable;
// a visit that drops `?person` must ask first and keep the typed value until
// the author lets it go. Data is prepared through the app's routes
// (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createPerson } from './support/api';
import { openPerson } from './support/navigation';

test('person-panel-page-navigation-guard: a sidebar link with an unsaved section asks first and keeps the typed value', async ({
    page,
    suffix,
}) => {
    const personName = `Dziecko ${suffix}`;

    const personId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });

    // 1. The person's panel, with the personal data edited but not saved.
    const panel = await openPerson(page, personId, personName);
    await panel.getByRole('button', { name: 'Edytuj Dane osobowe' }).click();
    const lastNameField = panel.getByRole('textbox', {
        name: 'Nazwisko rodowe',
    });
    await lastNameField.fill('Kowalska');

    // 2. `Źródła` in the sidebar asks first; the panel and the typed value stay.
    await page.getByRole('link', { name: 'Źródła', exact: true }).click();
    const unsaved = panel.getByRole('alert');
    await expect(unsaved).toContainText('Masz niezapisane zmiany');
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${personId}` &&
            url.searchParams.get('person') === String(personId),
    );
    await expect(lastNameField).toHaveValue('Kowalska');

    // 3. `Porzuć` lets the visit go: the sources page, without the panel.
    await unsaved.getByRole('button', { name: 'Porzuć' }).click();
    await expect(page).toHaveURL((url) => url.pathname === '/sources');
    await expect(
        page.getByRole('heading', { name: 'Źródła', level: 1 }),
    ).toBeVisible();
    await expect(panel).toBeHidden();
});
