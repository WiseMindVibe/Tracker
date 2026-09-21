interface Company{
    id: number;
    name: string;
}

interface Blog {
    id: number;
    company: Company;
    domain: string;
    main_geo: string;
    status: string;
    created_at: string;
    updated_at: string;

    // add whatever other columns your companies table has
}

interface Props {
    blogs: Blog[];
}

export default function BlogsIndex({ blogs }: Props) {
    return (
        <div className="p-6">
            <h1 className="text-xl font-bold mb-4">Blogs</h1>

            <table className="w-full border-collapse">
                <thead>
                    <tr>
                        <th className="text-left border-b p-2">ID</th>
                        <th className="text-left border-b p-2">Owned Company</th>
                        <th className="text-left border-b p-2">Blog Domain</th>
                        <th className="text-left border-b p-2">Main GEO</th>
                        <th className="text-left border-b p-2">Status</th>
                        <th className="text-left border-b p-2">Created At</th>
                        <th className="text-left border-b p-2">Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    {blogs.map((blog) => (
                        <tr key={blog.id}>
                            <td className="p-2 border-b">{blog.id}</td>
                            <td className="p-2 border-b">{blog.company.name}</td>
                            <td className="p-2 border-b">{blog.domain}</td>
                            <td className="p-2 border-b">{blog.main_geo}</td>
                            <td className="p-2 border-b">{blog.status}</td>
                            <td className="p-2 border-b">{blog.created_at}</td>
                            <td className="p-2 border-b">{blog.updated_at}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
