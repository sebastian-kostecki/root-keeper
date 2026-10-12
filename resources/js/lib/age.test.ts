import { describe, expect, it } from 'vite-plus/test';
import { formatAge } from './age';

describe('formatAge', () => {
    it('declines years', () => {
        expect(formatAge({ unit: 'years', value: 1 })).toBe('1 rok');
        expect(formatAge({ unit: 'years', value: 22 })).toBe('22 lata');
        expect(formatAge({ unit: 'years', value: 72 })).toBe('72 lata');
        expect(formatAge({ unit: 'years', value: 45 })).toBe('45 lat');
        expect(formatAge({ unit: 'years', value: 112 })).toBe('112 lat');
    });

    it('abbreviates months', () => {
        expect(formatAge({ unit: 'months', value: 2 })).toBe('2 mies.');
    });

    it('declines days and handles zero', () => {
        expect(formatAge({ unit: 'days', value: 1 })).toBe('1 dzień');
        expect(formatAge({ unit: 'days', value: 17 })).toBe('17 dni');
        expect(formatAge({ unit: 'days', value: 0 })).toBe('poniżej dnia');
    });

    it('describes a person who lived less than a year', () => {
        expect(formatAge({ unit: 'under_year', value: null })).toBe(
            'poniżej roku',
        );
    });
});
