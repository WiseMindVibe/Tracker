import { ReactNode } from 'react';

interface ResourcePageProps {
    children: ReactNode;
}

export default function ResourcePage({ children }: ResourcePageProps) {
    return (
        <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950">
            <div className="mx-auto w-full max-w-[1600px] px-6 py-8 lg:px-8">
                {children}
            </div>
        </div>
    );
}
