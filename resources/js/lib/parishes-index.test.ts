import { describe, expect, it } from 'vite-plus/test';
import type { Parish } from '@/types';
import { filterParishes, sortParishes } from './parishes-index';

function parish(id: number, name: string, locality: string | null): Parish {
    return {
        id,
        name,
        religion: null,
        religion_label: null,
        locality_id: locality === null ? null : id,
        locality_name: locality,
        label: name,
    };
}

const names = (parishes: Parish[]) => parishes.map((item) => item.name);

describe('filterParishes', () => {
    const list = [
        parish(1, 'Św. Jana', 'Łódź'),
        parish(2, 'Św. Piotra', null),
        parish(3, 'Wniebowzięcia', 'Kraków'),
    ];

    it('finds a parish by name regardless of case', () => {
        expect(names(filterParishes(list, 'JANA'))).toEqual(['Św. Jana']);
    });

    it('finds a parish by its seat', () => {
        expect(names(filterParishes(list, 'łódź'))).toEqual(['Św. Jana']);
    });

    it('matches every parish for a blank query', () => {
        expect(filterParishes(list, '  ')).toHaveLength(3);
    });
});

describe('sortParishes', () => {
    it('orders Polish names alphabetically, ties by id', () => {
        const list = [
            parish(3, 'Mielec', null),
            parish(2, 'Łódź', null),
            parish(1, 'Łódź', null),
        ];

        expect(sortParishes(list).map((item) => item.id)).toEqual([1, 2, 3]);
    });
});
