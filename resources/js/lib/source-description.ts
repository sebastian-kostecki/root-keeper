import type { Source } from '@/types';

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
