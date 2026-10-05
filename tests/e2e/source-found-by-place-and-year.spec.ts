// Risk: S-04 source-found-by-place-and-year (US-01) — the author finds the act of
// a fact by place and year and records its entry number on the link. The picker
// opens with the fact's year, the place narrows the list, the chosen act is
// linked with number 95, and the number shows at the fact and on the source's
// "Potwierdza" card. Pest sees the endpoints; only the browser sees the filter,
// the selection step and both views together. Data through the app's routes
// (tests/e2e/support/api.ts). Localities and parishes have no delete route until
// S-11, so they stay in the `e2e` database (unique per run by the suffix) — see
// context/foundation/test-stack.md. Sources and people go with the suffix sweep.
import { expect, test } from './support/fixtures';
import {
    createLocality,
    createParish,
    createPerson,
    createSource,
    personPreview,
} from './support/api';
import {
    openLinkedSource,
    openPerson,
    sourcePanel,
    sourcesGroup,
} from './support/navigation';

const FIXTURE = 'tests/Fixtures/portrait-small.jpg';
const BIRTH_YEAR = 1908;

test('source-found-by-place-and-year: an act found by place and year is linked with its entry number, shown at the fact and on the source', async ({
    page,
    suffix,
}) => {
    const localityName = `Luboml ${suffix}`;
    const otherLocalityName = `Brody ${suffix}`;
    const target = `Akt docelowy ${suffix}`;
    const otherYear = `Akt inny rok ${suffix}`;
    const otherPlace = `Akt inne miejsce ${suffix}`;

    // Setup: a person born in 1908, a parish seated in "Luboml …", the target act
    // (1908, that parish), a decoy of another year and a decoy of another place.
    const personId = await createPerson(page, {
        first_name: 'Jan',
        last_name: suffix,
        birth: { year: BIRTH_YEAR, place: 'Kraków' },
    });
    const parishId = await createParish(page, {
        name: `Parafia ${suffix}`,
        localityId: await createLocality(page, localityName),
    });
    const otherParishId = await createParish(page, {
        name: `Parafia inna ${suffix}`,
        localityId: await createLocality(page, otherLocalityName),
    });
    const targetId = await createSource(page, {
        kind: 'metrical_record',
        name: target,
        fixture: FIXTURE,
        record_type: 'birth',
        year: BIRTH_YEAR,
        parish_id: parishId,
    });
    await createSource(page, {
        kind: 'metrical_record',
        name: otherYear,
        fixture: FIXTURE,
        record_type: 'birth',
        year: BIRTH_YEAR + 1,
        parish_id: parishId,
    });
    await createSource(page, {
        kind: 'metrical_record',
        name: otherPlace,
        fixture: FIXTURE,
        record_type: 'birth',
        year: BIRTH_YEAR,
        parish_id: otherParishId,
    });
    const displayName = (await personPreview(page, personId)).person
        .display_name;

    // 1. The Źródła tab, birth group: open the picker.
    const dialog = await openPerson(page, personId, displayName);
    const birth = await sourcesGroup(dialog, 'Urodzenie');
    await birth.getByRole('button', { name: 'Powiąż źródło' }).click();
    const picker = page.getByRole('dialog', { name: 'Powiąż źródło' });
    await expect(picker).toBeVisible();

    // 2. The year starts as the fact's year: the act of another year is gone.
    await expect(picker.getByLabel('Rok')).toHaveValue(String(BIRTH_YEAR));
    const tile = (name: string) =>
        picker.getByRole('button', { name: new RegExp(name) });
    await expect(tile(target)).toBeVisible();
    await expect(tile(otherPlace)).toBeVisible();
    await expect(tile(otherYear)).toBeHidden();

    // 3. The place narrows it to the act of that locality.
    await picker.getByLabel('Miejsce').fill(localityName);
    await expect(tile(otherPlace)).toBeHidden();
    await expect(tile(target)).toBeVisible();

    // 4. Choose the act, give the entry number, link.
    await tile(target).click();
    await expect(tile(target)).toHaveAttribute('aria-pressed', 'true');
    await picker.getByLabel('Numer wpisu').fill('95');
    await picker.getByRole('button', { name: 'Powiąż', exact: true }).click();
    await expect(picker).toBeHidden();

    // 5. The number is shown at the fact, next to the linked source.
    await expect(birth.getByText('nr 95')).toBeVisible();

    // Oracle on data: the link carries the number in a fresh request.
    const linked = (await personPreview(page, personId)).person.birth?.sources;
    expect(linked?.map((s) => [s.id, s.entry_number])).toEqual([
        [targetId, '95'],
    ]);

    // 6. From the fact to the source: its "Potwierdza" card shows the number too.
    await openLinkedSource(birth, target);
    const panel = sourcePanel(page, target);
    await expect(panel).toBeVisible();
    await expect(
        panel.getByRole('link', { name: `Urodzenie — ${displayName}` }),
    ).toBeVisible();
    await expect(panel.getByText(/nr 95/)).toBeVisible();
});
