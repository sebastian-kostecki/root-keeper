import { describe, expect, it } from 'vite-plus/test';
import { splitSourcesForEvent } from './source-matching';

type Fields = Parameters<typeof splitSourcesForEvent>[0][number];

const akt = (id: number, record_type: Fields['record_type']) => ({
    id,
    kind: 'metrical_record' as const,
    record_type,
});

describe('splitSourcesForEvent', () => {
    it('matches an akt typed for the event, for each event type', () => {
        expect(
            splitSourcesForEvent([akt(1, 'birth')], 'birth').matching,
        ).toEqual([akt(1, 'birth')]);
        expect(
            splitSourcesForEvent([akt(2, 'marriage')], 'marriage').matching,
        ).toEqual([akt(2, 'marriage')]);
        expect(
            splitSourcesForEvent([akt(3, 'death')], 'death').matching,
        ).toEqual([akt(3, 'death')]);
    });

    it('sends an akt without a type to the rest', () => {
        const result = splitSourcesForEvent([akt(1, null)], 'birth');

        expect(result.matching).toEqual([]);
        expect(result.rest).toEqual([akt(1, null)]);
    });

    it('sends an akt typed inny to the rest', () => {
        const result = splitSourcesForEvent([akt(1, 'other')], 'birth');

        expect(result.matching).toEqual([]);
        expect(result.rest).toEqual([akt(1, 'other')]);
    });

    it('sends an akt of another event type to the rest', () => {
        const result = splitSourcesForEvent([akt(1, 'death')], 'birth');

        expect(result.matching).toEqual([]);
        expect(result.rest).toEqual([akt(1, 'death')]);
    });

    it('never matches a gravestone or other source, even with the record type', () => {
        const gravestone = {
            id: 1,
            kind: 'gravestone' as const,
            record_type: 'death' as const,
        };
        const other = {
            id: 2,
            kind: 'other' as const,
            record_type: 'death' as const,
        };
        const result = splitSourcesForEvent([gravestone, other], 'death');

        expect(result.matching).toEqual([]);
        expect(result.rest).toEqual([gravestone, other]);
    });

    it('keeps the input order in both groups', () => {
        const list = [
            akt(1, 'death'),
            akt(2, 'birth'),
            akt(3, 'death'),
            akt(4, null),
            akt(5, 'death'),
        ];
        const result = splitSourcesForEvent(list, 'death');

        expect(result.matching.map((source) => source.id)).toEqual([1, 3, 5]);
        expect(result.rest.map((source) => source.id)).toEqual([2, 4]);
    });

    it('returns the whole list as the rest when nothing matches', () => {
        const list = [akt(3, 'birth'), akt(1, null), akt(2, 'other')];
        const result = splitSourcesForEvent(list, 'marriage');

        expect(result.matching).toEqual([]);
        expect(result.rest).toEqual(list);
    });

    it('gives two empty groups for an empty list', () => {
        expect(splitSourcesForEvent([], 'birth')).toEqual({
            matching: [],
            rest: [],
        });
    });
});
