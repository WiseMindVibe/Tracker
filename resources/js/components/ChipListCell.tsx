import { useState } from 'react';
import { Check, Copy } from 'lucide-react';

interface Props {
    row: Record<string, any>;
    column: { relation: string; field: string };
}

export default function ChipListCell({ row, column }: Props) {
    const items: Record<string, any>[] = row[column.relation] ?? [];
    const [copiedId, setCopiedId] = useState<number | null>(null);

    function copy(value: string, id: number) {
        navigator.clipboard.writeText(value);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 1200);
    }

    if (items.length === 0) {
        return <span className="text-muted-foreground text-sm">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {items.map((item) => (
                <button
                    key={item.id}
                    type="button"
                    onClick={() => copy(item[column.field], item.id)}
                    className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs hover:bg-muted/70"
                    title="Click to copy"
                >
                    {item[column.field]}
                    {copiedId === item.id ? (
                        <Check className="h-3 w-3 text-green-600" />
                    ) : (
                        <Copy className="h-3 w-3 text-muted-foreground" />
                    )}
                </button>
            ))}
        </div>
    );
}