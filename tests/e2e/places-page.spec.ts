// Risk: places-page — two browser-only behaviours of the merged /places page.
// (a) An old /parishes?parish=<id> link (a bookmark, or a link from before the merge) must land on the
// parish tab with the panel of that parish open and its row marked as the open one: the redirect,
// the tab switch and the panel key have to agree in the browser. (b) A search for a parish dedication on
// the localities tab must offer the parish tab ("Pokaż w parafiach i urzędach (n)") and, after the click, show
// that parish in the parish list with the search kept in the address. Pest sees the redirect and the
// query; only the browser sees the tab, the panel and the button together. Data through the app's
// routes; every name carries the test's suffix, so the sweep removes the parish and the locality
// (see support/api.ts).
import { expect, test } from './support/fixtures';
import { createLocality, createParish } from './support/api';

test('places-page: an old /parishes?parish= link opens the parish tab with that parish panel and its row marked open', async ({
    page,
    suffix,
}) => {
    const seatName = `Lubomla ${suffix}`;

    // 1. Setup: a parish with a seat, created through the app's routes. Its title is the label: seat and religion.
    const localityId = await createLocality(page, seatName);
    const parishId = await createParish(page, { localityId });
    const parishName = `${seatName} (rzym.-kat.)`;

    // 2. The old address, as a bookmark would open it. The browser follows the redirect.
    await page.goto(`/parishes?parish=${parishId}`);

    // 3. The risk: the address lands on /places with the parish tab and the parish key,
    // the parish panel is the one open, and its row is the open row.
    await expect(page).toHaveURL(/\/places\?/);
    const landed = new URL(page.url());
    expect(landed.pathname).toBe('/places');
    expect(landed.searchParams.get('kind')).toBe('parafie');
    expect(landed.searchParams.get('parish')).toBe(String(parishId));

    const panel = page.getByRole('dialog', {
        name: parishName,
        exact: true,
    });
    await expect(panel).toBeVisible();
    await expect(
        page.getByRole('link', { name: parishName, exact: true }),
    ).toHaveAttribute('aria-current', 'true');
});

test('places-page: a parish name searched on the localities tab offers the parish tab, which shows the parish with the search kept', async ({
    page,
    suffix,
}) => {
    const seatName = `Ostrów ${suffix}`;
    const dedication = `Fara ${suffix}`;

    // 1. Setup: a parish whose dedication no locality carries, so the search hits only the parish list.
    const localityId = await createLocality(page, seatName);
    await createParish(page, { localityId, dedication });
    const parishLabel = `${seatName} ${dedication} (rzym.-kat.)`;

    // 2. Start on the localities tab (the default), and search the parish's name there.
    await page.goto('/places');
    const search = page.getByRole('searchbox', {
        name: 'Szukaj miejscowości, parafii, urzędów i cmentarzy',
    });
    await search.fill(dedication);

    // 3. The localities tab has no hit, so the empty result offers the parish tab with its count.
    const showParishes = page.getByRole('button', {
        name: 'Pokaż w parafiach i urzędach (1)',
        exact: true,
    });
    await expect(showParishes).toBeVisible();
    await showParishes.click();

    // 4. The risk: the parish tab opens with the search kept in the address, and the parish is listed.
    await page.waitForURL(
        (url) =>
            url.pathname === '/places' &&
            url.searchParams.get('kind') === 'parafie' &&
            url.searchParams.get('q') === dedication,
    );
    await expect(
        page.getByRole('link', { name: parishLabel, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('searchbox', {
            name: 'Szukaj miejscowości, parafii, urzędów i cmentarzy',
        }),
    ).toHaveValue(dedication);
});
