interface Company {
    id: number;
    name: string;
    status: string;
    created_at: string;
    updated_at: string;

    // add whatever other columns your companies table has
}

interface Props {
    companies: Company[];
}

export default function CompaniesIndex({ companies }: Props) {
    return (
        <div className="p-6">
            <h1 className="text-xl font-bold mb-4">Companies</h1>

            <table className="w-full border-collapse">
                <thead>
                    <tr>
                        <th className="text-left border-b p-2">ID</th>
                        <th className="text-left border-b p-2">Company Name</th>
                        <th className="text-left border-b p-2">Status</th>
                        <th className="text-left border-b p-2">Created At</th>
                        <th className="text-left border-b p-2">Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    {companies.map((company) => (
                        <tr key={company.id}>
                            <td className="p-2 border-b">{company.id}</td>
                            <td className="p-2 border-b">{company.name}</td>
                            <td className="p-2 border-b">{company.status}</td>
                            <td className="p-2 border-b">{company.created_at}</td>
                            <td className="p-2 border-b">{company.updated_at}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
