// Risks on `/persons` with the "Dodaj osobę" side panel (`?add=person`) beside
// the list: (a) a person added from the list lands elsewhere or the list
// disappears; (b) typed data is lost when the user clicks a person or types in
// the search beside the panel; (c) a failed search looks like an empty result.
// Data is prepared through the app's routes (tests/e2e/support/api.ts) and
// every record carries the test's unique suffix, which the fixture cleans up.
import { expect, test } from './support/fixtures';
import { createPerson, findPersons } from './support/api';

const DIRTY_ALERT = 'Masz niezapisane zmiany w nowej osobie';

test('persons-add-person-panel: a person added beside a search keeps the typed data and lands as a preview on the list', async ({
    page,
    suffix,
}) => {
    const newPersonName = `Nowa ${suffix}`;

    await page.goto(`/persons?q=${suffix}`);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Osoby' }),
    ).toBeVisible();

    // 1. "Dodaj osobę" opens the form beside the list; the list stays visible.
    await page.getByRole('link', { name: 'Dodaj osobę' }).click();
    const addPanel = page.getByRole('dialog', { name: 'Dodaj osobę' });
    await expect(addPanel).toBeVisible();
    await addPanel.getByLabel('Imię').fill('Nowa');
    await addPanel.getByLabel('Nazwisko', { exact: true }).fill(suffix);

    // 2. A search change does not close the panel nor clear the typed data.
    const searchField = page.getByRole('searchbox', { name: 'Szukaj osoby' });
    await searchField.fill(`${suffix}-szukaj`);
    await expect(page).toHaveURL(
        (url) =>
            url.searchParams.get('q') === `${suffix}-szukaj` &&
            url.searchParams.get('add') === 'person',
    );
    await expect(addPanel).toBeVisible();
    await expect(addPanel.getByLabel('Imię')).toHaveValue('Nowa');
    await expect(addPanel.getByLabel('Nazwisko', { exact: true })).toHaveValue(
        suffix,
    );

    // 3. `Zapisz` turns the panel into the preview of the new person; the list keeps its search.
    await addPanel.getByRole('button', { name: 'Zapisz' }).click();
    await page.waitForURL(
        (url) =>
            url.pathname === '/persons' &&
            url.searchParams.has('person') &&
            url.searchParams.get('q') === `${suffix}-szukaj`,
    );
    const preview = page.getByRole('dialog', { name: newPersonName });
    await expect(preview).toBeVisible();
    await expect(
        page.getByRole('searchbox', { name: 'Szukaj osoby' }),
    ).toHaveValue(`${suffix}-szukaj`);

    // Nothing else was created by this flow.
    const created = (await findPersons(page, suffix)).filter(
        (person) => person.display_name === newPersonName,
    );
    expect(created).toHaveLength(1);
});

test('persons-add-person-panel: a click on a person with an unsaved new person asks first; Porzuć opens that person and creates nobody', async ({
    page,
    suffix,
}) => {
    const existingName = `Istniejąca ${suffix}`;
    const newPersonName = `Nowa ${suffix}`;

    await createPerson(page, {
        first_name: 'Istniejąca',
        last_name: suffix,
    });

    await page.goto(`/persons?q=${suffix}`);
    await page.getByRole('link', { name: 'Dodaj osobę' }).click();
    const addPanel = page.getByRole('dialog', { name: 'Dodaj osobę' });
    await expect(addPanel).toBeVisible();
    await addPanel.getByLabel('Imię').fill('Nowa');
    await addPanel.getByLabel('Nazwisko', { exact: true }).fill(suffix);

    // 1. The person's row in the list asks first; the panel stays with the typed data.
    await page.getByRole('link', { name: existingName }).click();
    const unsaved = addPanel.getByRole('alert');
    await expect(unsaved).toContainText(DIRTY_ALERT);
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('add') === 'person',
    );
    await expect(addPanel.getByLabel('Imię')).toHaveValue('Nowa');

    // 2. `Porzuć` opens the clicked person and saves nothing.
    await unsaved.getByRole('button', { name: 'Porzuć' }).click();
    const existingPanel = page.getByRole('dialog', { name: existingName });
    await expect(existingPanel).toBeVisible();
    await expect(page).toHaveURL(
        (url) => url.searchParams.has('person') && !url.searchParams.has('add'),
    );
    await expect(addPanel).toBeHidden();

    const created = (await findPersons(page, suffix)).some(
        (person) => person.display_name === newPersonName,
    );
    expect(created).toBe(false);
});

test('persons-add-person-panel: a failed search shows an error with a retry, and the retry shows the results', async ({
    page,
    suffix,
}) => {
    const personName = `Szukany ${suffix}`;

    await createPerson(page, {
        first_name: 'Szukany',
        last_name: suffix,
    });

    await page.goto(`/persons?q=${suffix}`);
    await expect(page.getByRole('link', { name: personName })).toBeVisible();

    // 1. The search request is cut: the list stays, and an error says so beside the field.
    let cutOff = true;
    await page.route(
        (url) => url.pathname === '/persons' && url.searchParams.has('q'),
        (route) => {
            if (cutOff && route.request().headers()['x-inertia']) {
                return route.abort();
            }

            return route.continue();
        },
    );

    await page
        .getByRole('searchbox', { name: 'Szukaj osoby' })
        .fill(personName);

    const alert = page.getByRole('alert');
    await expect(alert).toContainText('Nie udało się wyszukać');

    // 2. The network comes back: `Spróbuj ponownie` runs the search again and shows the result.
    cutOff = false;
    await page.getByRole('button', { name: 'Spróbuj ponownie' }).click();
    await expect(page.getByRole('alert')).toHaveCount(0);
    await expect(page.getByRole('link', { name: personName })).toBeVisible();
});
