import { router } from '@inertiajs/react';
import { useState } from 'react';
import EventSourceController from '@/actions/App/Http/Controllers/EventSourceController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LinkedSource } from '@/types';

type Props = {
    eventId: number;
    source: LinkedSource;
    trigger: React.ReactNode;
};

export function EntryNumberDialog({ eventId, source, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [value, setValue] = useState(source.entry_number ?? '');
    const [error, setError] = useState<string | undefined>();

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setValue(source.entry_number ?? '');
            setError(undefined);
        }

        setOpen(next);
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        router.patch(
            EventSourceController.update([eventId, source.id]).url,
            { entry_number: value.trim() === '' ? null : value.trim() },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (errors) => setError(errors.entry_number),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <DialogTitle>Numer wpisu</DialogTitle>
                    <DialogDescription>
                        Numer wpisu w źródle „{source.display_name}”. Puste pole
                        usuwa numer.
                    </DialogDescription>

                    <div className="grid gap-2">
                        <Label htmlFor={`entry-number-${eventId}-${source.id}`}>
                            Numer wpisu
                        </Label>
                        <Input
                            id={`entry-number-${eventId}-${source.id}`}
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={error} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Anuluj
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Zapisz
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
