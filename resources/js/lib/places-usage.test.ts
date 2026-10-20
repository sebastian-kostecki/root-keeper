import { describe, expect, it } from 'vite-plus/test';
import {
    localityUsageNominative,
    localityUsagePhrase,
    noLocalityUsage,
    parishUsageNominative,
    parishUsagePhrase,
} from '@/lib/places-usage';
import type { LocalityUsage } from '@/types';

function usage(partial: Partial<LocalityUsage>): LocalityUsage {
    const facts = partial.facts ?? 0;
    const parishes = partial.parishes ?? 0;
    const residences = partial.residences ?? 0;
    const cemeteries = partial.cemeteries ?? 0;
    const gravestones = partial.gravestones ?? 0;

    return {
        facts,
        parishes,
        residences,
        cemeteries,
        gravestones,
        total: facts + parishes + residences + cemeteries + gravestones,
    };
}

const mixed = usage({
    facts: 3,
    parishes: 1,
    residences: 2,
    cemeteries: 2,
    gravestones: 1,
});

describe('parishUsagePhrase', () => {
    it.each([
        [1, '1 akcie'],
        [2, '2 aktach'],
        [4, '4 aktach'],
        [5, '5 aktach'],
        [12, '12 aktach'],
        [22, '22 aktach'],
    ])('odmienia %i akt w miejscowniku', (count, expected) => {
        expect(parishUsagePhrase(count)).toBe(expected);
    });
});

describe('parishUsageNominative', () => {
    it('zwraca „brak” dla zera', () => {
        expect(parishUsageNominative(0)).toBe('brak');
    });

    it.each([
        [1, '1 akt'],
        [3, '3 akty'],
        [5, '5 aktów'],
        [13, '13 aktów'],
    ])('odmienia %i akt w mianowniku', (count, expected) => {
        expect(parishUsageNominative(count)).toBe(expected);
    });
});

describe('localityUsagePhrase', () => {
    it.each([
        [{ facts: 1 }, '1 fakcie'],
        [{ parishes: 1 }, '1 parafii'],
        [{ residences: 1 }, '1 zamieszkaniu'],
        [{ cemeteries: 1 }, '1 cmentarzu'],
        [{ cemeteries: 5 }, '5 cmentarzach'],
        [{ gravestones: 1 }, '1 nagrobku'],
        [{ facts: 5 }, '5 faktach'],
        [{ gravestones: 22 }, '22 nagrobkach'],
    ])('odmienia %j w miejscowniku', (partial, expected) => {
        expect(localityUsagePhrase(usage(partial))).toBe(expected);
    });

    it('pomija zera i łączy pozycje przez „i” przed ostatnią', () => {
        expect(localityUsagePhrase(mixed)).toBe(
            '3 faktach, 1 parafii, 2 zamieszkaniach, 2 cmentarzach i 1 nagrobku',
        );
        expect(localityUsagePhrase(usage({ facts: 3, gravestones: 2 }))).toBe(
            '3 faktach i 2 nagrobkach',
        );
    });

    it('zwraca pusty tekst, gdy nic nie jest używane', () => {
        expect(localityUsagePhrase(noLocalityUsage)).toBe('');
    });
});

describe('localityUsageNominative', () => {
    it('zwraca „brak” dla zera', () => {
        expect(localityUsageNominative(noLocalityUsage)).toBe('brak');
    });

    it('odmienia w mianowniku i łączy pozycje', () => {
        expect(localityUsageNominative(mixed)).toBe(
            '3 fakty, 1 parafia, 2 zamieszkania, 2 cmentarze i 1 nagrobek',
        );
    });

    it.each([
        [{ facts: 12 }, '12 faktów'],
        [{ parishes: 5 }, '5 parafii'],
        [{ residences: 22 }, '22 zamieszkania'],
        [{ cemeteries: 1 }, '1 cmentarz'],
        [{ cemeteries: 5 }, '5 cmentarzy'],
    ])('odmienia %j w mianowniku', (partial, expected) => {
        expect(localityUsageNominative(usage(partial))).toBe(expected);
    });
});
