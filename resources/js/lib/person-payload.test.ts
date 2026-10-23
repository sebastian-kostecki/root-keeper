import { describe, expect, it } from 'vite-plus/test';
import type { PersonFormData } from '@/types';
import {
    eventPayloadFromValues,
    marriageFieldDefaults,
    marriageSaveRequest,
    mergeSectionIntoPayload,
} from './person-payload';

function personForm(): PersonFormData {
    return {
        id: 1,
        display_name: 'Anna Kowalska',
        first_name: 'Anna',
        last_name: 'Kowalska',
        gender: 'female',
        residences: [],
        religion: null,
        is_deceased: true,
        birth: null,
        death: null,
        burial: {
            cemetery: {
                id: 4,
                label: 'cmentarz Luboml',
                locality_id: 7,
                locality_name: 'Luboml',
            },
        },
    };
}

describe('mergeSectionIntoPayload: single surname', () => {
    it('has no maiden_name key in the person payload', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'birth', {
            'birth.year': '1950',
            is_deceased: '1',
        });

        expect(payload).not.toHaveProperty('maiden_name');
        expect(payload.last_name).toBe('Kowalska');
    });

    it('takes last_name from the personal section values', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'personal', {
            first_name: 'Anna',
            last_name: 'Nowak',
        });

        expect(payload).not.toHaveProperty('maiden_name');
        expect(payload.last_name).toBe('Nowak');
    });
});

describe('mergeSectionIntoPayload: burial place', () => {
    it.each(['personal', 'birth'] as const)(
        'keeps the burial cemetery of a deceased person when the %s section is saved',
        (section) => {
            const payload = mergeSectionIntoPayload(personForm(), section, {
                first_name: 'Anna',
                'birth.year': '1950',
            });

            expect(payload.burial).toEqual({ cemetery_id: 4 });
        },
    );

    it('takes the burial cemetery from the death section values', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'death', {
            is_deceased: '1',
            'death.locality_id': '3',
            'burial.cemetery_id': '12',
        });

        expect(payload.death?.locality_id).toBe(3);
        expect(payload.burial).toEqual({ cemetery_id: 12 });
    });

    it('clears the burial cemetery when the death section has none', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'death', {
            is_deceased: '1',
            'burial.cemetery_id': '',
        });

        expect(payload.burial).toEqual({ cemetery_id: null });
    });

    it('sends neither death nor burial when the death is unchecked', () => {
        const payload = mergeSectionIntoPayload(personForm(), 'death', {
            'burial.cemetery_id': '9',
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

    it('carries a range into the payload', () => {
        expect(
            eventPayloadFromValues('birth', {
                'birth.year': '1805',
                'birth.qualifier': 'range',
                'birth.until_year': '1811',
            }),
        ).toMatchObject({ year: 1805, qualifier: 'range', until_year: 1811 });
    });

    it('carries "przed" into the payload and defaults to exact', () => {
        expect(
            eventPayloadFromValues('marriage', {
                'marriage.year': '1809',
                'marriage.qualifier': 'before',
                'marriage.until_year': '',
            }),
        ).toMatchObject({ year: 1809, qualifier: 'before', until_year: null });
        expect(eventPayloadFromValues('birth', {}).qualifier).toBe('exact');
    });

    it('sends the stored qualifier back on a section save', () => {
        const form = personForm();
        form.birth = {
            year: 1805,
            month: null,
            day: null,
            qualifier: 'range',
            until_year: 1811,
            locality: null,
            is_confirmed: false,
        };

        expect(mergeSectionIntoPayload(form, 'death', {}).birth).toMatchObject({
            qualifier: 'range',
            until_year: 1811,
        });
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

describe('marriage card', () => {
    it('always sends the form, null when empty', () => {
        expect(
            marriageSaveRequest(5, { 'marriage.form': 'civil' })[1].marriage
                .form,
        ).toBe('civil');
        expect(
            marriageSaveRequest(5, { 'marriage.form': '' })[1].marriage.form,
        ).toBeNull();
        expect(marriageSaveRequest(5, {})[1].marriage.form).toBeNull();
    });

    it('carries the stored form into the edit defaults', () => {
        const defaults = marriageFieldDefaults({
            date_parts: {
                year: 2001,
                month: null,
                day: null,
                qualifier: 'exact',
                until_year: null,
            },
            locality_id: null,
            place: null,
            marriage_form: 'religious',
        });

        expect(defaults.form).toBe('religious');
    });
});
