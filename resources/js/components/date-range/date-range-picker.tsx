// resources/js/components/date-range/date-range-picker.tsx
import { useEffect, useRef, useState, type ComponentProps } from "react";
import RangeCalendar from "./range-calendar";
import { ALL_TIME_PRESET, CUSTOM_PRESET, defaultQuickOptions, formatRange, getPresetLabel, type ChangeMeta, type DateRangeValue } from "@/lib/date-range";

type CalendarProps = ComponentProps<typeof RangeCalendar>;

interface Props extends Omit<CalendarProps, "className"> {
    /** Which edge of the button the popover lines up with. */
    align?: "left" | "right";
    /** Close the popover once a full range (or a quick option) is chosen. */
    closeOnSelect?: boolean;
    className?: string;
}

/**
 * Collapsed by default: shows the selected range as a button.
 * Click it to open the two-month calendar + quick options.
 */
export default function DateRangePicker({
    value,
    onChange,
    align = "right",
    closeOnSelect = true,
    className = "",
    quickOptions = defaultQuickOptions,
    ...calendarProps
}: Props) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);

    // Close on outside click or Escape.
    useEffect(() => {
        if (!open) return;
        const onMouseDown = (e: MouseEvent) => {
            if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
        };
        const onKeyDown = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
        document.addEventListener("mousedown", onMouseDown);
        document.addEventListener("keydown", onKeyDown);
        return () => {
            document.removeEventListener("mousedown", onMouseDown);
            document.removeEventListener("keydown", onKeyDown);
        };
    }, [open]);

    function handleChange(next: DateRangeValue, meta: ChangeMeta) {
        onChange(next, meta);
        if (!closeOnSelect) return;
        // Close after the 2nd date click or a real quick option; stay open for the tickbox and "Custom".
        const finished =
            meta.source === "day" ? !!next.end : meta.source === "preset" && next.preset !== CUSTOM_PRESET;
        if (finished) setOpen(false);
    }

    const presetLabel = getPresetLabel(quickOptions || [], value.preset); // "Custom" works even with no quick options
    const showDates = value.preset !== ALL_TIME_PRESET;

    return (
        <div ref={rootRef} className={`relative inline-block ${className}`}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-haspopup="dialog"
                aria-expanded={open}
                className="flex min-h-11 max-w-full flex-wrap items-center gap-2 rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-left text-sm text-neutral-900 hover:bg-neutral-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
            >
                {presetLabel && <span className="font-medium">{presetLabel}</span>}
                {presetLabel && showDates && <span aria-hidden className="text-neutral-300 dark:text-neutral-600">|</span>}
                {showDates && <span className={presetLabel ? "text-neutral-600 dark:text-neutral-300" : ""}>{formatRange(value)}</span>}
                <span aria-hidden className={`text-xs transition-transform ${open ? "rotate-180" : ""}`}>
                    ▾
                </span>
            </button>

            {open && (
                <div
                    role="dialog"
                    aria-label="Select date range"
                    className={`absolute top-full z-50 mt-2 max-h-[calc(100dvh-6rem)] max-w-[calc(100vw-2rem)] overflow-y-auto shadow-lg ${align === "right" ? "left-0 lg:left-auto lg:right-0" : "left-0"}`}
                >
                    <RangeCalendar value={value} onChange={handleChange} quickOptions={quickOptions} {...calendarProps} />
                </div>
            )}
        </div>
    );
}