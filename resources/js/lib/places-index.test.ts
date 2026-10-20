import { describe, expect, it } from 'vite-plus/test';
import type { Locality, Parish } from '@/types';
import { otherKindHint, placesCounts, withPlacesQuery } from './places-index';

const locality = (id: number, name: string): Locality => ({
    id,
    name,
    kind: null,
    kind_label: null,
    latitude: null,
    longitude: null,
    historical_affiliation: null,
    description: null,
    is_defunct: false,
    defunct_note: null,
});

const parish = (
    id: number,
    name: string,
    localityName: string | null,
): Parish => ({
    id,
    name,
    religion: null,
    religion_label: null,
    locality_id: null,
    locality_name: localityName,
    label: name,
});

const localities = [
    locality(1, 'Tarnów'),
    locality(2, 'Tarnowskie Góry'),
    locality(3, 'Kraków'),
    locality(4, 'Łódź'),
];

const parishes = [
    parish(1, 'Św. Marii', 'Tarnów'),
    parish(2, 'Św. Józefa', 'Kraków'),
];

describe('placesCounts', () => {
    it('gives the size of each list for a blank search', () => {
        expect(placesCounts(localities, parishes, '')).toEqual({
            miejscowosci: 4,
            parafie: 2,
        });
    });

    it('counts the hits in both lists', () => {
        expect(placesCounts(localities, parishes, 'Tarnów')).toEqual({
            miejscowosci: 1,
            parafie: 1,
        });
    });

    it('counts a parish by its seat', () => {
        expect(placesCounts(localities, parishes, 'św. marii')).toEqual({
            miejscowosci: 0,
            parafie: 1,
        });
    });
});

describe('otherKindHint', () => {
    it('gives no hint for a blank search', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 0, parafie: 3 },
                '  ',
            ),
        ).toBeNull();
    });

    it('points to the other list when the current one has no hits', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 0, parafie: 1 },
                'św. marii',
            ),
        ).toEqual({ kind: 'parafie', count: 1 });
    });

    it('points to the localities from the parishes', () => {
        expect(
            otherKindHint('parafie', { miejscowosci: 2, parafie: 0 }, 'Tarnów'),
        ).toEqual({ kind: 'miejscowosci', count: 2 });
    });

    it('gives no hint when nothing matches in either list', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 0, parafie: 0 },
                'xyz',
            ),
        ).toBeNull();
    });

    it('gives no hint when the current list has hits', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 1, parafie: 1 },
                'Tarnów',
            ),
        ).toBeNull();
    });
});

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
