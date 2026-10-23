// resources/js/components/date-range/range-calendar.tsx
import { useMemo, useState } from "react";
import QuickOptions from "./quick-options";
import {
    addDays,
    addMonths,
    CUSTOM_PRESET,
    defaultQuickOptions,
    findPreset,
    formatRange,
    getToday,
    isSameDay,
    startOfMonth,
    startOfWeek,
    type ChangeMeta,
    type DateRangeValue,
    type QuickOption,
    type QuickOptionGroup,
    type WeekStart,
} from "@/lib/date-range";

interface Props {
    value: DateRangeValue;
    onChange: (value: DateRangeValue, meta: ChangeMeta) => void;
    /** App timezone, used to decide what "today" is. */
    timezone?: string;
    weekStartsOn?: WeekStart;
    /** Pass your own groups, or `false` to hide the quick options entirely. */
    quickOptions?: QuickOptionGroup[] | false;
    className?: string;
}

export default function RangeCalendar({
    value,
    onChange,
    timezone,
    weekStartsOn = 1,
    quickOptions = defaultQuickOptions,
    className = "",
}: Props) {
    const today = useMemo(() => getToday(timezone), [timezone]);
    // Left month; the right one is always the next month.
    const [viewMonth, setViewMonth] = useState(() => addMonths(value.end ?? today, -1));
    const [hover, setHover] = useState<Date | null>(null);

    const includeToday = !!value.includeToday;
    const picking = !!value.start && !value.end; // waiting for the 2nd click

    function handleDayClick(day: Date) {
        if (!picking) {
            // 1st click: start
            onChange({ start: day, end: null, preset: CUSTOM_PRESET, includeToday }, { source: "day" });
            return;
        }
        // 2nd click: end (swap if clicked before the start)
        const start = value.start!;
        const [a, b] = day < start ? [day, start] : [start, day];
        onChange({ start: a, end: b, preset: CUSTOM_PRESET, includeToday }, { source: "day" });
        setHover(null);
    }

    function handleQuickOption(option: QuickOption) {
        const { start, end } = option.getRange({ today, weekStartsOn, includeToday, current: value });
        onChange({ start, end, preset: option.id, includeToday }, { source: "preset" });
        if (end) setViewMonth(addMonths(end, -1));
    }

    function handleIncludeToday(checked: boolean) {
        // Re-run the active preset with the new setting so the dates update immediately.
        const option = quickOptions ? findPreset(quickOptions, value.preset)?.option : null;
        const range = option
            ? option.getRange({ today, weekStartsOn, includeToday: checked, current: value })
            : { start: value.start, end: value.end };
        onChange({ ...range, preset: value.preset, includeToday: checked }, { source: "toggle" });
        if (option && range.end) setViewMonth(addMonths(range.end, -1));
    }

    // While picking, preview the range up to the hovered day.
    const previewEnd = picking ? hover : value.end;
    let lo = value.start;
    let hi = previewEnd ?? value.start;
    if (lo && hi && hi < lo) [lo, hi] = [hi, lo];

    return (
        <div
            className={`inline-flex flex-col gap-4 rounded-lg border border-neutral-200 bg-white p-4 text-neutral-900 lg:flex-row dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 ${className}`}
        >
            {quickOptions && (
                <QuickOptions
                    groups={quickOptions}
                    activeId={value.preset}
                    onSelect={handleQuickOption}
                    includeToday={includeToday}
                    onIncludeTodayChange={handleIncludeToday}
                    className="lg:w-52 lg:border-r lg:border-neutral-200 lg:pr-4 dark:lg:border-neutral-700"
                />
            )}

            <div>
                <div className="flex flex-col gap-6 lg:flex-row">
                    {[viewMonth, addMonths(viewMonth, 1)].map((month, i) => (
                        <Month
                            key={`${month.getFullYear()}-${month.getMonth()}`}
                            month={month}
                            weekStartsOn={weekStartsOn}
                            today={today}
                            start={value.start}
                            end={value.end}
                            lo={lo}
                            hi={hi}
                            onPrev={i === 0 ? () => setViewMonth(addMonths(viewMonth, -1)) : undefined}
                            onNext={i === 1 ? () => setViewMonth(addMonths(viewMonth, 1)) : undefined}
                            onNextMobile={i === 0 ? () => setViewMonth(addMonths(viewMonth, 1)) : undefined}
                            onDayClick={handleDayClick}
                            onDayHover={setHover}
                            className={i === 1 ? "hidden lg:block" : undefined}
                        />
                    ))}
                </div>
                <div className="mt-3 border-t border-neutral-200 pt-3 text-sm text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
                    {formatRange(value)}
                </div>
            </div>
        </div>
    );
}

/* ---------- single month grid ---------- */

interface MonthProps {
    month: Date;
    weekStartsOn: WeekStart;
    today: Date;
    start: Date | null;
    end: Date | null;
    lo: Date | null;
    hi: Date | null;
    onPrev?: () => void;
    onNext?: () => void;
    onNextMobile?: () => void;
    className?: string;
    onDayClick: (d: Date) => void;
    onDayHover: (d: Date | null) => void;
}

function Month({ month, weekStartsOn, today, start, end, lo, hi, onPrev, onNext, onNextMobile, className, onDayClick, onDayHover }: MonthProps) {
    const first = startOfMonth(month);
    const lead = (first.getDay() - weekStartsOn + 7) % 7;
    const daysInMonth = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
    const cells: (Date | null)[] = [
        ...Array<null>(lead).fill(null),
        ...Array.from({ length: daysInMonth }, (_, i) => addDays(first, i)),
    ];

    const weekStart = startOfWeek(today, weekStartsOn);
    const weekdayLabels = Array.from({ length: 7 }, (_, i) =>
        new Intl.DateTimeFormat("en-GB", { weekday: "short" }).format(addDays(weekStart, i)).slice(0, 2),
    );
    const title = new Intl.DateTimeFormat("en-GB", { month: "long", year: "numeric" }).format(month);

    const navBtn =
        "flex h-7 w-7 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800";

    return (
        <div className={`w-64 lg:w-56 ${className ?? ""}`} onMouseLeave={() => onDayHover(null)}>
            <div className="mb-2 flex h-7 items-center justify-between">
                {onPrev ? (
                    <button type="button" onClick={onPrev} aria-label="Previous month" className={navBtn}>
                        ‹
                    </button>
                ) : (
                    <span className="w-7" />
                )}
                <span className="text-sm font-medium">{title}</span>
                {onNextMobile && (
                    <button
                        type="button"
                        onClick={onNextMobile}
                        aria-label="Next month"
                        className={`${navBtn} lg:hidden`}
                    >
                        ›
                    </button>
                )}
                {onNext ? (
                    <button type="button" onClick={onNext} aria-label="Next month" className={`${navBtn} hidden lg:flex`}>
                        ›
                    </button>
                ) : !onNextMobile ? (
                    <span className="w-7" />
                ) : null}
            </div>

            <div className="grid grid-cols-7 text-center text-xs text-neutral-500 dark:text-neutral-400">
                {weekdayLabels.map((w, i) => (
                    <div key={i} className="py-1">
                        {w}
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-7">
                {cells.map((day, i) => {
                    if (!day) return <div key={i} />;
                    const isEdge = isSameDay(day, start) || isSameDay(day, end);
                    const inRange = !!lo && !!hi && day >= lo && day <= hi;
                    const isToday = isSameDay(day, today);
                    return (
                        <div key={i} className={inRange ? "bg-blue-100 dark:bg-blue-950" : ""}>
                            <button
                                type="button"
                                onClick={() => onDayClick(day)}
                                onMouseEnter={() => onDayHover(day)}
                                aria-pressed={isEdge}
                                className={
                                    "h-8 w-full rounded-md text-sm focus-visible:outline-2 focus-visible:outline-blue-500 " +
                                    (isEdge
                                        ? "bg-blue-600 font-medium text-white"
                                        : "hover:bg-neutral-200 dark:hover:bg-neutral-700") +
                                    (isToday && !isEdge ? " font-semibold underline underline-offset-4" : "")
                                }
                            >
                                {day.getDate()}
                            </button>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}