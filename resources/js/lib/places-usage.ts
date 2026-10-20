import { pluralPhrase } from '@/lib/plural';
import type { LocalityUsage } from '@/types';

/** Usage of a locality without any reference; stands in while the counts are not loaded. */
export const noLocalityUsage: LocalityUsage = {
    facts: 0,
    parishes: 0,
    residences: 0,
    cemeteries: 0,
    gravestones: 0,
    total: 0,
};

/** A count with its noun in the right Polish form, e.g. „3 faktach”. */
function countOf(
    count: number,
    one: string,
    few: string,
    many: string,
): string {
    return `${count} ${pluralPhrase(count, one, few, many)}`;
}

/** Polish list: „A, B i C”. */
function joinPolish(items: string[]): string {
    if (items.length <= 1) {
        return items[0] ?? '';
    }

    return `${items.slice(0, -1).join(', ')} i ${items[items.length - 1]}`;
}

/**
 * Locative list for „… jest używana w: …”, e.g. „3 faktach, 1 parafii i 2 zamieszkaniach”.
 * Zero counts are left out; returns '' when nothing is used (callers show it only when `total > 0`).
 */
export function localityUsagePhrase(usage: LocalityUsage): string {
    const parts: string[] = [];

    if (usage.facts > 0) {
        parts.push(countOf(usage.facts, 'fakcie', 'faktach', 'faktach'));
    }

    if (usage.parishes > 0) {
        parts.push(
            countOf(usage.parishes, 'parafii', 'parafiach', 'parafiach'),
        );
    }

    if (usage.residences > 0) {
        parts.push(
            countOf(
                usage.residences,
                'zamieszkaniu',
                'zamieszkaniach',
                'zamieszkaniach',
            ),
        );
    }

    if (usage.cemeteries > 0) {
        parts.push(
            countOf(
                usage.cemeteries,
                'cmentarzu',
                'cmentarzach',
                'cmentarzach',
            ),
        );
    }

    if (usage.gravestones > 0) {
        parts.push(
            countOf(usage.gravestones, 'nagrobku', 'nagrobkach', 'nagrobkach'),
        );
    }

    return joinPolish(parts);
}

/**
 * Nominative list for the merge dialog, e.g. „3 fakty, 1 parafia”; „brak” when nothing is used.
 */
export function localityUsageNominative(usage: LocalityUsage): string {
    const parts: string[] = [];

    if (usage.facts > 0) {
        parts.push(countOf(usage.facts, 'fakt', 'fakty', 'faktów'));
    }

    if (usage.parishes > 0) {
        parts.push(countOf(usage.parishes, 'parafia', 'parafie', 'parafii'));
    }

    if (usage.residences > 0) {
        parts.push(
            countOf(
                usage.residences,
                'zamieszkanie',
                'zamieszkania',
                'zamieszkań',
            ),
        );
    }

    if (usage.cemeteries > 0) {
        parts.push(
            countOf(usage.cemeteries, 'cmentarz', 'cmentarze', 'cmentarzy'),
        );
    }

    if (usage.gravestones > 0) {
        parts.push(
            countOf(usage.gravestones, 'nagrobek', 'nagrobki', 'nagrobków'),
        );
    }

    return parts.length === 0 ? 'brak' : joinPolish(parts);
}

/** Locative count of acts for „… jest używana w: 4 aktach”. */
export function parishUsagePhrase(count: number): string {
    return countOf(count, 'akcie', 'aktach', 'aktach');
}

/** Nominative count of acts for the merge dialog, e.g. „4 akty”; „brak” when none. */
export function parishUsageNominative(count: number): string {
    return count === 0 ? 'brak' : countOf(count, 'akt', 'akty', 'aktów');
}

/** Locative list for a cemetery, e.g. „2 nagrobkach i 1 pochowaniu”. */
export function cemeteryUsagePhrase(sources: number, burials: number): string {
    const parts: string[] = [];

    if (sources > 0) {
        parts.push(countOf(sources, 'nagrobku', 'nagrobkach', 'nagrobkach'));
    }

    if (burials > 0) {
        parts.push(
            countOf(burials, 'pochowaniu', 'pochowaniach', 'pochowaniach'),
        );
    }

    return joinPolish(parts);
}

/** Nominative list for the merge dialog, e.g. „2 nagrobki i 1 pochowanie”; „brak” when none. */
export function cemeteryUsageNominative(
    sources: number,
    burials: number,
): string {
    const parts: string[] = [];

    if (sources > 0) {
        parts.push(countOf(sources, 'nagrobek', 'nagrobki', 'nagrobków'));
    }

    if (burials > 0) {
        parts.push(countOf(burials, 'pochowanie', 'pochowania', 'pochowań'));
    }

    return parts.length === 0 ? 'brak' : joinPolish(parts);
}
