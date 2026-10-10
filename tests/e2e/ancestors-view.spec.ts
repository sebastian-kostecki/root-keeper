// Risk: "entry to the ancestors view and recentring lead to the wrong person or
// lose the panel". The ancestors page is opened from a person's panel and
// recentred from a card; after each step the address, the heading and the open
// panel must all name the same person, and the generations switch must keep the
// chart's person. Data is prepared through the app's routes
// (tests/e2e/support/api.ts).
import { expect, test } from './support/fixtures';
import { createParent, createPerson } from './support/api';
import { openPerson } from './support/navigation';

test('ancestors-view: entry from the person panel and recentring keep the right person and the panel', async ({
    page,
    suffix,
}) => {
    const childName = `Dziecko ${suffix}`;
    const fatherName = `Ojciec ${suffix}`;
    const motherName = `Matka ${suffix}`;
    const grandfatherName = `Dziadek ${suffix}`;

    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    const fatherId = await createParent(
        page,
        childId,
        { first_name: 'Ojciec', last_name: suffix, gender: 'male' },
        'father',
    );
    const motherId = await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );
    // The father's father only: his mother stays unknown.
    await createParent(
        page,
        fatherId,
        { first_name: 'Dziadek', last_name: suffix, gender: 'male' },
        'father',
    );

    // 1. Entry: the child's panel on the tree leads to the ancestors page.
    const childPanel = await openPerson(page, childId, childName);
    await childPanel
        .getByRole('button', { name: 'Więcej działań', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Pokaż przodków' }).click();

    await expect(page).toHaveURL(
        (url) => url.pathname === `/tree/${childId}/ancestors`,
    );
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(childName);

    // 2. The chart shows the known ancestors and the placeholders for the
    // missing parents, with the default four generations.
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${fatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: `kobieta ${motherName}`, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${grandfatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(page.getByText('Nieznana matka', { exact: true })).toHaveCount(
        3,
    );
    await expect(
        page.getByText('Nieznany ojciec', { exact: true }),
    ).toHaveCount(2);

    // 3. Two generations hide the grandfather and every placeholder; the
    // parents stay.
    await page.getByRole('radio', { name: '2', exact: true }).click();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('generations') === '2',
    );
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${grandfatherName}`,
            exact: true,
        }),
    ).toHaveCount(0);
    await expect(page.getByText('Nieznana matka', { exact: true })).toHaveCount(
        0,
    );
    await expect(
        page.getByText('Nieznany ojciec', { exact: true }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('link', {
            name: `mężczyzna ${fatherName}`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: `kobieta ${motherName}`, exact: true }),
    ).toBeVisible();

    // 4. A click on the mother's card opens her panel beside the chart.
    await page
        .getByRole('link', { name: `kobieta ${motherName}`, exact: true })
        .click();
    const motherPanel = page.getByRole('dialog', { name: motherName });
    await expect(motherPanel).toBeVisible();

    // 5. Her panel's "Pokaż przodków" recentres the chart on her and keeps
    // the panel open and the generations of the address.
    await motherPanel
        .getByRole('button', { name: 'Więcej działań', exact: true })
        .click();
    await page.getByRole('menuitem', { name: 'Pokaż przodków' }).click();

    await expect(page).toHaveURL(
        (url) =>
            url.pathname === `/tree/${motherId}/ancestors` &&
            url.searchParams.get('generations') === '2' &&
            url.searchParams.get('person') === String(motherId),
    );
    await expect(heading).toHaveText(motherName);
    await expect(motherPanel).toBeVisible();

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});
