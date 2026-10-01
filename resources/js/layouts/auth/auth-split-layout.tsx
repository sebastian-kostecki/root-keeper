import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import AuthBrandPanel from '@/components/auth/auth-brand-panel';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-background grid min-h-svh lg:grid-cols-2">
            <AuthBrandPanel />
            <div className="flex min-h-svh flex-col">
                <div className="p-6 lg:hidden">
                    <Link
                        href={home()}
                        className="focus-visible:ring-ring flex w-fit items-center rounded-md outline-none focus-visible:ring-2"
                    >
                        <AppLogo />
                    </Link>
                </div>
                <div className="flex flex-1 items-center justify-center px-6 py-6 lg:p-10">
                    <div className="flex w-full max-w-[360px] flex-col gap-6">
                        <div className="flex flex-col items-start gap-2 text-left">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground text-sm">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
                <p className="text-muted-foreground px-6 pb-6 text-center text-xs lg:hidden">
                    Drzewo genealogiczne, w którym każda data ma źródło.
                </p>
            </div>
        </div>
    );
}
