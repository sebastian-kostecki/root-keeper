import { router } from '@inertiajs/react';
import { useState } from 'react';
import SourceLegacyEntryNumberController from '@/actions/App/Http/Controllers/SourceLegacyEntryNumberController';
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
import type { Source } from '@/types';

type Props = {
    source: Source;
};

/** The old per-source „Numer wpisu”, read-only; removable only through an explicit confirmation. */
export function LegacyEntryNumber({ source }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    if (source.legacy_entry_number === null) {
        return null;
    }

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(
            SourceLegacyEntryNumberController.url({ source: source.id }),
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="space-y-1 rounded-md border p-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm">
                    Numer wpisu (stary):{' '}
                    <span className="font-medium">
                        {source.legacy_entry_number}
                    </span>
                </p>

                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button type="button" variant="outline" size="sm">
                            Usuń
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Usunąć stary numer wpisu?</DialogTitle>
                        <DialogDescription>
                            Stary numer wpisu „{source.legacy_entry_number}”
                            zostanie trwale usunięty ze źródła.
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
            </div>
            <p className="text-muted-foreground text-sm">
                Przenieś go do powiązania przy fakcie, potem usuń.
            </p>
        </div>
    );
}
