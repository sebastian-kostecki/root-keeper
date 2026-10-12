import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { focusRing } from '@/lib/focus-ring';
import { cn } from '@/lib/utils';
import { index as treeIndex } from '@/routes/tree';

export function AppMobileHeader() {
    const { toggleSidebar } = useSidebar();

    return (
        <header className="border-border flex h-14 shrink-0 items-center gap-2 border-b px-2 md:hidden">
            <Button
                variant="ghost"
                size="icon"
                className="size-11"
                onClick={toggleSidebar}
            >
                <Menu />
                <span className="sr-only">Otwórz menu</span>
            </Button>
            <Link
                href={treeIndex()}
                className={cn('flex items-center gap-2 rounded-md', focusRing)}
            >
                <AppLogo />
            </Link>
        </header>
    );
}
