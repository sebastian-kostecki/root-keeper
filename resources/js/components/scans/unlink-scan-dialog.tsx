import { router } from '@inertiajs/react';
import { useState } from 'react';
import EventScanController from '@/actions/App/Http/Controllers/EventScanController';
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
import type { LinkedScan } from '@/types';

type Props = {
    eventId: number;
    scan: LinkedScan;
    isLastScan: boolean;
    eventLabel: string;
    trigger: React.ReactNode;
};

export function UnlinkScanDialog({
    eventId,
    scan,
    isLastScan,
    eventLabel,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(
            EventScanController.destroy({ event: eventId, scan: scan.id }).url,
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Odepnij skan</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz odpiąć skan „{scan.original_name}” od
                    wydarzenia „{eventLabel}”?
                    {isLastScan && <> Wydarzenie stanie się niepotwierdzone.</>}
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
                        Odepnij
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
