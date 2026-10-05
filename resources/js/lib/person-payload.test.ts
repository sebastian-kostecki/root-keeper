import { describe, expect, it } from 'vite-plus/test';
import type { PersonFormData } from '@/types';
import { mergeSectionIntoPayload } from './person-payload';

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
