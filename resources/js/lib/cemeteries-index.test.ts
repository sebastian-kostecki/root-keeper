import { describe, expect, it } from 'vite-plus/test';
import type { Cemetery } from '@/types';
import { filterCemeteries, sortCemeteries } from './cemeteries-index';

function cemetery(id: number, name: string | null, locality: string): Cemetery {
    return {
        id,
        name,
        locality_id: id,
        locality_name: locality,
        manager: null,
        manager_label: null,
        religion: null,
        religion_label: null,
        description: null,
        label: name === null ? `cmentarz ${locality}` : `${name}, ${locality}`,
    };
}

const ids = (cemeteries: Cemetery[]) => cemeteries.map((item) => item.id);

describe('filterCemeteries', () => {
    const list = [
        cemetery(1, 'Cmentarz parafialny', 'Luboml'),
        cemetery(2, null, 'Luboml'),
        cemetery(3, 'Rakowicki', 'Kraków'),
    ];

    it('finds a cemetery by name regardless of case', () => {
        expect(ids(filterCemeteries(list, 'RAKOWICKI'))).toEqual([3]);
    });

    it('finds cemeteries by their locality', () => {
        expect(ids(filterCemeteries(list, 'luboml'))).toEqual([1, 2]);
    });

    it('finds a nameless cemetery by its label', () => {
        expect(ids(filterCemeteries(list, 'cmentarz luboml'))).toEqual([2]);
    });

    it('matches every cemetery for a blank query', () => {
        expect(filterCemeteries(list, '  ')).toHaveLength(3);
    });
});

describe('sortCemeteries', () => {
    it('orders by locality, then name, ties by id', () => {
        const list = [
            cemetery(5, 'B', 'Mielec'),
            cemetery(4, 'A', 'Mielec'),
            cemetery(3, 'Łąkowy', 'Łódź'),
            cemetery(2, null, 'Łódź'),
            cemetery(1, null, 'Łódź'),
        ];

        expect(ids(sortCemeteries(list))).toEqual([1, 2, 3, 4, 5]);
    });
});
