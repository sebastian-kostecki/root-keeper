import { describe, expect, it } from 'vite-plus/test';
import { pairDetailLines } from './pair-details';
import type { DateQualifier } from '@/lib/partial-date';
import type { SheetPersonDetails } from '@/types';

const parts = (
    year: number | null,
    month: number | null = null,
    day: number | null = null,
    qualifier: DateQualifier = 'exact',
    until_year: number | null = null,
) => ({ year, month, day, qualifier, until_year });

describe('pairDetailLines', () => {
    it('lists birth, death and burial with full data', () => {
        const details: SheetPersonDetails = {
            birth: { date_parts: parts(1948, 3, 12), place: 'Kraków' },
            death: { date_parts: parts(2020, 5, 1), place: 'Warszawa' },
            burial_place: 'Zakopane',
        };

        expect(pairDetailLines(details, true)).toEqual([
            'ur. 12 marca 1948, Kraków',
            'zm. 1 maja 2020, Warszawa',
            'poch. Zakopane',
        ]);
    });

    it('prefixes an approximate date with "około"', () => {
        const details: SheetPersonDetails = {
            birth: {
                date_parts: parts(1850, null, null, 'approximate'),
                place: null,
            },
            death: null,
            burial_place: null,
        };

        expect(pairDetailLines(details, false)).toEqual(['ur. około 1850']);
    });

    it('reads a range date as "między … a …"', () => {
        const details: SheetPersonDetails = {
            birth: {
                date_parts: parts(1805, null, null, 'range', 1811),
                place: 'Wieś',
            },
            death: null,
            burial_place: null,
        };

        expect(pairDetailLines(details, false)).toEqual([
            'ur. między 1805 a 1811, Wieś',
        ]);
    });

    it('shows a year without place', () => {
        const details: SheetPersonDetails = {
            birth: { date_parts: parts(1948), place: null },
            death: null,
            burial_place: null,
        };

        expect(pairDetailLines(details, false)).toEqual(['ur. 1948']);
    });

    it('shows a place without date', () => {
        const details: SheetPersonDetails = {
            birth: { date_parts: parts(null), place: 'Kraków' },
            death: null,
            burial_place: null,
        };

        expect(pairDetailLines(details, false)).toEqual(['ur. Kraków']);
    });

    it('omits the death line for a living person', () => {
        const details: SheetPersonDetails = {
            birth: { date_parts: parts(1990), place: null },
            death: { date_parts: parts(2020), place: 'Warszawa' },
            burial_place: null,
        };

        expect(pairDetailLines(details, false)).toEqual(['ur. 1990']);
    });

    it('returns no lines for an empty set', () => {
        expect(
            pairDetailLines(
                { birth: null, death: null, burial_place: null },
                true,
            ),
        ).toEqual([]);
    });
});
