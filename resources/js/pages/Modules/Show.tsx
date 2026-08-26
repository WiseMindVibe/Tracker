function getNestedValue(object, path) {
    return path
        .split('.')
        .reduce((value, key) => value?.[key], object);
}

export default function Show({ title, columns, rows }) {
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
