import { describe, expect, it } from 'vite-plus/test';
import type { Locality } from '@/types';
import {
    buildLocalitiesQuery,
    filterLocalities,
    sortLocalities,
} from './localities-index';

function locality(id: number, name: string): Locality {
    return { id, name, description: null };
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

describe('buildLocalitiesQuery', () => {
    it('omits a blank search and trims the rest', () => {
        expect(buildLocalitiesQuery({ q: '  ' })).toEqual({});
        expect(buildLocalitiesQuery({ q: ' łódź ' })).toEqual({ q: 'łódź' });
    });
});
