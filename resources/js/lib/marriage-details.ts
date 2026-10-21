import type { Marriage } from '@/types';

/** Secondary line of a marriage row: „ślub {form}, date, place”, known parts only. */
export function marriageRowDetails(
    marriage: Pick<Marriage, 'marriage_form_label' | 'date' | 'place'>,
): string {
    return [
        marriage.marriage_form_label
            ? `ślub ${marriage.marriage_form_label}`
            : null,
        marriage.date,
        marriage.place,
    ]
        .filter(Boolean)
        .join(', ');
}
