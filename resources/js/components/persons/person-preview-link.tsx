import { Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import type * as React from 'react';
import {
    previewVisitOptions,
    usePersonPreview,
} from '@/components/persons/person-preview-provider';
import {
    isPlainClick,
    useOptionalSectionEditor,
} from '@/components/persons/section-editor';

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
    const { currentId, hrefFor, push } = usePersonPreview();
    const fromId = useRef<number | null>(null);

    const editor = useOptionalSectionEditor();
    const href = hrefFor(personId);

    const handleClick = (event: React.MouseEvent) => {
        onClick?.(event);

        const isIntercepted = isPlainClick(event) && !event.defaultPrevented;
        const from = isIntercepted && currentId !== personId ? currentId : null;

        fromId.current = from;

        if (isIntercepted && editor !== null && editor.isDirty) {
            event.preventDefault();
            fromId.current = null;
            editor.requestExit(() =>
                router.visit(href, {
                    ...previewVisitOptions,
                    onSuccess: () => {
                        if (from !== null) {
                            push(from);
                        }
                    },
                }),
            );
        }
    };

    return (
        <Link
            {...props}
            {...previewVisitOptions}
            href={href}
            onClick={handleClick}
            onSuccess={() => {
                if (fromId.current !== null) {
                    push(fromId.current);
                    fromId.current = null;
                }
            }}
        >
            {children}
        </Link>
    );
}
