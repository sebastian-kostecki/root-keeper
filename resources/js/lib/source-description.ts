import type { Source, SourceKind } from '@/types';

type CardFields = Pick<Source, 'record_type_label' | 'year' | 'provenance'>;

/** The „typ · rok” line of a source card, or null when neither is set. */
export function formatSourceMeta(source: CardFields): string | null {
    const parts = [
        source.record_type_label,
        source.year === null ? null : String(source.year),
    ].filter((part): part is string => part !== null && part !== '');

    return parts.length > 0 ? parts.join(' · ') : null;
}

/** The upload date is a fallback: shown only when the card has no description line. */
export function showsUploadDate(source: CardFields): boolean {
    return (
        formatSourceMeta(source) === null &&
        (source.provenance === null || source.provenance === '')
    );
}

export type ErasedField = {
    field: 'record_type' | 'entry_number';
    label: string;
    value: string;
};

type StoredRecord = Pick<
    Source,
    'kind' | 'record_type' | 'record_type_label' | 'entry_number'
>;

const present = (value: unknown): value is string =>
    typeof value === 'string' && value !== '';

/**
 * The akt-only fields that switching the kind would erase: typ and numer wpisu,
 * stored or typed. A stored value counts even when it was cleared in the open
 * form. Empty unless an akt metrykalny is changed to another kind.
 */
export function erasedRecordFields(
    source: StoredRecord,
    selectedKind: SourceKind | '',
    formValues: { record_type?: unknown; entry_number?: unknown },
    recordTypes: { value: string; label: string }[] = [],
): ErasedField[] {
    if (
        source.kind !== 'metrical_record' ||
        selectedKind === 'metrical_record' ||
        selectedKind === ''
    ) {
        return [];
    }

    const erased: ErasedField[] = [];
    const typed = formValues.record_type;

    if (present(typed) || present(source.record_type)) {
        const value = present(typed) ? typed : (source.record_type ?? '');
        const label =
            recordTypes.find((option) => option.value === value)?.label ??
            (value === source.record_type ? source.record_type_label : null) ??
            value;

        erased.push({ field: 'record_type', label: 'Typ aktu', value: label });
    }

    const typedNumber = formValues.entry_number;

    if (present(typedNumber) || present(source.entry_number)) {
        erased.push({
            field: 'entry_number',
            label: 'Numer wpisu',
            value: present(typedNumber)
                ? typedNumber
                : (source.entry_number ?? ''),
        });
    }

    return erased;
}

type DescriptionFields = Pick<
    Source,
    | 'name'
    | 'record_type'
    | 'entry_number'
    | 'year'
    | 'provenance'
    | 'url'
    | 'note'
>;

/** A source is undescribed only when all seven description columns are null; the kind does not count. */
export function isUndescribed(source: DescriptionFields): boolean {
    return (
        source.name === null &&
        source.record_type === null &&
        source.entry_number === null &&
        source.year === null &&
        source.provenance === null &&
        source.url === null &&
        source.note === null
    );
}
