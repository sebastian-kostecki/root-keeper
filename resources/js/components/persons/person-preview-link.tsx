import { Link } from '@inertiajs/react';
import type * as React from 'react';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import {
    isPlainClick,
    useOptionalSectionEditor,
} from '@/components/persons/section-editor';
import { panelVisitOptions } from '@/lib/side-panel-url';

type Props = Omit<
    React.ComponentProps<typeof Link>,
    'href' | 'replace' | 'preserveState' | 'preserveScroll' | 'only'
> & {
    personId: number;
};

/**
 * Link that opens the panel of a person beside the current page. Keeps a real
 * `href` so middle-click and "open in new tab" still work. While a section of
 * the open person is dirty, a plain click is handed to the guard bar.
 */
export function PersonPreviewLink({
    personId,
    onClick,
    children,
    ...props
}: Props) {
    const { hrefFor, open } = usePersonPreview();
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
            editor.requestExit(() => open(personId));
        }
    };

    return (
        <Link
            {...props}
            {...panelVisitOptions}
            href={hrefFor(personId)}
            onClick={handleClick}
        >
            {children}
        </Link>
    );
}
