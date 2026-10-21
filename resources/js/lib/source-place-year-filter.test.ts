import { describe, expect, it } from 'vite-plus/test';
import { filterSourcesByPlaceAndYear } from './source-place-year-filter';

const source = (
    id: number,
    year: number | null,
    parish_label: string | null,
    locality_name: string | null,
) => ({ id, year, parish_label, locality_name });

const lubomlSeat = source(1, 1900, 'Parafia św. Jana', 'Luboml');
const parishNamed = source(2, 1901, 'Parafia Luboml', null);
const otherYear = source(3, 1850, 'Parafia św. Jana', 'Luboml');
const noYear = source(4, null, 'Parafia św. Jana', 'Luboml');
const noPlace = source(5, 1900, null, null);
const elsewhere = source(6, 1900, 'Parafia X', 'Kraków');

const gravestone = source(7, 1950, null, 'Luboml');

const all = [lubomlSeat, parishNamed, otherYear, noYear, noPlace, elsewhere];
const none = { place: '', year: '' };

describe('filterSourcesByPlaceAndYear', () => {
    it('returns everything for blank filters', () => {
        expect(filterSourcesByPlaceAndYear(all, none)).toEqual({
            visible: all,
            hiddenUndescribedCount: 0,
        });
        expect(
            filterSourcesByPlaceAndYear(all, { place: '  ', year: ' ' })
                .visible,
        ).toEqual(all);
    });

    it('ignores a year that is not an integer', () => {
        expect(
            filterSourcesByPlaceAndYear(all, { place: '', year: 'abc' })
                .visible,
        ).toEqual(all);
    });

    it('matches the phrase in the locality name', () => {
        const { visible } = filterSourcesByPlaceAndYear(
            [lubomlSeat, elsewhere],
            {
                place: 'Luboml',
                year: '',
            },
        );

        expect(visible).toEqual([lubomlSeat]);
    });

    it('matches the phrase in the parish name', () => {
        const { visible } = filterSourcesByPlaceAndYear(
            [parishNamed, elsewhere],
            {
                place: 'Luboml',
                year: '',
            },
        );

        expect(visible).toEqual([parishNamed]);
    });

    it('matches the seat in the label of a parish without a locality of its own', () => {
        const seated = source(
            8,
            1900,
            'Płock św. Bartłomieja (rzym.-kat.)',
            null,
        );
        const { visible } = filterSourcesByPlaceAndYear([seated, elsewhere], {
            place: 'płock',
            year: '',
        });

        expect(visible).toEqual([seated]);
    });

    it('finds a gravestone by the place of its cemetery and its year', () => {
        const { visible } = filterSourcesByPlaceAndYear(
            [gravestone, lubomlSeat, elsewhere],
            { place: 'lubo', year: '1950' },
        );

        expect(visible).toEqual([gravestone]);
    });

    it('ignores letter case and surrounding spaces', () => {
        const { visible } = filterSourcesByPlaceAndYear([lubomlSeat], {
            place: ' luboml ',
            year: '',
        });

        expect(visible).toEqual([lubomlSeat]);
    });

    it('matches the exact year only', () => {
        const { visible } = filterSourcesByPlaceAndYear(
            [lubomlSeat, otherYear],
            {
                place: '',
                year: '1900',
            },
        );

        expect(visible).toEqual([lubomlSeat]);
    });

    it('hides and counts a source without a year when a year is active', () => {
        expect(
            filterSourcesByPlaceAndYear([lubomlSeat, noYear], {
                place: '',
                year: '1900',
            }),
        ).toEqual({ visible: [lubomlSeat], hiddenUndescribedCount: 1 });
    });

    it('hides and counts a source without a place when a place is active', () => {
        expect(
            filterSourcesByPlaceAndYear([lubomlSeat, noPlace], {
                place: 'Luboml',
                year: '',
            }),
        ).toEqual({ visible: [lubomlSeat], hiddenUndescribedCount: 1 });
    });

    it('hides but does not count a source with a different year', () => {
        expect(
            filterSourcesByPlaceAndYear([otherYear], {
                place: '',
                year: '1900',
            }),
        ).toEqual({ visible: [], hiddenUndescribedCount: 0 });
    });

    it('keeps the input order', () => {
        const { visible } = filterSourcesByPlaceAndYear(
            [elsewhere, lubomlSeat, noPlace],
            { place: '', year: '1900' },
        );

        expect(visible.map((s) => s.id)).toEqual([6, 1, 5]);
    });

    it('finds a gravestone by the name of its cemetery and by its locality', () => {
        const named = {
            ...source(8, 1950, null, 'Luboml'),
            cemetery_label: 'Cmentarz parafialny, Luboml',
        };
        const unnamed = {
            ...source(9, 1950, null, 'Chełm'),
            cemetery_label: 'cmentarz Chełm',
        };

        expect(
            filterSourcesByPlaceAndYear([named, unnamed], {
                place: 'parafialny',
                year: '',
            }).visible,
        ).toEqual([named]);
        expect(
            filterSourcesByPlaceAndYear([named, unnamed], {
                place: 'luboml',
                year: '',
            }).visible,
        ).toEqual([named]);
        expect(
            filterSourcesByPlaceAndYear([named, unnamed], {
                place: 'cmentarz chełm',
                year: '',
            }).visible,
        ).toEqual([unnamed]);
    });
});
