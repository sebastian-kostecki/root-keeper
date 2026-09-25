import { router } from '@inertiajs/react';
import { useState } from 'react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
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
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
    trigger: React.ReactNode;
};

export function DeletePersonDialog({ person, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const marriageScanCount = person.marriages.reduce(
        (total, marriage) => total + marriage.scans.length,
        0,
    );
    const linkCount =
        (person.birth?.scans.length ?? 0) +
        (person.death?.scans.length ?? 0) +
        marriageScanCount;
    const relationCount = person.parents.length + person.children.length;
    const marriageCount = person.marriages.length;

    const extras: string[] = [];

    if (linkCount > 0) {
        extras.push(
            `${linkCount} ${pluralPhrase(linkCount, 'powiązanie', 'powiązania', 'powiązań')} ze skanami aktów`,
        );
    }

    if (relationCount > 0) {
        extras.push(
            `${relationCount} ${pluralPhrase(relationCount, 'relacja rodzinna', 'relacje rodzinne', 'relacji rodzinnych')}`,
        );
    }

    if (marriageCount > 0) {
        extras.push(
            `${marriageCount} ${pluralPhrase(marriageCount, 'małżeństwo', 'małżeństwa', 'małżeństw')} (zniknie także u drugiego małżonka)`,
        );
    }

    const handleConfirm = () => {
        setProcessing(true);

        router.delete(PersonController.destroy(person.id).url, {
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Usuń osobę</DialogTitle>
                <DialogDescription>
                    Czy na pewno chcesz usunąć osobę „{person.display_name}
                    ”? Usunięte zostaną jej dane i urodzenie
                    {person.death && <> oraz zgon</>}
                    {extras.length > 0 && <>, a także {extras.join(' i ')}</>}.
                    Skany pozostaną w bibliotece skanów.
                    {relationCount > 0 && (
                        <> Powiązane osoby pozostaną w drzewie.</>
                    )}{' '}
                    Tej operacji nie można cofnąć.
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
