import { CheckIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

/** ✓ after a date backed by a source; the meaning is read out for screen readers. */
export function ConfirmedMark() {
    return (
        <>
            <CheckIcon
                aria-hidden="true"
                className="text-primary inline size-3.5 shrink-0 align-[-2px]"
            />
            <span className="sr-only">potwierdzone aktem</span>
        </>
    );
}

export function ProbandBadge() {
    return (
        <Badge variant="secondary" className="shrink-0">
            Probant
        </Badge>
    );
}
