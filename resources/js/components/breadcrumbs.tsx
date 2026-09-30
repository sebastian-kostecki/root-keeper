import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    if (breadcrumbs.length <= 1) {
        return null;
    }

    const parents = breadcrumbs.slice(0, -1);

    return (
        <Breadcrumb aria-label="Ścieżka" className="mb-0.5">
            <BreadcrumbList>
                {parents.map((item, index) => (
                    <Fragment key={index}>
                        <BreadcrumbItem>
                            <BreadcrumbLink asChild>
                                <Link href={item.href}>{item.title}</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                    </Fragment>
                ))}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
