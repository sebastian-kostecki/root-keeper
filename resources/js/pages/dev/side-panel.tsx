import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { SidePanel } from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { panelVisitOptions } from '@/lib/side-panel-url';
import { sidePanel } from '@/routes/dev';

type DemoItem = {
    id: number;
    title: string;
    body: string;
    wide: boolean;
};

type Props = {
    items: DemoItem[];
    demoPanel: DemoItem | null;
};

export default function SidePanelDemo({ items, demoPanel }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Panel boczny (demo)', href: sidePanel() }],
    });

    const { hrefFor, close } = useSidePanelNavigation('demo');
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const [confirmingClose, setConfirmingClose] = useState(false);

    const requestClose = () => {
        if (hasUnsavedChanges) {
            setConfirmingClose(true);

            return;
        }

        close();
    };

    const discardAndClose = () => {
        setConfirmingClose(false);
        setHasUnsavedChanges(false);
        close();
    };

    const nextItem =
        demoPanel === null
            ? null
            : (items[
                  (items.findIndex((item) => item.id === demoPanel.id) + 1) %
                      items.length
              ] ?? null);

    return (
        <>
            <Head title="Panel boczny (demo)" />

            <div className="space-y-6">
                <Heading title="Panel boczny (demo)" variant="page" />

                <ul className="space-y-1">
                    {items.map((item) => (
                        <li key={item.id}>
                            <Link
                                href={hrefFor(item.id)}
                                {...panelVisitOptions}
                                className="text-sm underline"
                            >
                                {item.title}
                                {item.wide ? ' (szeroki panel)' : ''}
                            </Link>
                        </li>
                    ))}
                </ul>

                <p className="max-w-prose text-sm">
                    {'Płynna treść strony zwęża się, gdy panel jest otwarty. '.repeat(
                        12,
                    )}
                </p>

                <div className="overflow-x-auto pb-2">
                    <div className="grid min-w-[68rem] grid-cols-8 gap-x-2">
                        {Array.from({ length: 8 }, (_, i) => (
                            <div
                                key={i}
                                className="bg-muted rounded-md p-3 text-sm"
                            >
                                Kolumna {i + 1}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="space-y-2">
                    {Array.from({ length: 30 }, (_, i) => (
                        <p key={i} className="text-muted-foreground text-sm">
                            Wiersz {i + 1}: treść dłuższa niż okno, do
                            sprawdzenia przewijania.
                        </p>
                    ))}
                </div>
            </div>

            {demoPanel !== null && (
                <SidePanel
                    title={demoPanel.title}
                    variant={demoPanel.wide ? 'wide' : 'standard'}
                    contentKey={demoPanel.id}
                    onRequestClose={requestClose}
                >
                    <div className="space-y-4 text-sm">
                        <p>{demoPanel.body}</p>

                        {nextItem !== null && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={hrefFor(nextItem.id)}
                                    {...panelVisitOptions}
                                >
                                    Następny rekord
                                </Link>
                            </Button>
                        )}

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={hasUnsavedChanges}
                                onChange={(event) =>
                                    setHasUnsavedChanges(event.target.checked)
                                }
                            />
                            Mam niezapisane zmiany
                        </label>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    Otwórz modal
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Zagnieżdżony modal</DialogTitle>
                                <DialogDescription>
                                    Escape zamyka tylko ten modal, nie panel.
                                </DialogDescription>
                            </DialogContent>
                        </Dialog>
                    </div>

                    <Dialog
                        open={confirmingClose}
                        onOpenChange={setConfirmingClose}
                    >
                        <DialogContent>
                            <DialogTitle>Porzucić zmiany?</DialogTitle>
                            <DialogDescription>
                                Niezapisane zmiany w panelu zostaną utracone.
                            </DialogDescription>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirmingClose(false)}
                                >
                                    Wróć do edycji
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={discardAndClose}
                                >
                                    Porzuć zmiany
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </SidePanel>
            )}
        </>
    );
}
