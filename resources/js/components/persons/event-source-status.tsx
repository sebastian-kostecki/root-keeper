import { CheckIcon, TriangleAlertIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type Props = {
    /** Short labels of the kinds confirming the fact, in the order to show. */
    kinds: string[];
};

export function EventSourceStatus({ kinds }: Props) {
    if (kinds.length > 0) {
        return (
            <Badge
                variant="default"
                className="h-auto max-w-full shrink whitespace-normal"
            >
                <CheckIcon />
                Potwierdzone: {kinds.join(', ')}
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="text-muted-foreground">
            <TriangleAlertIcon />
            Bez źródła
        </Badge>
    );
}
