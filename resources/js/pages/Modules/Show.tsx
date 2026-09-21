interface Column {
    field: string;
    label: string;
    relation?: string;
}

interface Props {
    title: string;
    columns: Column[];
    rows: { data: Record<string, any>[] };
}

function getNestedValue(object: Record<string, any>, path: string): unknown {
    return path
        .split('.')
        .reduce((value: any, key: string) => value?.[key], object);
}

export default function Show({ title, columns, rows }: Props) {
    return (
        <div>
            <h1>{title}</h1>

            <table>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.label}
                                className="px-2"
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>

                <tbody>
                    {rows.data.map((row) => (
                        <tr key={row.id}>
                            {columns.map((column) => (
                                <td
                                    key={`${row.id}-${column.relation ?? 'self'}-${column.field}`}
                                    className="px-2"
                                >
                                    {column.relation ? getNestedValue(
                                        row, `${column.relation}.${column.field}`
                                    ) : row[column.field]
                                    }
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
