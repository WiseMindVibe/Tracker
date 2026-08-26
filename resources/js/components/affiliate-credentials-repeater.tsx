import { useEffect } from 'react';

interface CredentialRow {
    id?: number;
    label: string;
    key: string;
    value: string;
}

interface Definition {
    label: string;
    key: string;
}

interface Props {
    catalogId: string;
    definitions: Record<string, Definition[]>;
    value: CredentialRow[];
    onChange: (rows: CredentialRow[]) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
}

export default function AffiliateCredentialsRepeater({
    catalogId,
    definitions,
    value,
    onChange,
    errors = {},
    disabled = false,
}: Props) {
    const defs = definitions[catalogId] ?? [];

    useEffect(() => {
        if (!catalogId) {
            if (value.length > 0) onChange([]);
            return;
        }

        const existingByKey = Object.fromEntries(value.map((row) => [row.key, row]));

        const rebuilt: CredentialRow[] = defs.map((def) => ({
            id: existingByKey[def.key]?.id,
            label: def.label,
            key: def.key,
            value: existingByKey[def.key]?.value ?? "",
        }));

        const changed =
            rebuilt.length !== value.length ||
            rebuilt.some((row, i) => row.key !== value[i]?.key);

        if (changed) {
            onChange(rebuilt);
        }
    }, [catalogId, defs.length]);

    function updateValue(index: number, newValue: string) {
        onChange(value.map((row, i) => (i === index ? { ...row, value: newValue } : row)));
    }

    if (!catalogId) {
        return (
            <p className="text-sm text-muted-foreground mt-4">
                Select an Affiliate Network to configure credentials.
            </p>
        );
    }

    if (defs.length === 0) {
        return (
            <p className="text-sm text-muted-foreground mt-4">
                No credential fields defined for this network.
            </p>
        );
    }

    return (
        <div className="mt-6 space-y-3">
            <label className="block text-sm font-medium text-muted-foreground mb-1">
                Affiliate Credentials
            </label>

            {value.map((row, index) => (
                <div key={row.key}>
                    <label className="block text-xs font-medium text-muted-foreground mb-1">
                        {row.label}
                    </label>
                    <input
                        type="text"
                        value={row.value}
                        disabled={disabled}
                        onChange={(e) => updateValue(index, e.target.value)}
                        className="w-full rounded-md border border-border px-3 py-1.5 text-sm"
                    />
                    {errors[`affiliateCredentials.${index}.value`] && (
                        <p className="mt-1 text-xs text-destructive">
                            {errors[`affiliateCredentials.${index}.value`]}
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
}