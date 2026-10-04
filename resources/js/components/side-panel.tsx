import { XIcon } from 'lucide-react';
import {
    createContext,
    useContext,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type SidePanelSlotContextValue = {
    host: HTMLElement | null;
    setHost: (host: HTMLElement | null) => void;
};

const SidePanelSlotContext = createContext<SidePanelSlotContextValue | null>(
    null,
);

export function SidePanelSlotProvider({ children }: { children: ReactNode }) {
    const [host, setHost] = useState<HTMLElement | null>(null);

    return (
        <SidePanelSlotContext.Provider value={{ host, setHost }}>
            {children}
        </SidePanelSlotContext.Provider>
    );
}

function useSidePanelSlot(): SidePanelSlotContextValue {
    const context = useContext(SidePanelSlotContext);

    if (context === null) {
        throw new Error('SidePanel must be used inside SidePanelSlotProvider');
    }

    return context;
}

/**
 * Layout slot the panel is portalled into. `display: contents` makes the panel
 * a direct child of the shell's flex row, and an empty slot takes no space.
 */
export function SidePanelSlot() {
    const { setHost } = useSidePanelSlot();

    return <div className="contents" ref={setHost} />;
}

type SidePanelProps = {
    title: string;
    description?: string;
    variant?: 'standard' | 'wide';
    /** Changes when the record shown in the panel is replaced. */
    contentKey?: string | number;
    onRequestClose: () => void;
    /** Buttons rendered in the header, next to the close button. */
    actions?: ReactNode;
    /** Pinned bottom area, e.g. form buttons. */
    footer?: ReactNode;
    children: ReactNode;
};

/**
 * Shared right-hand side panel shell. Not modal: the tree beside it stays
 * usable. Sticky card beside the content from `md`, full screen below it.
 */
export function SidePanel(props: SidePanelProps) {
    const { host } = useSidePanelSlot();

    if (host === null) {
        return null;
    }

    return createPortal(<SidePanelView {...props} />, host);
}

function SidePanelView({
    title,
    description,
    variant = 'standard',
    contentKey,
    onRequestClose,
    actions,
    footer,
    children,
}: SidePanelProps) {
    const titleId = useId();
    const descriptionId = useId();
    const rootRef = useRef<HTMLElement>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        // StrictMode runs effects twice in dev: remember only on the first run,
        // otherwise the second run would remember the panel heading.
        if (previousFocusRef.current === null) {
            const active = document.activeElement;

            if (active instanceof HTMLElement && active !== document.body) {
                previousFocusRef.current = active;
            }
        }

        return () => {
            const previous = previousFocusRef.current;

            if (previous?.isConnected) {
                previous.focus({ preventScroll: true });
            }
        };
    }, []);

    useEffect(() => {
        headingRef.current?.focus({ preventScroll: true });
    }, [contentKey]);

    useEffect(() => {
        const root = rootRef.current;

        if (root === null) {
            return;
        }

        // Native listener: synthetic events from portalled Radix modals bubble
        // through the React tree, native ones do not.
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !event.defaultPrevented) {
                event.preventDefault();
                onRequestClose();
            }
        };

        root.addEventListener('keydown', handleKeyDown);

        return () => root.removeEventListener('keydown', handleKeyDown);
    }, [onRequestClose]);

    return (
        <section
            ref={rootRef}
            role="dialog"
            aria-modal="false"
            aria-labelledby={titleId}
            aria-describedby={description ? descriptionId : undefined}
            className={cn(
                'bg-background animate-in slide-in-from-right fixed inset-0 z-40 flex w-full flex-col duration-200',
                'md:sticky md:inset-auto md:top-2 md:z-auto md:m-2 md:ml-0 md:h-[calc(100svh-(--spacing(4)))] md:shrink-0 md:self-start md:rounded-xl md:border md:shadow-sm',
                variant === 'wide'
                    ? 'md:w-[clamp(40rem,55vw,72rem)]'
                    : 'md:w-[clamp(24rem,30vw,36rem)]',
            )}
        >
            <header className="flex items-start gap-2 border-b px-4 py-3">
                <div className="min-w-0 flex-1">
                    <h2
                        id={titleId}
                        ref={headingRef}
                        tabIndex={-1}
                        className="truncate text-lg font-semibold outline-hidden"
                    >
                        {title}
                    </h2>
                    {description && (
                        <p
                            id={descriptionId}
                            className="text-muted-foreground text-sm"
                        >
                            {description}
                        </p>
                    )}
                </div>
                {actions}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Zamknij panel"
                    onClick={onRequestClose}
                >
                    <XIcon />
                </Button>
            </header>
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                {children}
            </div>
            {footer && <div className="border-t px-4 py-3">{footer}</div>}
        </section>
    );
}
