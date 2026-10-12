import { pluralPhrase } from '@/lib/plural';
import type { PersonAge } from '@/types';

/** Polish text for a person's age: "72 lata", "2 mies.", "poniżej roku". */
export function formatAge(age: PersonAge): string {
    const value = age.value ?? 0;

    switch (age.unit) {
        case 'years':
            return `${value} ${pluralPhrase(value, 'rok', 'lata', 'lat')}`;
        case 'months':
            return `${value} mies.`;
        case 'days':
            return value === 0
                ? 'poniżej dnia'
                : `${value} ${pluralPhrase(value, 'dzień', 'dni', 'dni')}`;
        case 'under_year':
            return 'poniżej roku';
    }
}
