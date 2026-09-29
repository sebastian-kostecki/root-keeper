import { Button } from '@/components/ui/button';
import { useSetRootPerson } from '@/hooks/use-set-root-person';

export function SetRootPersonButton({ personId }: { personId: number }) {
    const { setRootPerson, processing } = useSetRootPerson(personId);

    return (
        <Button
            variant="outline"
            size="sm"
            disabled={processing}
            onClick={setRootPerson}
        >
            Ustaw jako probanta
        </Button>
    );
}
