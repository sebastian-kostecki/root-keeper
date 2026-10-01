import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { index as treeIndex } from '@/routes/tree';

export function AppMobileHeader() {
    const { toggleSidebar } = useSidebar();

    return (
        <header className="border-border flex h-14 shrink-0 items-center gap-2 border-b px-2 md:hidden">
            <Button
                variant="ghost"
                size="icon"
                className="focus-visible:ring-sidebar-ring size-11 focus-visible:ring-2"
                onClick={toggleSidebar}
            >
                <Menu />
                <span className="sr-only">Otwórz menu</span>
            </Button>
            <Link
                href={treeIndex()}
                className="focus-visible:ring-sidebar-ring flex items-center gap-2 rounded-md outline-hidden focus-visible:ring-2"
            >
                <AppLogo />
            </Link>
        </header>
    );
}
