// resources/js/components/date-range/quick-options.tsx
import type { QuickOption, QuickOptionGroup } from "@/lib/date-range";

interface Props {
    groups: QuickOptionGroup[];
    activeId?: string | null;
    onSelect: (option: QuickOption) => void;
    /** State of the "include today" tickbox (shown for groups that define `toggle`). */
    includeToday?: boolean;
    onIncludeTodayChange?: (checked: boolean) => void;
    className?: string;
}

/**
 * Presentational list of preset buttons. It knows nothing about dates:
 * it renders the groups it is given and reports what was clicked.
 */
export default function QuickOptions({
    groups,
    activeId,
    onSelect,
    includeToday = false,
    onIncludeTodayChange,
    className = "",
}: Props) {
    return (
        <div className={`flex flex-col gap-4 ${className}`}>
            {groups.map((group) => (
                <div key={group.id}>
                    {group.label && (
                        <div className="mb-1.5 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                            {group.label}
                        </div>
                    )}
                    <div className="flex flex-wrap gap-1.5">
                        {group.options.map((option) => {
                            const active = option.id === activeId;
                            return (
                                <button
                                    key={option.id}
                                    type="button"
                                    onClick={() => onSelect(option)}
                                    aria-pressed={active}
                                    className={
                                        "rounded-md border px-2.5 py-1 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue-500 " +
                                        (active
                                            ? "border-blue-600 bg-blue-600 text-white"
                                            : "border-neutral-200 bg-white text-neutral-800 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800")
                                    }
                                >
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                    {group.toggle && (
                        <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                            <input
                                type="checkbox"
                                checked={includeToday}
                                onChange={(e) => onIncludeTodayChange?.(e.target.checked)}
                                className="h-4 w-4 rounded border-neutral-300 accent-blue-600"
                            />
                            {group.toggle.label}
                        </label>
                    )}
                </div>
            ))}
        </div>
    );
}