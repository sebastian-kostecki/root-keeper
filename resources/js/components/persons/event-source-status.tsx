import { CircleDashedIcon, FileTextIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type Props = {
    /** Short labels of the kinds confirming the fact, in the order to show. */
    kinds: string[];
};

export function EventSourceStatus({ kinds }: Props) {
    if (kinds.length > 0) {
        return (
            <Badge
                variant="outline"
                className="bg-source-surface border-source-border text-source h-auto max-w-full shrink whitespace-normal"
            >
                <FileTextIcon />
                <span className="sr-only">Potwierdzone: </span>
                {kinds.join(', ')}
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="text-muted-foreground border bg-transparent"
        >
            <CircleDashedIcon />
            Bez źródła
        </Badge>
    );
}
