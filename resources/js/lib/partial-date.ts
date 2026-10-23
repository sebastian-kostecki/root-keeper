export type DateQualifier =
    | 'exact'
    | 'approximate'
    | 'before'
    | 'after'
    | 'range';

export type PartialDateParts = {
    year: number | null;
    month: number | null;
    day: number | null;
    qualifier: DateQualifier;
    until_year: number | null;
};

export type DatePrecision = 'year' | 'month' | 'day' | 'range';

export type ParsedPartialDate =
    | { kind: 'empty' }
    | {
          kind: 'ok';
          parts: PartialDateParts;
          precision: DatePrecision;
      }
    | { kind: 'two-digit-year' }
    | { kind: 'range-order' }
    | { kind: 'invalid' };

/** Month names indexed by month number - 1: nominative, genitive, 3-letter abbreviation. */
const MONTHS: ReadonlyArray<readonly [string, string, string]> = [
    ['styczeń', 'stycznia', 'sty'],
    ['luty', 'lutego', 'lut'],
    ['marzec', 'marca', 'mar'],
    ['kwiecień', 'kwietnia', 'kwi'],
    ['maj', 'maja', 'maj'],
    ['czerwiec', 'czerwca', 'cze'],
    ['lipiec', 'lipca', 'lip'],
    ['sierpień', 'sierpnia', 'sie'],
    ['wrzesień', 'września', 'wrz'],
    ['październik', 'października', 'paz'],
    ['listopad', 'listopada', 'lis'],
    ['grudzień', 'grudnia', 'gru'],
];

const ROMAN_MONTHS = [
    'i',
    'ii',
    'iii',
    'iv',
    'v',
    'vi',
    'vii',
    'viii',
    'ix',
    'x',
    'xi',
    'xii',
];

/** Lowercase and strip Polish diacritics (ł does not decompose, so it is mapped by hand). */
function normalize(text: string): string {
    return text
        .toLowerCase()
        .replace(/ł/g, 'l')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

const MONTH_LOOKUP: ReadonlyMap<string, number> = new Map(
    MONTHS.flatMap((names, index) =>
        names.map((name) => [normalize(name), index + 1] as const),
    ),
);

const APPROXIMATE_PREFIX = /^(?:okolo|ok|ca|~)\.?\s*/;
const BEFORE_PREFIX = /^przed(?:\s+|$)/;
const AFTER_PREFIX = /^po(?:\s+|$)/;
const RANGE_DASH = /^(\d{4})\s*[-\u2013\u2014]\s*(\d{4})$/;
const RANGE_BETWEEN = /^miedzy\s+(\d{4})\s+(?:a|i)\s+(\d{4})$/;
const SEPARATORS = /[./\-\s]+/;

function parseMonth(token: string): number | null {
    if (/^\d{1,2}$/.test(token)) {
        const month = Number(token);

        return month >= 1 && month <= 12 ? month : null;
    }

    const roman = ROMAN_MONTHS.indexOf(token);

    if (roman !== -1) {
        return roman + 1;
    }

    return MONTH_LOOKUP.get(token) ?? null;
}

function parseDay(token: string): number | null {
    if (!/^\d{1,2}$/.test(token)) {
        return null;
    }

    const day = Number(token);

    return day >= 1 && day <= 31 ? day : null;
}

/** Turn Polish genealogy notation into the date parts the server validates. */
export function parsePartialDate(text: string): ParsedPartialDate {
    let rest = normalize(text).trim();

    if (rest === '') {
        return { kind: 'empty' };
    }

    const range = rest.match(RANGE_DASH) ?? rest.match(RANGE_BETWEEN);

    if (range) {
        const year = Number(range[1]);
        const untilYear = Number(range[2]);

        if (untilYear <= year) {
            return { kind: 'range-order' };
        }

        return {
            kind: 'ok',
            parts: {
                year,
                month: null,
                day: null,
                qualifier: 'range',
                until_year: untilYear,
            },
            precision: 'range',
        };
    }

    let qualifier: DateQualifier = 'exact';
    const prefix =
        rest.match(APPROXIMATE_PREFIX) ??
        rest.match(BEFORE_PREFIX) ??
        rest.match(AFTER_PREFIX);

    if (prefix) {
        qualifier = rest.match(APPROXIMATE_PREFIX)
            ? 'approximate'
            : rest.match(BEFORE_PREFIX)
              ? 'before'
              : 'after';
        rest = rest.slice(prefix[0].length);
    }

    const tokens = rest.split(SEPARATORS).filter((token) => token !== '');

    if (tokens.length === 0 || tokens.length > 3) {
        return { kind: 'invalid' };
    }

    const yearToken = tokens[tokens.length - 1];
    const leading = tokens.slice(0, -1);

    let month: number | null = null;
    let day: number | null = null;

    if (leading.length === 2) {
        day = parseDay(leading[0]);
        month = parseMonth(leading[1]);

        if (day === null || month === null) {
            return { kind: 'invalid' };
        }
    } else if (leading.length === 1) {
        month = parseMonth(leading[0]);

        if (month === null) {
            return { kind: 'invalid' };
        }
    }

    if (!/^\d+$/.test(yearToken)) {
        return { kind: 'invalid' };
    }

    if (yearToken.length === 2) {
        return { kind: 'two-digit-year' };
    }

    if (yearToken.length !== 4) {
        return { kind: 'invalid' };
    }

    return {
        kind: 'ok',
        parts: {
            year: Number(yearToken),
            month,
            day,
            qualifier,
            until_year: null,
        },
        precision: day !== null ? 'day' : month !== null ? 'month' : 'year',
    };
}

/**
 * `short` matches the server's `PartialDate::format` (12.03.1948, 03.1948, 1948, prefixes
 * "ok. ", "przed ", "po ", range 1805–1811); `long` is the card display (12 marca 1948,
 * marzec 1948, około 2015, przed 1809, między 1805 a 1811).
 */
export function formatPartialDate(
    parts: {
        year: number | null;
        month: number | null;
        day: number | null;
        qualifier?: DateQualifier;
        until_year?: number | null;
    },
    style: 'short' | 'long',
): string {
    if (parts.year === null) {
        return '';
    }

    const year = String(parts.year).padStart(4, '0');
    const { month, day } = parts;
    const qualifier = parts.qualifier ?? 'exact';
    let formatted: string;

    if (qualifier === 'range' && parts.until_year != null) {
        const until = String(parts.until_year).padStart(4, '0');

        return style === 'short'
            ? `${year}\u2013${until}`
            : `między ${year} a ${until}`;
    }

    if (style === 'short') {
        if (month !== null && day !== null) {
            formatted = `${String(day).padStart(2, '0')}.${String(month).padStart(2, '0')}.${year}`;
        } else if (month !== null) {
            formatted = `${String(month).padStart(2, '0')}.${year}`;
        } else {
            formatted = year;
        }

        return withQualifier(qualifier, formatted, 'ok. ');
    }

    if (month !== null && day !== null) {
        formatted = `${day} ${MONTHS[month - 1][1]} ${year}`;
    } else if (month !== null) {
        formatted = `${MONTHS[month - 1][0]} ${year}`;
    } else {
        formatted = year;
    }

    return withQualifier(qualifier, formatted, 'około ');
}

function withQualifier(
    qualifier: DateQualifier,
    formatted: string,
    approximateWord: string,
): string {
    switch (qualifier) {
        case 'approximate':
            return `${approximateWord}${formatted}`;
        case 'before':
            return `przed ${formatted}`;
        case 'after':
            return `po ${formatted}`;
        default:
            return formatted;
    }
}

export function describePrecision(precision: DatePrecision): string {
    switch (precision) {
        case 'day':
            return 'dokładna data';
        case 'month':
            return 'miesiąc i rok';
        case 'year':
            return 'tylko rok';
        case 'range':
            return 'zakres lat';
    }
}
