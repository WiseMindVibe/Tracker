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
        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-center text-sm text-muted-foreground sm:text-left">
                {total > 0 ? `Showing ${from}–${to} of ${total}` : 'No results'}
            </p>
            <div className="flex flex-wrap justify-center gap-1 sm:justify-end">
                {links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveState
                            preserveScroll
                            className={`inline-flex min-h-9 min-w-9 items-center justify-center rounded-md px-2.5 py-1.5 text-sm font-medium sm:px-3 ${
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-background text-foreground ring-1 ring-inset ring-border hover:bg-accent'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="inline-flex min-h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-md px-2.5 py-1.5 text-sm font-medium text-muted-foreground opacity-50 sm:px-3"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </div>
    );
}