import { describe, expect, it } from 'vite-plus/test';
import type { PersonFormData } from '@/types';
import {
    eventPayloadFromValues,
    mergeSectionIntoPayload,
} from './person-payload';

function personForm(): PersonFormData {
    return {
        id: 1,
        display_name: 'Anna Kowalska z d. Nowak',
        first_name: 'Anna',
        last_name: 'Kowalska',
        maiden_name: 'Nowak',
        gender: 'female',
        residences: [],
        religion: null,
        is_deceased: true,
        birth: null,
        death: null,
        burial: { locality: { id: 7, name: 'Luboml' } },
    };
}

describe('mergeSectionIntoPayload: maiden name', () => {
    it.each(['birth', 'death'] as const)(
        'keeps the maiden name when the %s section is saved',
        (section) => {
            const payload = mergeSectionIntoPayload(personForm(), section, {
                [`${section}.year`]: '1950',
                is_deceased: '1',
            });

            expect(payload.maiden_name).toBe('Nowak');
        },
    );

    it('takes the maiden name from the personal section values', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'personal', {
            first_name: 'Anna',
            last_name: 'Kowalska',
            maiden_name: '',
        });

        expect(payload.maiden_name).toBeNull();
    });
});

describe('mergeSectionIntoPayload: burial place', () => {
    it.each(['personal', 'birth'] as const)(
        'keeps the burial locality of a deceased person when the %s section is saved',
        (section) => {
            const payload = mergeSectionIntoPayload(personForm(), section, {
                first_name: 'Anna',
                'birth.year': '1950',
            });

            expect(payload.burial).toEqual({ locality_id: 7 });
        },
    );

    it('takes the burial locality from the death section values', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'death', {
            is_deceased: '1',
            'death.locality_id': '3',
            'burial.locality_id': '9',
        });

        expect(payload.death?.locality_id).toBe(3);
        expect(payload.burial).toEqual({ locality_id: 9 });
    });

    it('sends neither death nor burial when the death is unchecked', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'death', {
            'burial.locality_id': '9',
        });

        expect(payload.death).toBeUndefined();
        expect(payload.burial).toBeUndefined();
    });
});

describe('eventPayloadFromValues', () => {
    it('reads the locality id of the prefix', () => {
        expect(
            eventPayloadFromValues('marriage', {
                'marriage.locality_id': '12',
            }).locality_id,
        ).toBe(12);
        expect(eventPayloadFromValues('marriage', {}).locality_id).toBeNull();
    });
});

describe('residences', () => {
    const withResidences = (): PersonFormData => ({
        ...personForm(),
        residences: [
            {
                id: 4,
                locality: { id: 9, name: 'Zakopane' },
                year_from: 1920,
                year_to: null,
            },
        ],
    });

    it.each(['personal', 'birth', 'death'] as const)(
        'carries the residences of personForm when the %s section is saved',
        (section) => {
            const payload = mergeSectionIntoPayload(withResidences(), section, {
                is_deceased: '1',
            });

            expect(payload.residences).toEqual([
                { locality_id: 9, year_from: 1920, year_to: null },
            ]);
        },
    );

    it('reads rows by numeric index and keeps empty rows in place', () => {
        const payload = mergeSectionIntoPayload(
            withResidences(),
            'residences',
            {
                'residences.10.locality_id': '5',
                'residences.10.year_from': '',
                'residences.10.year_to': '1950',
                'residences.2.locality_id': '',
                'residences.2.year_from': '',
                'residences.2.year_to': '',
                'residences.0.locality_id': '3',
                'residences.0.year_from': '1900',
                'residences.0.year_to': '',
            },
        );

        expect(payload.residences).toEqual([
            { locality_id: 3, year_from: 1900, year_to: null },
            { locality_id: null, year_from: null, year_to: null },
            { locality_id: 5, year_from: null, year_to: 1950 },
        ]);
    });
});
