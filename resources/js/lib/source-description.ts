import type { Source, SourceKind } from '@/types';

type CardFields = Pick<
    Source,
    'record_type_label' | 'year' | 'archive' | 'parish_label'
>;

/** The „typ · rok · parafia” line of a source card, or null when none is set. */
export function formatSourceMeta(source: CardFields): string | null {
    const parts = [
        source.record_type_label,
        source.year === null ? null : String(source.year),
        source.parish_label,
    ].filter((part): part is string => part !== null && part !== '');

    return parts.length > 0 ? parts.join(' · ') : null;
}

/** The upload date is a fallback: shown only when the card has no description line. */
export function showsUploadDate(source: CardFields): boolean {
    return (
        formatSourceMeta(source) === null &&
        (source.archive === null || source.archive === '')
    );
}

export type ErasedField = {
    field: 'record_type' | 'entry_number' | 'parish_id';
    label: string;
    value: string;
};

type StoredRecord = Pick<
    Source,
    | 'kind'
    | 'record_type'
    | 'record_type_label'
    | 'entry_number'
    | 'parish_id'
    | 'parish_label'
>;

const present = (value: unknown): value is string =>
    typeof value === 'string' && value !== '';

/**
 * The akt-only fields that switching the kind would erase: typ, parafia and numer wpisu,
 * stored or typed. A stored value counts even when it was cleared in the open
 * form. Empty unless an akt metrykalny is changed to another kind.
 */
export function erasedRecordFields(
    source: StoredRecord,
    selectedKind: SourceKind | '',
    formValues: {
        record_type?: unknown;
        entry_number?: unknown;
        parish_id?: unknown;
    },
    recordTypes: { value: string; label: string }[] = [],
    parishes: { id: number; label: string }[] = [],
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

    const typedParish = formValues.parish_id;

    if (present(typedParish) || source.parish_id !== null) {
        const chosen = present(typedParish)
            ? parishes.find((parish) => String(parish.id) === typedParish)
            : undefined;
        const storedId =
            source.parish_id === null ? null : String(source.parish_id);
        const label =
            chosen?.label ??
            (!present(typedParish) || typedParish === storedId
                ? source.parish_label
                : null) ??
            (present(typedParish) ? typedParish : '');

        erased.push({ field: 'parish_id', label: 'Parafia', value: label });
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
    | 'archive'
    | 'parish_id'
    | 'url'
    | 'note'
>;

/** A source is undescribed only when all description columns are null; the kind does not count. */
export function isUndescribed(source: DescriptionFields): boolean {
    return (
        source.name === null &&
        source.record_type === null &&
        source.entry_number === null &&
        source.year === null &&
        source.archive === null &&
        source.parish_id === null &&
        source.url === null &&
        source.note === null
    );
}

type SummaryFields = CardFields &
    Pick<Source, 'kind' | 'kind_short_label' | 'entry_number' | 'uploaded_at'>;

/** „akt urodzenia”, „akt metrykalny” (akt without a type), „nagrobek”, „inne”. */
export function sourceKindText(
    source: Pick<Source, 'kind' | 'kind_short_label' | 'record_type_label'>,
): string {
    if (source.kind !== 'metrical_record') {
        return source.kind_short_label;
    }

    return present(source.record_type_label)
        ? `${source.kind_short_label} ${source.record_type_label}`
        : 'akt metrykalny';
}

function formatUploadDate(uploadedAt: string): string | null {
    const date = new Date(uploadedAt);

    return Number.isNaN(date.getTime())
        ? null
        : date.toLocaleDateString('pl-PL', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          });
}

/** „akt urodzenia · 1878 · nr 47 · parafia”; ends with „dodano {date}” when the upload date is shown. */
export function formatSourceSummary(source: SummaryFields): string {
    const parts = [sourceKindText(source)];

    if (source.year !== null) {
        parts.push(String(source.year));
    }

    if (present(source.entry_number)) {
        parts.push(`nr ${source.entry_number}`);
    }

    if (present(source.parish_label)) {
        parts.push(source.parish_label);
    }

    if (showsUploadDate(source)) {
        const date = formatUploadDate(source.uploaded_at);

        if (date !== null) {
            parts.push(`dodano ${date}`);
        }
    }

    return parts.join(' · ');
}
