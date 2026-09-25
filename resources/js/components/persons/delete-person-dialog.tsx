import { router } from '@inertiajs/react';
import { useState } from 'react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
    trigger: React.ReactNode;
};

function scanLinkPhrase(count: number): string {
    if (count === 1) {
        return 'powiązanie';
    }

    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14)
    ) {
        return 'powiązania';
    }

    return 'powiązań';
}

export function DeletePersonDialog({ person, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const linkCount =
        (person.birth?.scans.length ?? 0) + (person.death?.scans.length ?? 0);

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(PersonController.destroy(person.id).url, {
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Usuń osobę</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz usunąć osobę „{person.display_name}
                    ”? Usunięte zostaną jej dane i urodzenie
                    {person.death && <> oraz zgon</>}
                    {linkCount > 0 && (
                        <>
                            , a także {linkCount} {scanLinkPhrase(linkCount)} ze
                            skanami aktów
                        </>
                    )}
                    . Skany pozostaną w bibliotece skanów. Tej operacji nie
                    można cofnąć.
                </DialogDescription>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Anuluj
                        </Button>
                    </DialogClose>

                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={handleConfirm}
                    >
                        Usuń
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
