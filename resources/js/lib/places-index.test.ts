import { describe, expect, it } from 'vite-plus/test';
import type { Cemetery, Locality, Parish } from '@/types';
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
    name: null,
    kind: 'parish',
    kind_label: 'parafia',
    dedication: name,
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

const cemetery = (
    id: number,
    name: string | null,
    localityName: string,
): Cemetery => ({
    id,
    name,
    locality_id: id,
    locality_name: localityName,
    manager: null,
    manager_label: null,
    religion: null,
    religion_label: null,
    description: null,
    label:
        name === null ? `cmentarz ${localityName}` : `${name}, ${localityName}`,
});

const cemeteries = [
    cemetery(1, null, 'Tarnów'),
    cemetery(2, 'Rakowicki', 'Kraków'),
    cemetery(3, 'Parafialny', 'Kraków'),
];

describe('placesCounts', () => {
    it('gives the size of each list for a blank search', () => {
        expect(placesCounts(localities, parishes, cemeteries, '')).toEqual({
            miejscowosci: 4,
            parafie: 2,
            cmentarze: 3,
        });
    });

    it('counts the hits in all three lists', () => {
        expect(
            placesCounts(localities, parishes, cemeteries, 'Tarnów'),
        ).toEqual({ miejscowosci: 1, parafie: 1, cmentarze: 1 });
    });

    it('counts a parish by its seat', () => {
        expect(
            placesCounts(localities, parishes, cemeteries, 'św. marii'),
        ).toEqual({ miejscowosci: 0, parafie: 1, cmentarze: 0 });
    });

    it('counts a cemetery by its name', () => {
        expect(
            placesCounts(localities, parishes, cemeteries, 'rakowicki'),
        ).toEqual({ miejscowosci: 0, parafie: 0, cmentarze: 1 });
    });
});

describe('otherKindHint', () => {
    it('gives no hint for a blank search', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 0, parafie: 3, cmentarze: 1 },
                '  ',
            ),
        ).toEqual([]);
    });

    it('points to the other list when the current one has no hits', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 0, parafie: 1, cmentarze: 0 },
                'św. marii',
            ),
        ).toEqual([{ kind: 'parafie', count: 1 }]);
    });

    it('lists every other tab with hits, in tab order', () => {
        expect(
            otherKindHint(
                'parafie',
                { miejscowosci: 2, parafie: 0, cmentarze: 4 },
                'Tarnów',
            ),
        ).toEqual([
            { kind: 'miejscowosci', count: 2 },
            { kind: 'cmentarze', count: 4 },
        ]);
    });

    it('leaves out tabs without hits', () => {
        expect(
            otherKindHint(
                'cmentarze',
                { miejscowosci: 0, parafie: 3, cmentarze: 0 },
                'x',
            ),
        ).toEqual([{ kind: 'parafie', count: 3 }]);
    });

    it('gives no hint when nothing matches anywhere', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 0, parafie: 0, cmentarze: 0 },
                'xyz',
            ),
        ).toEqual([]);
    });

    it('gives no hint when the current list has hits', () => {
        expect(
            otherKindHint(
                'miejscowosci',
                { miejscowosci: 1, parafie: 1, cmentarze: 1 },
                'Tarnów',
            ),
        ).toEqual([]);
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

    it('sets the cemetery kind and keeps the cemetery panel', () => {
        expect(
            withPlacesQuery('/places?cemetery=3', { kind: 'cmentarze' }),
        ).toBe('/places?cemetery=3&kind=cmentarze');
    });

    it('changes only the given keys', () => {
        expect(withPlacesQuery('/places?kind=parafie&q=jan', {})).toBe(
            '/places?kind=parafie&q=jan',
        );
    });
});
