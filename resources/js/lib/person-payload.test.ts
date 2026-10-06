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
        residence: null,
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
