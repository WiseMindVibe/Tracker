import { useState } from 'react';

interface Field {
    field: string;
    label: string;
    type: string;
    placeholder?: string;
}

interface Props {
    relation: string;
    label: string;
    fields: Field[];
    min: number;
    value: Record<string, string>[];
    onChange: (rows: Record<string, string>[]) => void;
    errors: Record<string, string>;
}

export default function RepeaterField({ relation, label, fields, min, value, onChange, errors }: Props) {
    function addRow() {
        const blank = Object.fromEntries(fields.map((f) => [f.field, ""]));
        onChange([...value, blank]);
    }

    function removeRow(index: number) {
        onChange(value.filter((_, i) => i !== index));
    }

    function updateRow(index: number, field: string, val: string) {
        onChange(value.map((row, i) => (i === index ? { ...row, [field]: val } : row)));
    }

    return (
        <div>
            <label className="block text-sm font-medium text-muted-foreground mb-1">
                {label}
                {min > 0 && (<span className="text-destructive">*</span>)}
            </label>

            <div className="space-y-2">
                {value.map((row, index) => (
                    <div key={index} className="flex items-start gap-2">
                        {fields.map((field) => (
                            <div key={field.field} className="flex-1">
                                <label className="block text-xs font-medium text-muted-foreground mb-1">
                                    {field.label}
                                </label>
                                <input
                                    type="text"
                                    placeholder={field.placeholder}
                                    value={row[field.field] ?? ""}
                                    onChange={(e) => updateRow(index, field.field, e.target.value)}
                                    className="w-full rounded-md border border-border px-3 py-1.5 text-sm placeholder:text-muted-foreground"
                                />
                            </div>
                        ))}
                        {value.length > min && (
                            <button
                                type="button"
                                onClick={() => removeRow(index)}
                                className="text-sm text-red-500 px-2 cursor-pointer"
                            >
                                Remove
                            </button>
                        )}
                        {errors[`${relation}.${index}.${fields[0]?.field}`] && (
                            <p className="text-sm text-destructive">
                                {errors[`${relation}.${index}.${fields[0]?.field}`]}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            <button
                type="button"
                onClick={addRow}
                className="mt-2 text-sm text-primary cursor-pointer hover:underline"
            >
                + Add {label}
            </button>

            {errors[relation] && (
                <p className="mt-1 text-sm text-destructive">{errors[relation]}</p>
            )}
        </div>
    );
}