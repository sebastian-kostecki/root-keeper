import { router } from '@inertiajs/react';
import { useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
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
import type { Source, SourceConfirmation } from '@/types';

type Props = {
    source: Source;
    confirms: SourceConfirmation[];
    trigger: React.ReactNode;
};

export function DeleteSourceDialog({ source, confirms, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const buried = source.buried_people ?? [];

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(SourceController.destroy(source.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                {confirms.length === 0 ? (
                    <>
                        <DialogTitle>Usunąć źródło?</DialogTitle>
                        <DialogDescription>
                            Źródło „{source.display_name}” i jego plik zostaną
                            trwale usunięte.
                        </DialogDescription>
                    </>
                ) : (
                    <>
                        <DialogTitle>Usuń źródło</DialogTitle>
                        <DialogDescription>
                            Czy na pewno chcesz usunąć źródło „
                            {source.display_name}”? Źródło i jego plik zostaną
                            trwale usunięte. Tej operacji nie można cofnąć.
                        </DialogDescription>

                        <div className="space-y-1">
                            <p className="text-muted-foreground text-sm">
                                Te fakty stracą to źródło:
                            </p>
                            <ul className="space-y-1">
                                {confirms.map((confirmation) => (
                                    <li
                                        key={`${confirmation.event_id}-${confirmation.person.id}`}
                                        className="text-sm"
                                    >
                                        {confirmation.event_label} —{' '}
                                        {confirmation.person.display_name} —{' '}
                                        {confirmation.is_last_source ? (
                                            <span className="text-destructive-foreground font-medium">
                                                stanie się niepotwierdzone
                                            </span>
                                        ) : (
                                            <span>
                                                pozostanie potwierdzone innym
                                                źródłem
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </>
                )}

                {buried.length > 0 && (
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-sm">
                            Ten nagrobek zniknie z listy pochowanych:
                        </p>
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            {buried.map((person) => (
                                <li key={person.id}>{person.display_name}</li>
                            ))}
                        </ul>
                        <p className="text-muted-foreground text-sm">
                            Miejsca pochowania tych osób zostają.
                        </p>
                    </div>
                )}

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
