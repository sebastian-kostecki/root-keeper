export type PartialDateParts = {
    year: number | null;
    month: number | null;
    day: number | null;
    is_approximate: boolean;
};

export type DatePrecision = 'year' | 'month' | 'day';

export type ParsedPartialDate =
    | { kind: 'empty' }
    | {
          kind: 'ok';
          parts: PartialDateParts;
          precision: DatePrecision;
      }
    | { kind: 'two-digit-year' }
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

    const prefix = rest.match(APPROXIMATE_PREFIX);
    const isApproximate = prefix !== null;

    if (prefix) {
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
            is_approximate: isApproximate,
        },
        precision: day !== null ? 'day' : month !== null ? 'month' : 'year',
    };
}

/**
 * `short` matches the server's `PartialDate::format` (12.03.1948, 03.1948, 1948, prefix "ok. ");
 * `long` is the card display (12 marca 1948, marzec 1948, około 2015).
 */
export function formatPartialDate(
    parts: {
        year: number | null;
        month: number | null;
        day: number | null;
        is_approximate?: boolean;
    },
    style: 'short' | 'long',
): string {
    if (parts.year === null) {
        return '';
    }

    const year = String(parts.year).padStart(4, '0');
    const { month, day } = parts;
    let formatted: string;

    if (style === 'short') {
        if (month !== null && day !== null) {
            formatted = `${String(day).padStart(2, '0')}.${String(month).padStart(2, '0')}.${year}`;
        } else if (month !== null) {
            formatted = `${String(month).padStart(2, '0')}.${year}`;
        } else {
            formatted = year;
        }

        return parts.is_approximate ? `ok. ${formatted}` : formatted;
    }

    if (month !== null && day !== null) {
        formatted = `${day} ${MONTHS[month - 1][1]} ${year}`;
    } else if (month !== null) {
        formatted = `${MONTHS[month - 1][0]} ${year}`;
    } else {
        formatted = year;
    }

    return parts.is_approximate ? `około ${formatted}` : formatted;
}

export function describePrecision(precision: DatePrecision): string {
    switch (precision) {
        case 'day':
            return 'dokładna data';
        case 'month':
            return 'miesiąc i rok';
        case 'year':
            return 'tylko rok';
    }
}
