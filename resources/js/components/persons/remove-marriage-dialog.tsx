import { router } from '@inertiajs/react';
import { useState } from 'react';
import MarriageController from '@/actions/App/Http/Controllers/MarriageController';
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
import { pluralPhrase } from '@/lib/plural';
import type { Marriage } from '@/types';

type Props = {
    /** A `Marriage` from the person panel, or the `SourcedPersonEvent` of the marriage panel. */
    marriage: Pick<Marriage, 'id' | 'sources'>;
    personName: string;
    spouseName: string;
    trigger?: React.ReactNode;
    /** Controlled mode: when given, no trigger is rendered. */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

export function RemoveMarriageDialog({
    marriage,
    personName,
    spouseName,
    trigger,
    open: controlledOpen,
    onOpenChange,
}: Props) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = controlledOpen !== undefined;
    const open = isControlled ? controlledOpen : internalOpen;
    const setOpen = (next: boolean) => {
        if (!isControlled) {
            setInternalOpen(next);
        }

        onOpenChange?.(next);
    };
    const [processing, setProcessing] = useState(false);

    const sourceCount = marriage.sources.length;

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(MarriageController.destroy(marriage.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogTitle>Usuń małżeństwo</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz usunąć małżeństwo „{personName}” i „
                    {spouseName}”? Zniknie ono u obojga małżonków
                    {sourceCount > 0 && (
                        <>
                            ; {sourceCount}{' '}
                            {pluralPhrase(
                                sourceCount,
                                'powiązanie ze źródłami zostanie odpięte',
                                'powiązania ze źródłami zostaną odpięte',
                                'powiązań ze źródłami zostanie odpiętych',
                            )}{' '}
                            — źródła pozostaną
                        </>
                    )}
                    . Tej operacji nie można cofnąć.
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
