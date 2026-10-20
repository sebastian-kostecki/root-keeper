import { describe, expect, it } from 'vite-plus/test';
import type { Locality } from '@/types';
import {
    filterLocalities,
    formatCoordinates,
    localityHint,
    openStreetMapUrl,
    sortLocalities,
} from './localities-index';

function locality(id: number, name: string): Locality {
    return {
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
    };
}

const names = (localities: Locality[]) => localities.map((item) => item.name);

describe('filterLocalities', () => {
    const list = [locality(1, 'Łódź'), locality(2, 'Kraków')];

    it('finds a Polish name regardless of case', () => {
        expect(names(filterLocalities(list, 'łódź'))).toEqual(['Łódź']);
        expect(names(filterLocalities(list, 'KRA'))).toEqual(['Kraków']);
    });

    it('matches every locality for a blank query', () => {
        expect(filterLocalities(list, '  ')).toHaveLength(2);
    });
});

describe('sortLocalities', () => {
    it('orders Polish names alphabetically', () => {
        const list = [
            locality(1, 'Mielec'),
            locality(2, 'Łódź'),
            locality(3, 'Lwów'),
        ];

        expect(names(sortLocalities(list))).toEqual(['Lwów', 'Łódź', 'Mielec']);
    });

    it('breaks ties by id', () => {
        const list = [locality(2, 'Kraków'), locality(1, 'Kraków')];

        expect(sortLocalities(list).map((item) => item.id)).toEqual([1, 2]);
    });
});

describe('localityHint', () => {
    it('marks a defunct locality', () => {
        expect(localityHint({ is_defunct: true })).toBe('nie istnieje');
    });

    it('has no note for an existing or unknown one', () => {
        expect(localityHint({ is_defunct: false })).toBeUndefined();
        expect(localityHint({})).toBeUndefined();
    });
});

describe('formatCoordinates', () => {
    it('joins latitude and longitude', () => {
        expect(formatCoordinates(50.0614, 19.9366)).toBe('50.0614, 19.9366');
    });

    it('keeps negative values and drops trailing zeros', () => {
        expect(formatCoordinates(-33.5, -70.0)).toBe('-33.5, -70');
    });

    it('is empty when a coordinate is missing', () => {
        expect(formatCoordinates(null, null)).toBe('');
        expect(formatCoordinates(50.1, null)).toBe('');
    });
});

describe('openStreetMapUrl', () => {
    it('builds a marker and map link', () => {
        expect(openStreetMapUrl(50.0614, 19.9366)).toBe(
            'https://www.openstreetmap.org/?mlat=50.0614&mlon=19.9366#map=14/50.0614/19.9366',
        );
    });

    it('keeps the sign of negative coordinates', () => {
        expect(openStreetMapUrl(-33.5, -70)).toBe(
            'https://www.openstreetmap.org/?mlat=-33.5&mlon=-70#map=14/-33.5/-70',
        );
    });
});
