import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { genderSurface } from '@/lib/gender-styles';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

function SheetCard({
    name,
    meta,
    surface,
}: {
    name: string;
    meta: string;
    surface: string;
}) {
    return (
        <div
            className={cn(
                'flex w-40 flex-col gap-1 rounded-md border px-3 py-2.5',
                surface,
            )}
        >
            <span className="text-sm font-medium">{name}</span>
            <span className="text-tree-meta flex items-center gap-1 text-xs">
                <CircleCheck className="size-3" />
                {meta}
            </span>
        </div>
    );
}

export default function AuthBrandPanel() {
    return (
        <div className="bg-sidebar text-sidebar-foreground border-sidebar-border hidden flex-col justify-between border-r px-10 py-8 lg:flex">
            <Link
                href={home()}
                className="focus-visible:ring-sidebar-ring flex w-fit items-center rounded-md outline-none focus-visible:ring-3"
            >
                <AppLogo />
            </Link>

            <div
                aria-hidden="true"
                className="flex flex-col items-center self-center"
            >
                <div className="flex items-center gap-3">
                    <SheetCard
                        name="Ojciec"
                        meta="akt urodzenia"
                        surface={genderSurface.male}
                    />
                    <div className="bg-tree-line h-px w-6" />
                    <SheetCard
                        name="Matka"
                        meta="akt małżeństwa"
                        surface={genderSurface.female}
                    />
                </div>
                <div className="bg-tree-line h-8 w-px" />
                <SheetCard
                    name="Ty"
                    meta="akt chrztu"
                    surface={genderSurface.unknown}
                />
            </div>

            <div className="flex max-w-md flex-col gap-3">
                <p className="text-2xl font-medium">
                    Drzewo genealogiczne, w którym każda data ma źródło.
                </p>
                <p className="text-sidebar-foreground/70 text-sm">
                    Dodawaj osoby i relacje, dołączaj skany aktów i potwierdzaj
                    nimi daty urodzin, ślubów i zgonów.
                </p>
            </div>
        </div>
    );
}
