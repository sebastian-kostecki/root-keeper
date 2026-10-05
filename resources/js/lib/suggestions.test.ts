import { describe, expect, it } from 'vite-plus/test';
import { canOfferCreate, filterSuggestions } from './suggestions';

const identity = (value: string) => value;

describe('filterSuggestions', () => {
    it('finds a Polish name regardless of case', () => {
        expect(filterSuggestions(['Łódź', 'Kraków'], 'łódź', identity)).toEqual(
            ['Łódź'],
        );
    });

    it('matches a fragment inside the label', () => {
        expect(
            filterSuggestions(['Św. Jana', 'Św. Piotra'], 'jana', identity),
        ).toEqual(['Św. Jana']);
    });

    it('puts labels starting with the query before those containing it', () => {
        expect(
            filterSuggestions(['Św. Jana', 'Janów', 'Anna'], 'jan', identity),
        ).toEqual(['Janów', 'Św. Jana']);
    });

    it('returns every option, in Polish order, for a blank query', () => {
        expect(
            filterSuggestions(['Mielec', 'Łódź', 'Lwów'], '  ', identity),
        ).toEqual(['Lwów', 'Łódź', 'Mielec']);
    });

    it('caps the result at the limit', () => {
        expect(filterSuggestions(['a', 'b', 'c'], '', identity, 2)).toEqual([
            'a',
            'b',
        ]);
    });
});

describe('canOfferCreate', () => {
    it('is false for an exact match ignoring case', () => {
        expect(canOfferCreate(['Kraków'], 'KRAKÓW', identity)).toBe(false);
    });

    it('is true when no option has that name', () => {
        expect(canOfferCreate(['Kraków'], 'Krak', identity)).toBe(true);
    });

    it('is false for a blank query', () => {
        expect(canOfferCreate(['Kraków'], '   ', identity)).toBe(false);
    });
});
