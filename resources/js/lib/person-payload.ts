import MarriageController from '@/actions/App/Http/Controllers/MarriageController';
import type {
    LocalityOption,
    MarriageEvent,
    PersonFormData,
    PersonFormEvent,
} from '@/types';

/** Form values of one edited section, keyed by input name (`birth.year`, ...). */
export type SectionValues = Record<string, string>;

/** Sections that save through `PUT persons/{id}`. */
export type PersonSection = 'personal' | 'birth' | 'death' | 'residences';

/** Body of `PUT persons/{id}/note`. */
export type PersonNotePayload = { note: string | null };

/** Body of `PUT marriages/{id}`. */
export type MarriageSavePayload = {
    marriage: PartialDatePayload & { form: string | null };
};

export type PartialDatePayload = {
    year: number | null;
    month: number | null;
    day: number | null;
    is_approximate: boolean;
    locality_id: number | null;
};

export type ResidencePayload = {
    locality_id: number | null;
    year_from: number | null;
    year_to: number | null;
};

export type PersonPayload = {
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    residences: ResidencePayload[];
    religion: string | null;
    is_deceased: boolean;
    birth: PartialDatePayload;
    death?: PartialDatePayload;
    burial?: { cemetery_id: number | null };
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
 * Reads `residences.{i}.locality_id|year_from|year_to` out of form values, in row
 * order. Empty rows are kept: the server skips them and keeps the indexes, so an
 * error lands under the right row.
 */
export function residencesFromValues(
    values: SectionValues,
): ResidencePayload[] {
    const rows = new Map<number, ResidencePayload>();

    for (const key of Object.keys(values)) {
        const match =
            /^residences\.(\d+)\.(locality_id|year_from|year_to)$/.exec(key);

        if (match === null) {
            continue;
        }

        const index = Number(match[1]);
        const row = rows.get(index) ?? {
            locality_id: null,
            year_from: null,
            year_to: null,
        };

        row[match[2] as keyof ResidencePayload] = toNumber(values[key]);
        rows.set(index, row);
    }

    return [...rows.entries()].sort(([a], [b]) => a - b).map(([, row]) => row);
}

/**
 * Maps the person form data to the `PUT persons/{id}` request shape. The
 * update is a full replace on the server, so every key must be present.
 */
export function personFormToPayload(personForm: PersonFormData): PersonPayload {
    const payload: PersonPayload = {
        first_name: personForm.first_name,
        last_name: personForm.last_name,
        gender: personForm.gender,
        residences: personForm.residences.map((residence) => ({
            locality_id: residence.locality.id,
            year_from: residence.year_from,
            year_to: residence.year_to,
        })),
        religion: personForm.religion,
        is_deceased: personForm.is_deceased,
        birth: eventToPayload(personForm.birth),
    };

    if (personForm.is_deceased) {
        payload.death = eventToPayload(personForm.death);
        payload.burial = {
            cemetery_id: personForm.burial?.cemetery?.id ?? null,
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
        payload.gender = blankToNull(values.gender);
        payload.religion = blankToNull(values.religion);
    } else if (section === 'residences') {
        payload.residences = residencesFromValues(values);
    } else if (section === 'birth') {
        payload.birth = eventPayloadFromValues('birth', values);
    } else {
        payload.is_deceased = values.is_deceased === '1';

        if (payload.is_deceased) {
            payload.death = eventPayloadFromValues('death', values);
            payload.burial = {
                cemetery_id: toNumber(values['burial.cemetery_id']),
            };
        } else {
            delete payload.death;
            delete payload.burial;
        }
    }

    return payload;
}

/** Values of the marriage fields as `MarriageFields` shows them. */
export function marriageFieldDefaults(
    marriage: Pick<
        MarriageEvent,
        'date_parts' | 'locality_id' | 'place' | 'marriage_form'
    >,
): {
    year: number | null;
    month: number | null;
    day: number | null;
    is_approximate: boolean;
    locality: LocalityOption | null;
    form: string | null;
} {
    return {
        ...marriage.date_parts,
        form: marriage.marriage_form,
        locality:
            marriage.locality_id === null
                ? null
                : { id: marriage.locality_id, name: marriage.place ?? '' },
    };
}

/** `PUT marriages/{id}` request for the edited values of the marriage card. */
export function marriageSaveRequest(
    marriageId: number,
    values: SectionValues,
): readonly [string, MarriageSavePayload] {
    return [
        MarriageController.update.url(marriageId),
        {
            marriage: {
                ...eventPayloadFromValues('marriage', values),
                form: values['marriage.form'] || null,
            },
        },
    ];
}
