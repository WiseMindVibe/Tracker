import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

type Props = {
    initialValue: string;
    placeholder?: string;
};

export default function SearchInput({ initialValue, placeholder }: Props) {
    const [value, setValue] = useState(initialValue);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (value === initialValue) return;

            router.get(
                window.location.pathname,
                { search: value || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return (
        <input
            type="text"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder={placeholder ?? 'Search...'}
            className="w-full max-w-sm rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
        />
    );
}