import { router } from '@inertiajs/react';
import { useState } from 'react';
import ParentChildRelationController from '@/actions/App/Http/Controllers/ParentChildRelationController';
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

type Props = {
    relationId: number;
    parentName: string;
    childName: string;
    trigger?: React.ReactNode;
    /** Controlled mode: when given, no trigger is rendered. */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

export function RemoveRelationDialog({
    relationId,
    parentName,
    childName,
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

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(ParentChildRelationController.destroy(relationId).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogTitle>Usuń relację</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz usunąć relację: „{parentName}” jest
                    rodzicem „{childName}”? Obie osoby pozostaną w drzewie. Tej
                    operacji nie można cofnąć.
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
