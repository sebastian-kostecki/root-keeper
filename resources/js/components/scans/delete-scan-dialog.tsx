import { router } from '@inertiajs/react';
import { useState } from 'react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
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
import type { Scan, ScanConfirmation } from '@/types';

type Props = {
    scan: Scan;
    confirms: ScanConfirmation[];
    trigger: React.ReactNode;
};

export function DeleteScanDialog({ scan, confirms, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(ScanController.destroy(scan.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Usuń skan</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz usunąć skan „{scan.original_name}
                    ”? Skan i jego plik zostaną trwale usunięte. Tej operacji
                    nie można cofnąć.
                </DialogDescription>

                {confirms.length === 0 ? (
                    <p className="text-sm">
                        Ten skan nie potwierdza żadnego wydarzenia.
                    </p>
                ) : (
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-sm">
                            Te fakty stracą to źródło:
                        </p>
                        <ul className="space-y-1">
                            {confirms.map((confirmation) => (
                                <li
                                    key={`${confirmation.event_id}-${confirmation.person.id}`}
                                    className="text-sm"
                                >
                                    {confirmation.event_label} —{' '}
                                    {confirmation.person.display_name} —{' '}
                                    {confirmation.is_last_scan ? (
                                        <span className="text-destructive font-medium">
                                            stanie się niepotwierdzone
                                        </span>
                                    ) : (
                                        <span>
                                            pozostanie potwierdzone innym skanem
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

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
