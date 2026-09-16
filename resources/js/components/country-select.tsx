import { useEffect, useMemo, useState } from 'react';

interface CountryOption {
    value: string;   // e.g. "AF"
    label: string;   // e.g. "Afghanistan"
    aliases?: string[];
}

interface Props {
    field: string;
    options: CountryOption[];
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}

function scoreCountry(search: string, country: CountryOption): number {
    const q = search.trim().toLowerCase();
    if (!q) return 0;

    const code = country.value.toLowerCase();
    const name = country.label.toLowerCase();
    const aliases = (country.aliases ?? []).map((a) => a.toLowerCase());

    if (code === q) return 100;
    if (aliases.includes(q)) return 90;
    if (code.startsWith(q)) return 80;
    if (name.startsWith(q)) return 70;
    if (aliases.some((a) => a.startsWith(q))) return 60;
    if (name.includes(q)) return 40;
    if (aliases.some((a) => a.includes(q))) return 30;

    return -1;
}

export default function CountrySelect({
    field,
    options,
    value,
    onChange,
    disabled = false,
}: Props) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(0);

    const selectedLabel = useMemo(() => {
        return options.find((o) => o.value === value)?.label ?? '';
    }, [options, value]);

    useEffect(() => {
        setSearch(selectedLabel);
    }, [selectedLabel]);

    const filteredOptions = useMemo(() => {
        if (!search) return options.slice(0, 20);
        return options
            .map((o) => ({ option: o, score: scoreCountry(search, o) }))
            .filter((r) => r.score >= 0)
            .sort((a, b) => b.score - a.score)
            .slice(0, 20)
            .map((r) => r.option);
    }, [search, options]);

    function selectOption(option: CountryOption) {
        onChange(option.value);
        setSearch(option.label);
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
                selectOption(selected);
            }
        }
    }

    return (
        <div className="relative">
            <input
                id={field}
                disabled={disabled}
                type="text"
                value={search}
                placeholder='Select A Country'
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
                        filteredOptions.map((option, index) => (
                            <button
                                type="button"
                                key={option.value}
                                onMouseDown={(e) => {
                                    // Prevent input blur before selection
                                    e.preventDefault();
                                    selectOption(option);
                                }}
                                className={`block w-full px-3 py-2 text-left text-sm ${
                                    index === highlightedIndex
                                        ? 'bg-muted'
                                        : 'hover:bg-muted'
                                }`}
                            >
                                {option.label}{' '}
                                <span className="text-muted-foreground">({option.value})</span>
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