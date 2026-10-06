import type { PersonFormData, PersonFormEvent } from '@/types';

/** Form values of one edited section, keyed by input name (`birth.year`, ...). */
export type SectionValues = Record<string, string>;

/** Sections that save through `PUT persons/{id}`. */
export type PersonSection = 'personal' | 'birth' | 'death';

export type PartialDatePayload = {
    year: number | null;
    month: number | null;
    day: number | null;
    is_approximate: boolean;
    locality_id: number | null;
};

export type PersonPayload = {
    first_name: string | null;
    last_name: string | null;
    maiden_name: string | null;
    gender: string | null;
    residence: string | null;
    religion: string | null;
    is_deceased: boolean;
    birth: PartialDatePayload;
    death?: PartialDatePayload;
    burial?: { locality_id: number | null };
};

function blankToNull(value: string | undefined): string | null {
    return value === undefined || value === '' ? null : value;
}

function toNumber(value: string | undefined): number | null {
    if (value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function eventToPayload(event: PersonFormEvent | null): PartialDatePayload {
    return {
        year: event?.year ?? null,
        month: event?.month ?? null,
        day: event?.day ?? null,
        is_approximate: event?.is_approximate ?? false,
        locality_id: event?.locality?.id ?? null,
    };
}

/** Reads `{prefix}.year|month|day|is_approximate|locality_id` out of form values. */
export function eventPayloadFromValues(
    prefix: 'birth' | 'death' | 'marriage',
    values: SectionValues,
): PartialDatePayload {
    return {
        year: toNumber(values[`${prefix}.year`]),
        month: toNumber(values[`${prefix}.month`]),
        day: toNumber(values[`${prefix}.day`]),
        is_approximate: values[`${prefix}.is_approximate`] === '1',
        locality_id: toNumber(values[`${prefix}.locality_id`]),
    };
}

/**
 * Maps the person form data to the `PUT persons/{id}` request shape. The
 * update is a full replace on the server, so every key must be present.
 */
export function personFormToPayload(personForm: PersonFormData): PersonPayload {
    const payload: PersonPayload = {
        first_name: personForm.first_name,
        last_name: personForm.last_name,
        maiden_name: personForm.maiden_name,
        gender: personForm.gender,
        residence: personForm.residence,
        religion: personForm.religion,
        is_deceased: personForm.is_deceased,
        birth: eventToPayload(personForm.birth),
    };

    if (personForm.is_deceased) {
        payload.death = eventToPayload(personForm.death);
        payload.burial = {
            locality_id: personForm.burial?.locality?.id ?? null,
        };
    }

    return payload;
}

/**
 * Full payload for saving one section: the current `personForm` with only that
 * section's keys overwritten by the edited values.
 */
export function mergeSectionIntoPayload(
    personForm: PersonFormData,
    section: PersonSection,
    values: SectionValues,
): PersonPayload {
    const payload = personFormToPayload(personForm);

    if (section === 'personal') {
        payload.first_name = blankToNull(values.first_name);
        payload.last_name = blankToNull(values.last_name);
        payload.maiden_name = blankToNull(values.maiden_name);
        payload.gender = blankToNull(values.gender);
        payload.religion = blankToNull(values.religion);
        payload.residence = blankToNull(values.residence);
    } else if (section === 'birth') {
        payload.birth = eventPayloadFromValues('birth', values);
    } else {
        payload.is_deceased = values.is_deceased === '1';

        if (payload.is_deceased) {
            payload.death = eventPayloadFromValues('death', values);
            payload.burial = {
                locality_id: toNumber(values['burial.locality_id']),
            };
        } else {
            delete payload.death;
            delete payload.burial;
        }
    }

    return payload;
}
