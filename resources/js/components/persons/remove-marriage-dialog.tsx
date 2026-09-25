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
import type { Marriage } from '@/types';

type Props = {
    marriage: Marriage;
    personName: string;
    spouseName: string;
    trigger: React.ReactNode;
};

function pluralPhrase(
    count: number,
    one: string,
    few: string,
    many: string,
): string {
    if (count === 1) {
        return one;
    }

    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14)
    ) {
        return few;
    }

    return many;
}

export function RemoveMarriageDialog({
    marriage,
    personName,
    spouseName,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const scanCount = marriage.scans.length;

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
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Usuń małżeństwo</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz usunąć małżeństwo „{personName}” i „
                    {spouseName}”? Zniknie ono u obojga małżonków
                    {scanCount > 0 && (
                        <>
                            ; {scanCount}{' '}
                            {pluralPhrase(
                                scanCount,
                                'powiązanie ze skanami zostanie odpięte',
                                'powiązania ze skanami zostaną odpięte',
                                'powiązań ze skanami zostanie odpiętych',
                            )}{' '}
                            — skany pozostaną
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
