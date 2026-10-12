import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onCancel: () => void;
    onDiscard: () => void;
};

export function DiscardChangesDialog({ open, onCancel, onDiscard }: Props) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onCancel();
                }
            }}
        >
            <DialogContent>
                <DialogTitle>Odrzucić niezapisane zmiany?</DialogTitle>
                <DialogDescription>
                    Zmiany wprowadzone w panelu zostaną utracone.
                </DialogDescription>
                <DialogFooter>
                    <Button variant="outline" onClick={onCancel}>
                        Wróć do edycji
                    </Button>
                    <Button variant="destructive" onClick={onDiscard}>
                        Odrzuć
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
