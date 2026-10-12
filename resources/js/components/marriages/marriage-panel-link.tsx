import { Link } from '@inertiajs/react';
import type * as React from 'react';
import {
    isPlainClick,
    useOptionalSectionEditor,
} from '@/components/persons/section-editor';
import { useMarriagePanel } from '@/hooks/use-marriage-panel';
import { panelVisitOptions } from '@/lib/side-panel-url';

type Props = Omit<
    React.ComponentProps<typeof Link>,
    'href' | 'replace' | 'preserveState' | 'preserveScroll' | 'only'
> & {
    marriageId: number;
};

/**
 * Link that opens the panel of a marriage beside the current page. Keeps a real
 * `href` so middle-click and "open in new tab" still work. While a section of
 * the open record is dirty, a plain click is handed to the guard bar.
 */
export function MarriagePanelLink({
    marriageId,
    onClick,
    children,
    ...props
}: Props) {
    const { hrefFor, open } = useMarriagePanel();
    const editor = useOptionalSectionEditor();

    const handleClick = (event: React.MouseEvent) => {
        onClick?.(event);

        if (
            editor !== null &&
            editor.isDirty &&
            isPlainClick(event) &&
            !event.defaultPrevented
        ) {
            event.preventDefault();
            editor.requestExit(() => open(marriageId));
        }
    };

    return (
        <Link
            {...props}
            {...panelVisitOptions}
            href={hrefFor(marriageId)}
            onClick={handleClick}
        >
            {children}
        </Link>
    );
}
