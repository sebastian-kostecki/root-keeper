import { router } from '@inertiajs/react';
import { useState } from 'react';
import ParishController from '@/actions/App/Http/Controllers/ParishController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { parishUsagePhrase } from '@/lib/places-usage';
import type { Parish } from '@/types';

type Props = {
    parish: Parish;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Closes this dialog and opens the merge dialog for the same parish. */
    onMerge: () => void;
};

/** Deletes an unused parish; a used one is explained and sent to „Scal z…”. */
export function DeleteParishDialog({
    parish,
    open,
    onOpenChange,
    onMerge,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const count = parish.sources_count ?? 0;
    const inUse = count > 0;

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(ParishController.destroy(parish.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);
                setErrors({});
            }}
        >
            <DialogContent>
                {inUse ? (
                    <>
                        <DialogTitle>Nie można usunąć</DialogTitle>
                        <DialogDescription>
                            Parafia „{parish.label}” jest używana w:{' '}
                            {parishUsagePhrase(count)}. Scal ją z właściwą, aby
                            przenieść te akty.
                        </DialogDescription>
                    </>
                ) : (
                    <>
                        <DialogTitle>Usunąć parafię?</DialogTitle>
                        <DialogDescription>
                            Parafia „{parish.label}” zostanie usunięta ze spisu.
                        </DialogDescription>
                    </>
                )}

                <InputError message={errors.parish} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {inUse ? 'Zamknij' : 'Anuluj'}
                        </Button>
                    </DialogClose>

                    {inUse ? (
                        <Button
                            type="button"
                            onClick={() => {
                                setErrors({});
                                onMerge();
                            }}
                        >
                            Scal z…
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={processing}
                            onClick={handleConfirm}
                        >
                            {processing && <Spinner />}
                            Usuń parafię
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
