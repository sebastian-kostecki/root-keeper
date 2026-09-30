import type { PersonEvent, Source } from '@/types';

type MatchFields = Pick<Source, 'kind' | 'record_type'>;

/**
 * Divides the picker list for one event: the akty metrykalne typed for that
 * event go to `matching`, everything else to `rest`. Both keep the input order.
 */
export function splitSourcesForEvent<T extends MatchFields>(
    sources: readonly T[],
    eventType: PersonEvent['type'],
): { matching: T[]; rest: T[] } {
    const matching: T[] = [];
    const rest: T[] = [];

    for (const source of sources) {
        if (
            source.kind === 'metrical_record' &&
            source.record_type === eventType
        ) {
            matching.push(source);
        } else {
            rest.push(source);
        }
    }

    return { matching, rest };
}
