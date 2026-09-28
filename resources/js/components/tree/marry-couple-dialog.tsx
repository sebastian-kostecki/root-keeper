import { router } from '@inertiajs/react';
import { useState } from 'react';
import MarriageController from '@/actions/App/Http/Controllers/MarriageController';
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

type Person = { id: number; display_name: string };

type Props = {
    first: Person;
    second: Person;
};

/**
 * "Połącz jako małżeństwo" for a pair the sheet shows side by side: a
 * confirmation that creates an undated marriage through the existing
 * marriage endpoint. Date and place are filled in the person preview.
 */
export function MarryCoupleDialog({ first, second }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleConfirm = () => {
        setProcessing(true);

        router.post(
            MarriageController.store().url,
            { person_id: first.id, spouse_id: second.id },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: setErrors,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setErrors({});
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label={`Połącz jako małżeństwo: ${first.display_name} i ${second.display_name}`}
                >
                    Połącz jako małżeństwo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Połącz jako małżeństwo</DialogTitle>
                <DialogDescription>
                    Połączyć „{first.display_name}” i „{second.display_name}”
                    jako małżeństwo? Datę, miejsce i potwierdzenie ślubu
                    uzupełnisz w podglądzie osoby.
                </DialogDescription>

                <InputError message={errors.spouse_id ?? errors.person_id} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Anuluj
                        </Button>
                    </DialogClose>

                    <Button
                        type="button"
                        disabled={processing}
                        onClick={handleConfirm}
                    >
                        Połącz
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
