import { describe, expect, it } from 'vite-plus/test';
import { formatYearRange } from './year-range';

describe('formatYearRange', () => {
    it('joins both years with an en dash', () => {
        expect(formatYearRange(1900, 1910)).toBe('1900–1910');
    });

    it('shows only the start', () => {
        expect(formatYearRange(1900, null)).toBe('od 1900');
    });

    it('shows only the end', () => {
        expect(formatYearRange(null, 1910)).toBe('do 1910');
    });

    it('is empty without years', () => {
        expect(formatYearRange(null, null)).toBe('');
    });
});
