import { router } from '@inertiajs/react';
import { useState } from 'react';
import EventSourceController from '@/actions/App/Http/Controllers/EventSourceController';
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
import type { LinkedSource } from '@/types';

type Props = {
    eventId: number;
    source: LinkedSource;
    isLastSource: boolean;
    eventLabel: string;
    trigger: React.ReactNode;
};

export function UnlinkSourceDialog({
    eventId,
    source,
    isLastSource,
    eventLabel,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(
            EventSourceController.destroy({ event: eventId, source: source.id })
                .url,
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
                <DialogTitle>Odepnij źródło</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz odpiąć źródło „{source.original_name}”
                    od wydarzenia „{eventLabel}”?
                    {isLastSource && (
                        <> Wydarzenie stanie się niepotwierdzone.</>
                    )}
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
