import { useEffect, useMemo, useState } from 'react';

interface Props {
    field: string;
    options: Record<string, string>;
    value: string;
    placeholder: string;
    onChange: (value: string) => void;
}

export default function SearchableSelect({
    field,
    options,
    value,
    placeholder,
    onChange,
}: Props) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(0);

    const selectedLabel = useMemo(() => {
        return options[value] ?? '';
    }, [options, value]);

    useEffect(() => {
        setSearch(selectedLabel);
    }, [selectedLabel]);

    const filteredOptions = useMemo(() => {
        return Object.entries(options).filter(([, label]) =>
            label.toLowerCase().includes(search.toLowerCase())
        );
    }, [options, search]);

    function selectOption(optionValue: string, label: string) {
        onChange(optionValue);
        setSearch(label);
        setIsOpen(false);
        setHighlightedIndex(0);
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
            if (e.key === 'Tab') {
                setIsOpen(false);
                setHighlightedIndex(0);
                return;
            }

        if (e.key === 'Escape') {
            e.preventDefault();
            setIsOpen(false);
            setHighlightedIndex(0);
            e.currentTarget.blur();
            return;
        }

        if (!isOpen) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                setIsOpen(true);
                setHighlightedIndex(0);
                return;
            }
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();

            setHighlightedIndex((current) =>
                filteredOptions.length === 0
                    ? 0
                    : Math.min(current + 1, filteredOptions.length - 1)
            );
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();

            setHighlightedIndex((current) =>
                filteredOptions.length === 0
                    ? 0
                    : Math.max(current - 1, 0)
            );
        }

        if (e.key === 'Enter') {
            e.preventDefault();

            const selected = filteredOptions[highlightedIndex];

            if (selected) {
                selectOption(selected[0], selected[1]);
            }
        }
    }

    return (
        <div className="relative">
            <input
                id={field}
                type="text"
                value={search}
                placeholder={placeholder}
                onFocus={() => {
                    setIsOpen(true);
                    setHighlightedIndex(0);
                }}
                onBlur={() => {
                    setIsOpen(false);
                    setHighlightedIndex(0);
                }}
                onChange={(e) => {
                    setSearch(e.target.value);
                    setIsOpen(true);
                    setHighlightedIndex(0);
                }}
                onKeyDown={handleKeyDown}
                autoComplete="off"
                className="w-full rounded-md border border-border px-3 py-1.5 text-sm"
            />

            {isOpen && (
                <div className="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-border bg-background shadow-md">
                    {filteredOptions.length > 0 ? (
                        filteredOptions.map(([optionValue, label], index) => (
                            <button
                                type="button"
                                key={optionValue}
                                onMouseDown={(e) => {
                                    // Prevent input blur before selection
                                    e.preventDefault();
                                    selectOption(optionValue, label);
                                }}
                                className={`block w-full px-3 py-2 text-left text-sm ${
                                    index === highlightedIndex
                                        ? 'bg-muted'
                                        : 'hover:bg-muted'
                                }`}
                            >
                                {label}
                            </button>
                        ))
                    ) : (
                        <div className="px-3 py-2 text-sm text-muted-foreground">
                            No results found
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}