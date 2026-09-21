import { ReactNode } from 'react';

interface ResourceHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
}

export default function ResourceHeader({
    title,
    description,
    actions,
}: ResourceHeaderProps) {
    return (
        <div className="mb-7 flex items-start justify-between gap-6">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                    {title}
                </h1>

                {description && (
                    <p className="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
