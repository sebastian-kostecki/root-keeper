import { router } from '@inertiajs/react';
import { useState } from 'react';
import LocalityController from '@/actions/App/Http/Controllers/LocalityController';
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
import { localityUsagePhrase, noLocalityUsage } from '@/lib/places-usage';
import type { Locality } from '@/types';

type Props = {
    locality: Locality;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Closes this dialog and opens the merge dialog for the same locality. */
    onMerge: () => void;
};

/** Deletes an unused locality; a used one is explained and sent to „Scal z…”. */
export function DeleteLocalityDialog({
    locality,
    open,
    onOpenChange,
    onMerge,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const usage = locality.usage ?? noLocalityUsage;
    const inUse = usage.total > 0;

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(LocalityController.destroy(locality.id).url, {
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
                            Miejscowość „{locality.name}” jest używana w:{' '}
                            {localityUsagePhrase(usage)}. Scal ją z właściwą,
                            aby przenieść te odwołania.
                        </DialogDescription>
                    </>
                ) : (
                    <>
                        <DialogTitle>Usunąć miejscowość?</DialogTitle>
                        <DialogDescription>
                            Miejscowość „{locality.name}” zostanie usunięta ze
                            spisu.
                        </DialogDescription>
                    </>
                )}

                <InputError message={errors.locality} />

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
                            Usuń
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
