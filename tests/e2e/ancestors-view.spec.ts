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
    await expect(childPanel).toBeVisible();

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
        (url) =>
            url.pathname === `/tree/${childId}/ancestors` &&
            url.searchParams.get('generations') === '2',
    );
    await expect(childPanel).toBeVisible();
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

// Risk: "a manual zoom is lost when the canvas resizes, or 'Dopasuj' does not
// bring the fitted view back". Cards are measured by their width on screen, so
// the assertions compare scales without reading the transform.
test('ancestors-view: a manual zoom survives a canvas resize until "Dopasuj"', async ({
    page,
    suffix,
}) => {
    const fatherName = `Ojciec ${suffix}`;
    const motherName = `Matka ${suffix}`;

    const childId = await createPerson(page, {
        first_name: 'Dziecko',
        last_name: suffix,
    });
    await createParent(
        page,
        childId,
        { first_name: 'Ojciec', last_name: suffix, gender: 'male' },
        'father',
    );
    await createParent(
        page,
        childId,
        { first_name: 'Matka', last_name: suffix, gender: 'female' },
        'mother',
    );

    await page.goto(`/tree/${childId}/ancestors?generations=2`);

    const fatherCard = page.getByRole('link', {
        name: `mężczyzna ${fatherName}`,
        exact: true,
    });
    await expect(fatherCard).toBeVisible();

    // The zoom animates; wait until two samples agree before comparing.
    const settledWidth = async (): Promise<number> => {
        let previous = -1;
        let width = 0;

        await expect
            .poll(async () => {
                width = (await fatherCard.boundingBox())?.width ?? 0;
                const settled = Math.abs(width - previous) < 0.5;
                previous = width;

                return settled;
            })
            .toBe(true);

        return width;
    };

    const fitted = await settledWidth();

    // 1. "Pomniejsz" shrinks the cards.
    await page.getByRole('button', { name: 'Pomniejsz', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeLessThan(fitted);
    const zoomedOut = await settledWidth();

    // 2. Opening a panel narrows the canvas; the manual zoom is kept.
    await page
        .getByRole('link', { name: `kobieta ${motherName}`, exact: true })
        .click();
    await expect(page.getByRole('dialog', { name: motherName })).toBeVisible();
    expect(Math.abs((await settledWidth()) - zoomedOut)).toBeLessThan(1);

    // 3. "Dopasuj" brings the fitted view back.
    await page.getByRole('button', { name: 'Dopasuj', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeGreaterThan(zoomedOut);
    const refitted = await settledWidth();

    // 4. "Powiększ" grows the cards again.
    await page.getByRole('button', { name: 'Pomniejsz', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeLessThan(refitted);
    const shrunk = await settledWidth();
    await page.getByRole('button', { name: 'Powiększ', exact: true }).click();
    await expect
        .poll(async () => (await fatherCard.boundingBox())?.width ?? 0)
        .toBeGreaterThan(shrunk);

    // Cleanup: the `suffix` fixture removes every record carrying the suffix.
});
