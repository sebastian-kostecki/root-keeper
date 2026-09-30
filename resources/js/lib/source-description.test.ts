import { describe, expect, it } from 'vite-plus/test';
import { formatSourceMeta, showsUploadDate } from './source-description';

const empty = { record_type_label: null, year: null, provenance: null };

describe('formatSourceMeta', () => {
    it('joins type and year', () => {
        expect(
            formatSourceMeta({
                ...empty,
                record_type_label: 'urodzenia',
                year: 1878,
            }),
        ).toBe('urodzenia · 1878');
    });

    it('shows only the year', () => {
        expect(formatSourceMeta({ ...empty, year: 1878 })).toBe('1878');
    });

    it('shows only the type', () => {
        expect(
            formatSourceMeta({ ...empty, record_type_label: 'urodzenia' }),
        ).toBe('urodzenia');
    });

    it('returns nothing when both are empty', () => {
        expect(formatSourceMeta(empty)).toBeNull();
    });
});

describe('showsUploadDate', () => {
    it('shows the date when typ, rok and pochodzenie are all empty', () => {
        expect(showsUploadDate(empty)).toBe(true);
    });

    it('hides the date when any of the three is set', () => {
        expect(showsUploadDate({ ...empty, year: 1878 })).toBe(false);
        expect(showsUploadDate({ ...empty, record_type_label: 'zgonu' })).toBe(
            false,
        );
        expect(showsUploadDate({ ...empty, provenance: 'Archiwum' })).toBe(
            false,
        );
    });
});
