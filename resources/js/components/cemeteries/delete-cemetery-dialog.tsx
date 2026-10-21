import { router } from '@inertiajs/react';
import { useState } from 'react';
import CemeteryController from '@/actions/App/Http/Controllers/CemeteryController';
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
import { cemeteryUsagePhrase } from '@/lib/places-usage';
import type { Cemetery } from '@/types';

type Props = {
    cemetery: Cemetery;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Closes this dialog and opens the merge dialog for the same cemetery. */
    onMerge: () => void;
};

/** Deletes an unused cemetery; a used one is explained and sent to „Scal z…”. */
export function DeleteCemeteryDialog({
    cemetery,
    open,
    onOpenChange,
    onMerge,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const sources = cemetery.sources_count ?? 0;
    const burials = cemetery.burials_count ?? 0;
    const inUse = sources + burials > 0;

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(CemeteryController.destroy(cemetery.id).url, {
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
                            Cmentarz „{cemetery.label}” jest używany w:{' '}
                            {cemeteryUsagePhrase(sources, burials)}. Scal go z
                            właściwym, aby przenieść te odwołania.
                        </DialogDescription>
                    </>
                ) : (
                    <>
                        <DialogTitle>Usunąć cmentarz?</DialogTitle>
                        <DialogDescription>
                            Cmentarz „{cemetery.label}” zostanie usunięty ze
                            spisu.
                        </DialogDescription>
                    </>
                )}

                <InputError message={errors.cemetery} />

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
                            Usuń cmentarz
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
