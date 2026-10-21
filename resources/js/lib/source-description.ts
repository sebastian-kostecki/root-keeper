import type { CemeteryOption, Source, SourceKind } from '@/types';

const present = (value: unknown): value is string =>
    typeof value === 'string' && value !== '';

type CardFields = Pick<
    Source,
    | 'kind'
    | 'record_type_label'
    | 'year'
    | 'archive'
    | 'parish_label'
    | 'cemetery_label'
>;

/** „cmentarz Luboml” (or „Cmentarz parafialny, Luboml”) for a gravestone, the parish label for an akt; null when not set. */
export function sourcePlaceLabel(
    source: Pick<Source, 'kind' | 'parish_label' | 'cemetery_label'>,
): string | null {
    if (source.kind === 'gravestone') {
        return present(source.cemetery_label) ? source.cemetery_label : null;
    }

    return source.parish_label;
}

/** The „typ · rok · parafia/cmentarz” line of a source card, or null when none is set. */
export function formatSourceMeta(source: CardFields): string | null {
    const parts = [
        source.record_type_label,
        source.year === null ? null : String(source.year),
        sourcePlaceLabel(source),
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
    field: 'record_type' | 'parish_id' | 'cemetery_id' | 'buried_person_ids';
    label: string;
    value: string;
};

type StoredRecord = Pick<
    Source,
    | 'kind'
    | 'record_type'
    | 'record_type_label'
    | 'parish_id'
    | 'parish_label'
    | 'cemetery_id'
    | 'cemetery_label'
    | 'buried_people'
>;

/**
 * The kind-only fields that switching the kind would erase: typ and parafia of
 * an akt metrykalny, cemetery and buried people of a gravestone, stored or
 * typed. A stored value counts even when it was cleared in the open form.
 * Empty unless the kind changes.
 */
export function erasedRecordFields(
    source: StoredRecord,
    selectedKind: SourceKind | '',
    formValues: {
        record_type?: unknown;
        parish_id?: unknown;
        cemetery_id?: unknown;
        buried_person_ids?: unknown;
    },
    recordTypes: { value: string; label: string }[] = [],
    parishes: { id: number; label: string }[] = [],
    cemeteries: CemeteryOption[] = [],
): ErasedField[] {
    if (selectedKind === '' || selectedKind === source.kind) {
        return [];
    }

    if (source.kind === 'gravestone') {
        return erasedGravestoneFields(source, formValues, cemeteries);
    }

    if (source.kind !== 'metrical_record') {
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

        erased.push({
            field: 'parish_id',
            label: 'Parafia / urząd',
            value: label,
        });
    }

    return erased;
}

function erasedGravestoneFields(
    source: StoredRecord,
    formValues: {
        cemetery_id?: unknown;
        buried_person_ids?: unknown;
    },
    cemeteries: CemeteryOption[],
): ErasedField[] {
    const erased: ErasedField[] = [];
    const typed = formValues.cemetery_id;

    if (present(typed) || source.cemetery_id !== null) {
        const chosen = present(typed)
            ? cemeteries.find((cemetery) => String(cemetery.id) === typed)
            : undefined;
        const storedId =
            source.cemetery_id === null ? null : String(source.cemetery_id);
        const label =
            chosen?.label ??
            (!present(typed) || typed === storedId
                ? source.cemetery_label
                : null) ??
            (present(typed) ? typed : '');

        erased.push({
            field: 'cemetery_id',
            label: 'Cmentarz',
            value: label,
        });
    }

    const typedIds = Array.isArray(formValues.buried_person_ids)
        ? formValues.buried_person_ids.map(String)
        : [];
    const buried = new Set([
        ...(source.buried_people ?? []).map((person) => String(person.id)),
        ...typedIds,
    ]);

    if (buried.size > 0) {
        erased.push({
            field: 'buried_person_ids',
            label: 'Osoby pochowane',
            value: String(buried.size),
        });
    }

    return erased;
}

type DescriptionFields = Pick<
    Source,
    'name' | 'record_type' | 'year' | 'archive' | 'parish_id' | 'url' | 'note'
>;

/** A source is undescribed only when all description columns are null; the kind does not count. */
export function isUndescribed(source: DescriptionFields): boolean {
    return (
        source.name === null &&
        source.record_type === null &&
        source.year === null &&
        source.archive === null &&
        source.parish_id === null &&
        source.url === null &&
        source.note === null
    );
}

type SummaryFields = CardFields &
    Pick<Source, 'kind' | 'kind_short_label' | 'uploaded_at'>;

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

/** „akt urodzenia · 1878 · parafia” or „nagrobek · 1950 · cmentarz Luboml”; ends with „dodano {date}” when the upload date is shown. */
export function formatSourceSummary(source: SummaryFields): string {
    const parts = [sourceKindText(source)];

    if (source.year !== null) {
        parts.push(String(source.year));
    }

    const place = sourcePlaceLabel(source);

    if (present(place)) {
        parts.push(place);
    }

    if (showsUploadDate(source)) {
        const date = formatUploadDate(source.uploaded_at);

        if (date !== null) {
            parts.push(`dodano ${date}`);
        }
    }

    return parts.join(' · ');
}
