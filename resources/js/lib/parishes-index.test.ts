import { describe, expect, it } from 'vite-plus/test';
import type { LocalityOption, Parish } from '@/types';
import { filterParishes, seatNamed, sortParishes } from './parishes-index';

function parish(
    id: number,
    label: string,
    locality: string | null,
    legacyName: string | null = null,
): Parish {
    return {
        id,
        name: legacyName,
        kind: 'parish',
        kind_label: 'parafia',
        dedication: null,
        religion: null,
        religion_label: null,
        locality_id: locality === null ? null : id,
        locality_name: locality,
        label,
    };
}

const labels = (parishes: Parish[]) => parishes.map((item) => item.label);

describe('filterParishes', () => {
    const list = [
        parish(1, 'Łódź, św. Jana', 'Łódź'),
        parish(2, 'Św. Piotra', null, 'Parafia św. Piotra w Radomiu'),
        parish(3, 'Kraków, Fara', 'Kraków'),
    ];

    it('finds a record by its label regardless of case', () => {
        expect(labels(filterParishes(list, 'JANA'))).toEqual([
            'Łódź, św. Jana',
        ]);
    });

    it('finds a new record by its distinguisher', () => {
        expect(labels(filterParishes(list, 'fara'))).toEqual(['Kraków, Fara']);
    });

    it('finds an old record by its former name', () => {
        expect(labels(filterParishes(list, 'radomiu'))).toEqual(['Św. Piotra']);
    });

    it('finds a record by its seat', () => {
        expect(labels(filterParishes(list, 'łódź'))).toEqual([
            'Łódź, św. Jana',
        ]);
    });

    it('matches every record for a blank query', () => {
        expect(filterParishes(list, '  ')).toHaveLength(3);
    });
});

describe('sortParishes', () => {
    it('orders Polish labels alphabetically, ties by id', () => {
        const list = [
            parish(3, 'Mielec', null),
            parish(2, 'Łódź', null),
            parish(1, 'Łódź', null),
        ];

        expect(sortParishes(list).map((item) => item.id)).toEqual([1, 2, 3]);
    });
});

describe('seatNamed', () => {
    const localities: LocalityOption[] = [
        { id: 1, name: 'Płock' },
        { id: 2, name: 'Łódź' },
    ];

    it('finds the locality named like the text, ignoring case and surrounding spaces', () => {
        expect(seatNamed(localities, '  pŁOCK ')).toEqual(localities[0]);
        expect(seatNamed(localities, 'łódź')).toEqual(localities[1]);
    });

    it('returns null for an unknown or blank text', () => {
        expect(seatNamed(localities, 'Kraków')).toBeNull();
        expect(seatNamed(localities, '   ')).toBeNull();
    });

    it('does not match a locality by a part of its name', () => {
        expect(seatNamed(localities, 'Pło')).toBeNull();
    });
});
