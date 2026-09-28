import { Link } from '@inertiajs/react';
import type * as React from 'react';
import {
    previewVisitOptions,
    usePersonPreview,
} from '@/components/persons/person-preview-provider';

type Props = Omit<
    React.ComponentProps<typeof Link>,
    'href' | 'replace' | 'preserveState' | 'preserveScroll' | 'only'
> & {
    personId: number;
};

/**
 * Link that opens the preview modal of a person over the current page. Keeps a
 * real `href` so middle-click and "open in new tab" still work.
 */
export function PersonPreviewLink({
    personId,
    onClick,
    children,
    ...props
}: Props) {
    const { hrefFor, pushCurrent } = usePersonPreview();

    const handleClick = (event: React.MouseEvent) => {
        onClick?.(event);

        const isPlainClick =
            event.button === 0 &&
            !event.metaKey &&
            !event.ctrlKey &&
            !event.shiftKey &&
            !event.altKey;

        if (isPlainClick && !event.defaultPrevented) {
            pushCurrent();
        }
    };

    return (
        <Link
            {...props}
            {...previewVisitOptions}
            href={hrefFor(personId)}
            onClick={handleClick}
        >
            {children}
        </Link>
    );
}
