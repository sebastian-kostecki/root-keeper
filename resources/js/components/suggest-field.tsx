import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent, FocusEvent, KeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';
import { canOfferCreate, filterSuggestions } from '@/lib/suggestions';
import { cn } from '@/lib/utils';

/** Most suggestions rendered at once; the list scrolls after about eight. */
const MAX_SUGGESTIONS = 50;

type CommonProps<T> = {
    id: string;
    /** Name of the field in the form. */
    name: string;
    options: T[];
    labelOf: (option: T) => string;
    placeholder?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

type RecordProps<T> = CommonProps<T> & {
    /** Pick an existing record; `options` must carry a numeric `id`. The hidden input `name` holds its id. */
    mode: 'record';
    value: number | null;
    onChange: (id: number | null) => void;
    /** Adds „Dodaj …” as the last item; the caller creates the record and selects it. */
    onCreate?: (query: string) => void;
    createLabel?: (query: string) => string;
    /** `unmatched`: only when no option has exactly that name; `always`: for every non-blank query. */
    offerCreate?: 'unmatched' | 'always';
};

type TextProps<T> = CommonProps<T> & {
    /** Free text in the visible input (which carries `name`); suggestions only fill it in. */
    mode: 'text';
    defaultValue?: string;
};

export type SuggestFieldProps<T> = RecordProps<T> | TextProps<T>;

type Item<T> = { kind: 'option'; option: T } | { kind: 'create' };

function idOf<T>(option: T): number {
    return (option as { id: number }).id;
}

function setNativeValue(input: HTMLInputElement, value: string) {
    // The prototype setter, not the instance one: React's value tracker then still sees the change.
    Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value',
    )?.set?.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

/** Text input with a suggestion list beneath it, in record mode (pick an id) or text mode (fill in free text). */
export function SuggestField<T>(props: SuggestFieldProps<T>) {
    const { id, name, options, labelOf, placeholder, disabled } = props;
    const isRecord = props.mode === 'record';
    const value = props.mode === 'record' ? props.value : null;

    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const hiddenRef = useRef<HTMLInputElement>(null);
    const previousValue = useRef(value);

    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [isEditing, setIsEditing] = useState(false);
    const [query, setQuery] = useState(
        props.mode === 'text' ? (props.defaultValue ?? '') : '',
    );

    const listboxId = `${id}-listbox`;
    const optionId = (index: number) => `${id}-option-${index}`;

    const selected =
        props.mode === 'record' && value !== null
            ? options.find((option) => idOf(option) === value)
            : undefined;
    const selectedLabel = selected === undefined ? '' : labelOf(selected);

    // Record mode shows the typed text while editing and the chosen label otherwise.
    const searchText = props.mode === 'record' && !isEditing ? '' : query;
    const trimmedQuery = searchText.trim();

    const items: Item<T>[] = isOpen
        ? filterSuggestions(options, searchText, labelOf, MAX_SUGGESTIONS).map(
              (option) => ({ kind: 'option', option }),
          )
        : [];

    if (
        isOpen &&
        props.mode === 'record' &&
        props.onCreate !== undefined &&
        (props.offerCreate === 'always'
            ? trimmedQuery !== ''
            : canOfferCreate(options, searchText, labelOf))
    ) {
        items.push({ kind: 'create' });
    }

    const showList = isOpen && items.length > 0;
    const currentActive = activeIndex < items.length ? activeIndex : -1;

    // The Inertia form only notices native events; React-set hidden values send none.
    useEffect(() => {
        if (previousValue.current === value) {
            return;
        }

        previousValue.current = value;
        hiddenRef.current?.dispatchEvent(
            new Event('change', { bubbles: true }),
        );
    }, [value]);

    useEffect(() => {
        if (showList && currentActive >= 0) {
            document
                .getElementById(`${id}-option-${currentActive}`)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [showList, currentActive, id]);

    const close = () => {
        setIsOpen(false);
        setActiveIndex(-1);
    };

    const open = () => {
        if (!isOpen) {
            setIsOpen(true);
            setActiveIndex(isRecord ? 0 : -1);
        }
    };

    const choose = (item: Item<T>) => {
        if (props.mode === 'record') {
            if (item.kind === 'create') {
                props.onCreate?.(trimmedQuery);
            } else {
                props.onChange(idOf(item.option));
            }

            setIsEditing(false);
            setQuery('');
        } else if (item.kind === 'option' && inputRef.current !== null) {
            setNativeValue(inputRef.current, labelOf(item.option));
        }

        close();
    };

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const text = event.target.value;

        setQuery(text);
        setIsEditing(true);
        setIsOpen(true);
        setActiveIndex(isRecord ? 0 : -1);

        if (props.mode === 'record' && text.trim() === '' && value !== null) {
            props.onChange(null);
        }
    };

    const handleKeyDownCapture = (event: KeyboardEvent<HTMLDivElement>) => {
        // The side panel closes itself on an unhandled Escape, with a native listener that runs after this one.
        if (event.key === 'Escape' && isOpen) {
            event.preventDefault();
            close();

            if (props.mode === 'record') {
                setIsEditing(false);
            }
        }
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (!isOpen) {
                open();

                return;
            }

            if (items.length > 0) {
                const step = event.key === 'ArrowDown' ? 1 : -1;

                setActiveIndex(
                    (currentActive + step + items.length) % items.length,
                );
            }
        } else if (event.key === 'Enter' && isOpen) {
            const item = items[currentActive];

            if (item !== undefined) {
                event.preventDefault();
                choose(item);
            } else if (isRecord) {
                event.preventDefault();
            } else {
                close();
            }
        }
    };

    const handleBlur = (event: FocusEvent<HTMLDivElement>) => {
        if (!wrapperRef.current?.contains(event.relatedTarget)) {
            close();
            setIsEditing(false);
        }
    };

    return (
        <div
            ref={wrapperRef}
            className="relative"
            onKeyDownCapture={handleKeyDownCapture}
            onBlur={handleBlur}
        >
            {props.mode === 'record' && (
                <input
                    ref={hiddenRef}
                    type="hidden"
                    name={name}
                    value={value === null ? '' : String(value)}
                />
            )}

            <Input
                ref={inputRef}
                id={id}
                {...(props.mode === 'text'
                    ? { name, defaultValue: props.defaultValue }
                    : { value: isEditing ? query : selectedLabel })}
                type="text"
                role="combobox"
                autoComplete="off"
                aria-expanded={showList}
                aria-controls={showList ? listboxId : undefined}
                aria-autocomplete="list"
                aria-activedescendant={
                    showList && currentActive >= 0
                        ? optionId(currentActive)
                        : undefined
                }
                aria-invalid={props['aria-invalid']}
                placeholder={placeholder}
                disabled={disabled}
                onChange={handleChange}
                onClick={open}
                onKeyDown={handleKeyDown}
            />

            {showList && (
                <ul
                    id={listboxId}
                    role="listbox"
                    className="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border p-1 shadow-md"
                    // Keeps focus in the input, so picking an item does not blur it first.
                    onMouseDown={(event) => event.preventDefault()}
                >
                    {items.map((item, index) => (
                        <li
                            key={
                                item.kind === 'create'
                                    ? 'create'
                                    : `option-${index}`
                            }
                            id={optionId(index)}
                            role="option"
                            aria-selected={index === currentActive}
                            className={cn(
                                'cursor-pointer rounded-sm px-2 py-1.5 text-sm',
                                index === currentActive &&
                                    'bg-accent text-accent-foreground',
                                item.kind === 'create' && 'font-medium',
                            )}
                            onMouseMove={() => setActiveIndex(index)}
                            onClick={() => choose(item)}
                        >
                            {item.kind === 'create'
                                ? (props.mode === 'record' &&
                                      props.createLabel?.(trimmedQuery)) ||
                                  `Dodaj „${trimmedQuery}”`
                                : labelOf(item.option)}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
