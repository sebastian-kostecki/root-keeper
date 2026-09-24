import { CheckIcon, TriangleAlertIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type Props = {
    isConfirmed: boolean;
};

export function EventSourceStatus({ isConfirmed }: Props) {
    if (isConfirmed) {
        return (
            <Badge variant="default">
                <CheckIcon />
                Potwierdzone aktem
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
