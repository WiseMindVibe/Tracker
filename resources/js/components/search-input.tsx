import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Props = {
    initialValue: string;
    placeholder?: string;
};

export default function SearchInput({ initialValue, placeholder }: Props) {
    const [value, setValue] = useState(initialValue);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (value === initialValue) {
                return;
            }

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
            className="w-full max-w-sm rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/40"
        />
    );
}