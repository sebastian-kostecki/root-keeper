import { describe, expect, it } from 'vite-plus/test';
import { withPlacesQuery } from './places-index';

describe('withPlacesQuery', () => {
    it('keeps the open panel when the search changes', () => {
        expect(withPlacesQuery('/places?locality=3', { q: ' łódź ' })).toBe(
            '/places?locality=3&q=%C5%82%C3%B3d%C5%BA',
        );
    });

    it('removes only the search when it is blank', () => {
        expect(withPlacesQuery('/places?q=x&locality=new', { q: '  ' })).toBe(
            '/places?locality=new',
        );
    });

    it('keeps the parish panel and the kind when the search changes', () => {
        expect(
            withPlacesQuery('/places?kind=parafie&parish=3', { q: ' jan ' }),
        ).toBe('/places?kind=parafie&parish=3&q=jan');
    });

    it('removes the default kind and keeps the rest', () => {
        expect(
            withPlacesQuery('/places?kind=parafie&locality=3', {
                kind: 'miejscowosci',
            }),
        ).toBe('/places?locality=3');
    });

    it('sets the parish kind and keeps the search and the hash', () => {
        expect(
            withPlacesQuery('/places?q=jan&locality=3#top', {
                kind: 'parafie',
            }),
        ).toBe('/places?q=jan&locality=3&kind=parafie#top');
    });

    it('changes only the given keys', () => {
        expect(withPlacesQuery('/places?kind=parafie&q=jan', {})).toBe(
            '/places?kind=parafie&q=jan',
        );
    });
});
