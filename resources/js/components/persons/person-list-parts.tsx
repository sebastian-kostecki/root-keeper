import { FileTextIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

/** Document icon after a date backed by a source; the meaning is read out for screen readers. */
export function ConfirmedMark() {
    return (
        <>
            <FileTextIcon
                aria-hidden="true"
                className="text-source inline size-3.5 shrink-0 align-[-2px]"
            />
            <span className="sr-only">potwierdzone źródłem</span>
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
