import { Badge } from '@/components/ui/badge';
import type { ConfidenceLevel } from '@/types';

type Props = {
    level: ConfidenceLevel | null;
    /** Meaning of the letter, e.g. „akt pierwotny”. */
    label: string | null;
};

/** Neutral „Poziom A” marker of a fact; nothing when the level is unknown. */
export function ConfidenceBadge({ level, label }: Props) {
    if (level === null) {
        return null;
    }

    const meaning = label ? `${level} — ${label}` : level;

    return (
        <Badge
            variant="outline"
            className="text-muted-foreground border bg-transparent"
            title={meaning}
        >
            <span className="sr-only">Poziom pewności: {meaning}</span>
            <span aria-hidden="true">Poziom {level}</span>
        </Badge>
    );
}
