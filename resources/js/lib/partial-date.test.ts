import { describe, expect, it } from 'vite-plus/test';
import {
    describePrecision,
    formatPartialDate,
    parsePartialDate,
} from './partial-date';

function ok(text: string) {
    const result = parsePartialDate(text);

    if (result.kind !== 'ok') {
        throw new Error(`Expected "${text}" to parse, got ${result.kind}`);
    }

    return result;
}

describe('parsePartialDate: board examples', () => {
    it('reads a bare year', () => {
        const result = ok('1948');

        expect(result.parts).toEqual({
            year: 1948,
            month: null,
            day: null,
            is_approximate: false,
        });
        expect(formatPartialDate(result.parts, 'short')).toBe('1948');
        expect(describePrecision(result.precision)).toBe('tylko rok');
    });

    it('reads a month name with a year', () => {
        const result = ok('marzec 1948');

        expect(formatPartialDate(result.parts, 'short')).toBe('03.1948');
        expect(describePrecision(result.precision)).toBe('miesiąc i rok');
    });

    it('reads a numeric full date', () => {
        const result = ok('12.3.1948');

        expect(result.parts).toEqual({
            year: 1948,
            month: 3,
            day: 12,
            is_approximate: false,
        });
        expect(formatPartialDate(result.parts, 'short')).toBe('12.03.1948');
        expect(describePrecision(result.precision)).toBe('dokładna data');
    });

    it('reads an approximate year', () => {
        const result = ok('ok. 1948');

        expect(result.parts.is_approximate).toBe(true);
        expect(formatPartialDate(result.parts, 'short')).toBe('ok. 1948');
    });

    it('flags a two-digit year', () => {
        expect(parsePartialDate('12.3.48')).toEqual({ kind: 'two-digit-year' });
        expect(parsePartialDate('48')).toEqual({ kind: 'two-digit-year' });
    });

    it('flags unrecognised text', () => {
        expect(parsePartialDate('przed wojną')).toEqual({ kind: 'invalid' });
    });

    it('treats blank input as empty', () => {
        expect(parsePartialDate('')).toEqual({ kind: 'empty' });
        expect(parsePartialDate('   ')).toEqual({ kind: 'empty' });
    });
});

describe('parsePartialDate: separators', () => {
    it.each(['12.3.1948', '12/3/1948', '12-3-1948', '12 3 1948'])(
        'accepts %s',
        (text) => {
            expect(ok(text).parts).toMatchObject({
                year: 1948,
                month: 3,
                day: 12,
            });
        },
    );

    it('reads M.YYYY', () => {
        expect(ok('3.1948').parts).toMatchObject({
            year: 1948,
            month: 3,
            day: null,
        });
    });
});

describe('parsePartialDate: month names', () => {
    it('accepts the genitive with a day', () => {
        expect(ok('12 marca 1948').parts).toMatchObject({
            year: 1948,
            month: 3,
            day: 12,
        });
    });

    it('accepts 3-letter abbreviations', () => {
        expect(ok('12 mar 1948').parts).toMatchObject({ month: 3, day: 12 });
        expect(ok('paź 1948').parts).toMatchObject({ month: 10 });
    });

    it('accepts every nominative and genitive form', () => {
        const nominative = [
            'styczeń',
            'luty',
            'marzec',
            'kwiecień',
            'maj',
            'czerwiec',
            'lipiec',
            'sierpień',
            'wrzesień',
            'październik',
            'listopad',
            'grudzień',
        ];
        const genitive = [
            'stycznia',
            'lutego',
            'marca',
            'kwietnia',
            'maja',
            'czerwca',
            'lipca',
            'sierpnia',
            'września',
            'października',
            'listopada',
            'grudnia',
        ];

        nominative.forEach((name, index) => {
            expect(ok(`${name} 1948`).parts.month).toBe(index + 1);
        });
        genitive.forEach((name, index) => {
            expect(ok(`5 ${name} 1948`).parts.month).toBe(index + 1);
        });
    });

    it('is case-insensitive', () => {
        expect(ok('MARZEC 1948').parts.month).toBe(3);
        expect(ok('12 Marca 1948').parts.month).toBe(3);
    });

    it('accepts input without diacritics', () => {
        expect(ok('pazdziernik 1948').parts.month).toBe(10);
        expect(ok('12 wrzesnia 1948').parts.month).toBe(9);
        expect(ok('styczen 1948').parts.month).toBe(1);
        expect(ok('okolo 1948').parts.is_approximate).toBe(true);
    });
});

describe('parsePartialDate: Roman months', () => {
    it('reads a Roman month', () => {
        expect(ok('III 1948').parts).toMatchObject({ month: 3, day: null });
        expect(ok('12 III 1948').parts).toMatchObject({ month: 3, day: 12 });
        expect(ok('12.III.1948').parts).toMatchObject({ month: 3, day: 12 });
        expect(ok('xii 1948').parts.month).toBe(12);
    });

    it('reads I as month by position, not as day', () => {
        expect(ok('I 1948').parts).toMatchObject({ month: 1, day: null });
        expect(ok('1 1948').parts).toMatchObject({ month: 1, day: null });
        expect(ok('1 I 1948').parts).toMatchObject({ month: 1, day: 1 });
        expect(parsePartialDate('I I 1948')).toEqual({ kind: 'invalid' });
    });
});

describe('parsePartialDate: approximate markers', () => {
    it.each([
        'ok. 1948',
        'ok 1948',
        'około 1948',
        'okolo 1948',
        '~1948',
        '~ 1948',
        'ca 1948',
        'ca. 1948',
        'OK. 1948',
    ])('marks %s as approximate', (text) => {
        expect(ok(text).parts).toMatchObject({
            year: 1948,
            is_approximate: true,
        });
    });

    it('combines with month and day', () => {
        expect(ok('ok. 12.3.1948').parts).toEqual({
            year: 1948,
            month: 3,
            day: 12,
            is_approximate: true,
        });
    });

    it('rejects a marker without a date', () => {
        expect(parsePartialDate('ok.')).toEqual({ kind: 'invalid' });
        expect(parsePartialDate('~')).toEqual({ kind: 'invalid' });
    });
});

describe('parsePartialDate: rejections', () => {
    it.each([
        '1948-1950',
        '1948 - 1950',
        'przed 1948',
        'po 1948',
        'zima 1948',
        'lato 2015',
        '32.3.1948',
        '0.3.1948',
        '12.13.1948',
        '12.0.1948',
        '1948 r.',
        '12.3.1948 abc',
        '1.2.3.1948',
        'abc',
        '194',
        '19488',
    ])('rejects %s', (text) => {
        expect(parsePartialDate(text)).toEqual({ kind: 'invalid' });
    });

    it('does not check the calendar beyond day 31', () => {
        expect(ok('31.2.1948').parts).toMatchObject({ month: 2, day: 31 });
    });
});

describe('formatPartialDate', () => {
    const day = { year: 1948, month: 3, day: 12, is_approximate: false };
    const month = { year: 1948, month: 3, day: null, is_approximate: false };
    const year = { year: 2015, month: null, day: null, is_approximate: false };

    it('formats short like PartialDate::format', () => {
        expect(formatPartialDate(day, 'short')).toBe('12.03.1948');
        expect(formatPartialDate(month, 'short')).toBe('03.1948');
        expect(formatPartialDate(year, 'short')).toBe('2015');
        expect(
            formatPartialDate({ ...year, is_approximate: true }, 'short'),
        ).toBe('ok. 2015');
        expect(
            formatPartialDate({ ...day, is_approximate: true }, 'short'),
        ).toBe('ok. 12.03.1948');
    });

    it('formats long for cards', () => {
        expect(formatPartialDate(day, 'long')).toBe('12 marca 1948');
        expect(formatPartialDate(month, 'long')).toBe('marzec 1948');
        expect(formatPartialDate(year, 'long')).toBe('2015');
        expect(
            formatPartialDate({ ...year, is_approximate: true }, 'long'),
        ).toBe('około 2015');
    });

    it('returns an empty string without a year', () => {
        expect(
            formatPartialDate(
                { year: null, month: null, day: null, is_approximate: false },
                'short',
            ),
        ).toBe('');
    });

    it('round-trips short output through the parser', () => {
        expect(ok(formatPartialDate(day, 'short')).parts).toEqual(day);
        expect(ok('ok. 03.1948').parts).toEqual({
            ...month,
            is_approximate: true,
        });
    });
});

describe('describePrecision', () => {
    it('names each precision', () => {
        expect(describePrecision('day')).toBe('dokładna data');
        expect(describePrecision('month')).toBe('miesiąc i rok');
        expect(describePrecision('year')).toBe('tylko rok');
    });
});
