import { describe, expect, it } from 'vite-plus/test';
import type { TimelineEntry } from '@/types';
import { personTimelineRows } from './person-timeline';

function entry(overrides: Partial<TimelineEntry> = {}): TimelineEntry {
    return {
        id: 1,
        type: 'birth',
        label: 'Urodzenie',
        date: '1900',
        year: 1900,
        qualifier: 'exact',
        until_year: null,
        date_has_month: false,
        age: null,
        place: null,
        relative: null,
        ...overrides,
    };
}

const child = { id: 2, display_name: 'Anna Kowalska' };

describe('personTimelineRows', () => {
    it('marks own events, relatives and unknown dates', () => {
        const rows = personTimelineRows([
            entry({ type: 'birth' }),
            entry({ id: 2, type: 'marriage', relative: child }),
            entry({ id: 3, type: 'birth', relative: child }),
            entry({ id: 4, type: 'death', relative: child }),
            entry({ id: 5, type: 'burial', year: null, date: null }),
            entry({ id: 6, type: 'death', year: null, date: null }),
        ]);

        expect(rows.map((row) => row.marker)).toEqual([
            'own',
            'own',
            'relative',
            'relative',
            'unknown',
            'unknown',
        ]);
    });

    it('shows "?" for a missing year and an empty column for a burial', () => {
        const [unknown, burial] = personTimelineRows([
            entry({ type: 'marriage', year: null, date: null }),
            entry({ id: 2, type: 'burial', year: null, date: null }),
        ]);

        expect(unknown.year).toBe('?');
        expect(burial.year).toBeNull();
    });

    it('passes the year, the qualifier label and the formatted age', () => {
        const [row] = personTimelineRows([
            entry({
                year: 1920,
                qualifier: 'approximate',
                age: { unit: 'years', value: 20 },
            }),
        ]);

        expect(row.year).toBe('1920');
        expect(row.qualifierLabel).toBe('ok.');
        expect(row.age).toBe('20 lat');
    });

    it.each([
        ['before', 'przed'],
        ['after', 'po'],
    ] as const)('labels a %s date above the year', (qualifier, label) => {
        const [row] = personTimelineRows([entry({ qualifier })]);

        expect(row.qualifierLabel).toBe(label);
        expect(row.year).toBe('1900');
        expect(row.yearEnd).toBeNull();
    });

    it('splits a range into two year lines with a spoken date', () => {
        const [row] = personTimelineRows([
            entry({ year: 1805, qualifier: 'range', until_year: 1811 }),
        ]);

        expect(row.year).toBe('1805\u2013');
        expect(row.yearEnd).toBe('1811');
        expect(row.qualifierLabel).toBeNull();
        expect(row.spokenDate).toBe('między 1805 a 1811');
    });

    it('has no age text when the server sent none', () => {
        const [row] = personTimelineRows([entry()]);

        expect(row.age).toBeNull();
    });

    it('shows the full date only when it has a month', () => {
        const [year, full] = personTimelineRows([
            entry({ date: '1920' }),
            entry({
                id: 2,
                date: '12.05.1920',
                date_has_month: true,
            }),
        ]);

        expect(year.date).toBeNull();
        expect(full.date).toBe('12.05.1920');
    });

    it('dashes segments next to an unknown marker and ends the last row', () => {
        const rows = personTimelineRows([
            entry({ type: 'death', year: 1960 }),
            entry({ id: 2, type: 'burial', year: null, date: null }),
            entry({ id: 3, type: 'death', relative: child, year: 1990 }),
        ]);

        expect(rows.map((row) => row.segmentBelow)).toEqual([
            'dashed',
            'dashed',
            null,
        ]);
    });

    it('keeps segments solid between known markers', () => {
        const rows = personTimelineRows([
            entry(),
            entry({ id: 2, type: 'marriage', year: 1920 }),
            entry({ id: 3, type: 'death', year: 1960 }),
        ]);

        expect(rows.map((row) => row.segmentBelow)).toEqual([
            'solid',
            'solid',
            null,
        ]);
    });

    it('returns no rows for an empty timeline', () => {
        expect(personTimelineRows([])).toEqual([]);
    });
});
