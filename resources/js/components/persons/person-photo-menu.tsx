import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import PersonPhotoController from '@/actions/App/Http/Controllers/PersonPhotoController';
import { PersonAvatar } from '@/components/persons/person-avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { personPhotoUrl } from '@/lib/person-photo';
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
    /** Validation message for the photo field, or null when it is clear. */
    onErrorChange: (message: string | null) => void;
};

/**
 * Avatar of the person panel as a button with the photo menu. Choosing a file uploads it
 * at once; the server answers with `back()`, so the panel stays open and keeps its state.
 */
export function PersonPhotoMenu({ person, onErrorChange }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const hasPhoto = person.photo_version !== null;

    const handleFileChange = (): void => {
        const file = fileInputRef.current?.files?.[0];

        if (!file) {
            return;
        }

        router.post(
            PersonPhotoController.store(person.id).url,
            { photo: file },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    setProcessing(true);
                    onErrorChange(null);
                },
                onError: (errors) => onErrorChange(errors.photo ?? null),
                onFinish: () => {
                    setProcessing(false);

                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            },
        );
    };

    const handleDelete = (): void => {
        router.delete(PersonPhotoController.destroy(person.id).url, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                setProcessing(true);
                onErrorChange(null);
            },
            onSuccess: () => setIsDeleteOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label="Zdjęcie osoby"
                        disabled={processing}
                        className="focus-visible:ring-ring/50 relative shrink-0 rounded-full outline-none focus-visible:ring-[3px] disabled:cursor-wait"
                    >
                        <PersonAvatar
                            name={person.display_name}
                            gender={person.gender?.value ?? null}
                            photoUrl={personPhotoUrl(
                                person.id,
                                person.photo_version,
                            )}
                        />
                        {processing && (
                            <span className="bg-background/60 absolute inset-0 flex items-center justify-center rounded-full">
                                <Spinner />
                            </span>
                        )}
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        disabled={processing}
                        onSelect={() => fileInputRef.current?.click()}
                    >
                        {hasPhoto ? 'Zmień zdjęcie' : 'Dodaj zdjęcie'}
                    </DropdownMenuItem>
                    {hasPhoto && (
                        <DropdownMenuItem
                            variant="destructive"
                            disabled={processing}
                            onSelect={() => setIsDeleteOpen(true)}
                        >
                            Usuń zdjęcie
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <input
                ref={fileInputRef}
                type="file"
                name="photo"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleFileChange}
            />

            <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <DialogContent>
                    <DialogTitle>Usunąć zdjęcie osoby?</DialogTitle>
                    <DialogDescription>
                        Zdjęcie zniknie z panelu, karty w drzewie i listy osób.
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
                            onClick={handleDelete}
                        >
                            Usuń
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
