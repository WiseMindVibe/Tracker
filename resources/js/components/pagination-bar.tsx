import { Link } from '@inertiajs/react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function PaginationBar({ links, from, to, total }: Props) {
    return (
        <div className="mt-4 flex items-center justify-between">
            <p className="text-sm text-slate-500 dark:text-slate-400">
                {total > 0 ? `Showing ${from}–${to} of ${total}` : 'No results'}
            </p>
            <div className="flex items-center gap-1">
                {links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveState
                            preserveScroll
                            className={`rounded-md px-3 py-1.5 text-sm font-medium ${
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="cursor-not-allowed rounded-md px-3 py-1.5 text-sm font-medium text-slate-300 dark:text-slate-700"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </div>
    );
}