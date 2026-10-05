import { describe, expect, it } from 'vite-plus/test';
import {
    erasedRecordFields,
    formatSourceMeta,
    formatSourceSummary,
    isUndescribed,
    showsUploadDate,
    sourceKindText,
} from './source-description';

const empty = {
    record_type_label: null,
    year: null,
    archive: null,
    parish_label: null,
};

describe('formatSourceMeta', () => {
    it('joins type and year', () => {
        expect(
            formatSourceMeta({
                ...empty,
                record_type_label: 'urodzenia',
                year: 1878,
            }),
        ).toBe('urodzenia · 1878');
    });

    it('shows only the year', () => {
        expect(formatSourceMeta({ ...empty, year: 1878 })).toBe('1878');
    });

    it('shows only the type', () => {
        expect(
            formatSourceMeta({ ...empty, record_type_label: 'urodzenia' }),
        ).toBe('urodzenia');
    });

    it('appends the parish', () => {
        expect(
            formatSourceMeta({
                ...empty,
                record_type_label: 'urodzenia',
                year: 1878,
                parish_label: 'Św. Jana (rzymskokatolickie)',
            }),
        ).toBe('urodzenia · 1878 · Św. Jana (rzymskokatolickie)');
    });

    it('returns nothing when all are empty', () => {
        expect(formatSourceMeta(empty)).toBeNull();
    });
});

describe('showsUploadDate', () => {
    it('shows the date when typ, rok and archiwum are all empty', () => {
        expect(showsUploadDate(empty)).toBe(true);
    });

    it('hides the date when any of them is set', () => {
        expect(showsUploadDate({ ...empty, year: 1878 })).toBe(false);
        expect(showsUploadDate({ ...empty, record_type_label: 'zgonu' })).toBe(
            false,
        );
        expect(showsUploadDate({ ...empty, archive: 'Archiwum' })).toBe(false);
    });
});

const akt = {
    kind: 'metrical_record' as const,
    record_type: null,
    record_type_label: null,
    parish_id: null,
    parish_label: null,
};

describe('erasedRecordFields', () => {
    it('lists nothing when typ and parafia are both empty', () => {
        expect(
            erasedRecordFields(akt, 'gravestone', {
                record_type: '',
                parish_id: '',
            }),
        ).toEqual([]);
    });

    it('lists a stored typ', () => {
        expect(
            erasedRecordFields(
                {
                    ...akt,
                    record_type: 'birth',
                    record_type_label: 'urodzenia',
                },
                'gravestone',
                { record_type: 'birth', parish_id: '' },
            ),
        ).toEqual([
            { field: 'record_type', label: 'Typ aktu', value: 'urodzenia' },
        ]);
    });

    it('lists a stored parish by its label', () => {
        expect(
            erasedRecordFields(
                { ...akt, parish_id: 7, parish_label: 'Św. Jana' },
                'gravestone',
                { parish_id: '7' },
            ),
        ).toEqual([
            { field: 'parish_id', label: 'Parafia', value: 'Św. Jana' },
        ]);
    });

    it('lists a stored parish cleared in the form', () => {
        expect(
            erasedRecordFields(
                { ...akt, parish_id: 7, parish_label: 'Św. Jana' },
                'other',
                { parish_id: '' },
            ),
        ).toEqual([
            { field: 'parish_id', label: 'Parafia', value: 'Św. Jana' },
        ]);
    });

    it('lists a parish chosen in the form by its option label', () => {
        expect(
            erasedRecordFields(
                akt,
                'other',
                { parish_id: '9' },
                [],
                [{ id: 9, label: 'Św. Anny' }],
            ),
        ).toEqual([
            { field: 'parish_id', label: 'Parafia', value: 'Św. Anny' },
        ]);
    });

    it('lists nothing when the kind is unchanged', () => {
        expect(
            erasedRecordFields(
                { ...akt, parish_id: 7, parish_label: 'Św. Jana' },
                'metrical_record',
                { parish_id: '7' },
            ),
        ).toEqual([]);
    });

    it('lists nothing for a source that was never an akt', () => {
        expect(
            erasedRecordFields({ ...akt, kind: 'gravestone' }, 'other', {
                record_type: 'birth',
                parish_id: '7',
            }),
        ).toEqual([]);
    });
});

describe('isUndescribed', () => {
    const none = {
        name: null,
        record_type: null,
        year: null,
        archive: null,
        parish_id: null,
        url: null,
        note: null,
    };

    it('is true when all columns are null', () => {
        expect(isUndescribed(none)).toBe(true);
    });

    it('is false with only a name', () => {
        expect(isUndescribed({ ...none, name: 'Akt chrztu' })).toBe(false);
    });

    it('is false with only an archive', () => {
        expect(isUndescribed({ ...none, archive: 'Archiwum w Tarnowie' })).toBe(
            false,
        );
    });

    it('is false with only a parish', () => {
        expect(isUndescribed({ ...none, parish_id: 3 })).toBe(false);
    });

    it('is false with only a note', () => {
        expect(isUndescribed({ ...none, note: 'Znalazłem w archiwum' })).toBe(
            false,
        );
    });
});

const summarySource = {
    kind: 'other' as const,
    kind_short_label: 'inne',
    record_type_label: null,
    year: null,
    archive: null,
    parish_label: null,
    uploaded_at: '2026-09-24T12:00:00+00:00',
};

const birthAkt = {
    ...summarySource,
    kind: 'metrical_record' as const,
    kind_short_label: 'akt',
    record_type_label: 'urodzenia',
};

describe('sourceKindText', () => {
    it('names the akt by its type', () => {
        expect(sourceKindText(birthAkt)).toBe('akt urodzenia');
    });

    it('calls an akt without a type „akt metrykalny”', () => {
        expect(
            sourceKindText({
                ...birthAkt,
                record_type_label: null,
            }),
        ).toBe('akt metrykalny');
    });

    it('uses the short label of the other kinds', () => {
        expect(
            sourceKindText({
                ...summarySource,
                kind: 'gravestone',
                kind_short_label: 'nagrobek',
            }),
        ).toBe('nagrobek');
        expect(sourceKindText(summarySource)).toBe('inne');
    });
});

describe('formatSourceSummary', () => {
    it('joins kind, year and parish', () => {
        expect(
            formatSourceSummary({
                ...birthAkt,
                year: 1878,
                parish_label: 'Św. Jana',
            }),
        ).toBe('akt urodzenia · 1878 · Św. Jana');
    });

    it('omits what is not set', () => {
        expect(
            formatSourceSummary({
                ...summarySource,
                kind: 'gravestone',
                kind_short_label: 'nagrobek',
                year: 1932,
            }),
        ).toBe('nagrobek · 1932');
    });

    it('adds the upload date when nothing else describes the source', () => {
        expect(formatSourceSummary(summarySource)).toBe(
            'inne · dodano 24.09.2026',
        );
        expect(
            formatSourceSummary({
                ...birthAkt,
                record_type_label: null,
                uploaded_at: '2026-09-24T12:00:00+00:00',
            }),
        ).toBe('akt metrykalny · dodano 24.09.2026');
    });

    it('has no date when archiwum is set', () => {
        expect(
            formatSourceSummary({
                ...summarySource,
                archive: 'Archiwum w Nowym Sączu',
            }),
        ).toBe('inne');
    });

    it('ends with the parish', () => {
        expect(
            formatSourceSummary({
                ...birthAkt,
                year: 1878,
                parish_label: 'Św. Jana',
            }),
        ).toBe('akt urodzenia · 1878 · Św. Jana');
    });
});
