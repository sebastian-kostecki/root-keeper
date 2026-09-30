import { describe, expect, it } from 'vite-plus/test';
import { getInitials } from './use-initials';

describe('getInitials', () => {
    it('takes the first letters of the first and last word', () => {
        expect(getInitials('Jan Kowalski')).toBe('JK');
        expect(getInitials('Anna Maria Nowak')).toBe('AN');
    });

    it('uses one letter for a single word', () => {
        expect(getInitials('jan')).toBe('J');
    });

    it('skips words that do not start with a letter or digit', () => {
        expect(getInitials('Demo drzewa (pusty)')).toBe('DD');
        expect(getInitials('Jan - Kowalski')).toBe('JK');
    });

    it('returns an empty string when no word qualifies', () => {
        expect(getInitials('')).toBe('');
        expect(getInitials('(…)')).toBe('');
    });
});
