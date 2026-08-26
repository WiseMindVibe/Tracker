import ChipListCell from '@/components/ChipListCell';
import ProgressCell from '@/components/progress-cell';
import { Link, Head, router } from '@inertiajs/react';
import { ArrowUp, ArrowDown, ArrowUpDown, Copy } from 'lucide-react';


interface Props {
    module: string;
    titles: Titles;
    columns: Column[];
    actions?: Actions[];
    rows: {
        data: any,
    };
    sort?: {
        column: string;
        direction: "asc" | "desc";
    };
}

interface Titles {
    page: string;
    header: string;
    header_s: string;
}

interface Actions {
    type: "create" | "edit" | "view";
    label?: string;
    href?: string;
}

interface BaseColumn {
    field: string;
    label: string;
    sortable?: boolean;

}
interface TextColumn extends BaseColumn { type: "text" }
interface RelationColumn extends BaseColumn { type: "relation"; relation: string }
interface CountColumn extends BaseColumn { type: "count"; relation: string }
interface ProgressColumn extends BaseColumn { type: "progress"; currentKey: string; capKey: string }
interface ChipListColumn extends BaseColumn { type: "chip_list"; relation: string }

type Column = TextColumn | RelationColumn | CountColumn | ProgressColumn | ChipListColumn ;

export default function Index({
    module,
    titles,
    columns,
    actions,
    rows,
    sort
}: Props){

    const hasCreate = actions?.find((a) => a.type === 'create');
    const hasEdit = actions?.find((a) => a.type === 'edit');
    const hasView = actions?.find((a) => a.type === 'view');

    function resolveRelationValue(row: any, relationPath: string, field: string) {
        const value = relationPath
            .split('.')
            .reduce((acc, key) => acc?.[key], row);
        return value?.[field];
    }

    function handleSort(column: Column){
        if(!column.sortable) return;

        const isCurrent = sort?.column === column.field;
        const direction = isCurrent && sort?.direction === "asc" ? "desc" : "asc";

        router.get(
            window.location.pathname,
            { sort: column.field, direction },
            { preserveState: true, preserveScroll: true, replace: true }

        )
    }

    function copyToClipboard(value: string) {
        navigator.clipboard.writeText(value);
    }

    return ( 
    <div className="ml-5 mt-5">

        <Head title={titles.page}/>

        <div className="flex justify-between items-center pe-20">

            <h1 className="text-2xl font-semibold tracking-tight">
                {titles.header}
            </h1>

            {hasCreate && (
                <Link
                    href={`/m/${module}/create`}
                    className='inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90'
                    >
                    + {hasCreate.label ?? `New ${titles.header_s}`}
                </Link>
            )}
        </div>

        <table className="mt-3 w-full text-sm">
            <thead>
                <tr className="border-b border-border bg-muted/50">    
                    {columns.map((column, index) => {
                        const isCurrent = sort?.column == column.field;
                        return (
                            <th key={`${column.field}-${index}`}
                            onClick={() => handleSort(column)}
                            className={`px-2 py-1.5 text-left font-medium text-muted-foreground
                            ${column.sortable ? "cursor-pointer select-none hover:text-foreground" :
                                ""
                            }`}>

                                <span className='inline-flex items-center gap-1'>
                                    {column.label}
                                    {column.sortable && (
                                        isCurrent ? (
                                            sort.direction === "asc"
                                            ? <ArrowUp className='h-3.5 w-3.5'/>
                                            : <ArrowDown className='h-3.5 w-3.5'/>
                                        ) : (
                                            <ArrowUpDown className='h-3.5 w-3.5 opacity-30' />
                                        )
                                    )}
                                </span>
                            </th>
                        );
                    })}
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                {rows.data.map((row) => (
                    <tr key={row.id}
                    className='border-b border-border transition-colors hover:bg-muted/50'>
                        {columns.map((column, index) => (
                            <td
                                key={`${row.id}-${column.field}-${index}`}
                                className="px-3 py-2"
                            >
                                {column.type === "relation" ? (
                                    resolveRelationValue(
                                        row,
                                        column.relation,
                                        column.field
                                    )

                                ) : column.type === "count" ? (
                                    row[`${column.relation}_count`] ?? 0

                                ) : column.type === "progress" ? (
                                    <ProgressCell row={row} column={column} />
                                ) : column.type === "chip_list" ? (
                                    <ChipListCell row={row} column={column} />
                                ) : 
                                (
                                    row[column.field]
                                )}
                            </td>
                        ))}
                        <td className='text-center space-x-2'>
                            {hasView && (
                                <Link
                                    href={`/m/${module}/${row.id}/view`}
                                    className='rounded-md bg-secondary px-2 py-1 text-sm font-medium text-secondary-foreground hover:bg-secondary/90'
                                >
                                    View
                                </Link>
                            )}
                            {hasEdit && (
                                <Link
                                    href={`/m/${module}/${row.id}/edit`}
                                    className='rounded-md bg-primary px-2 py-1 text-sm font-medium text-primary-foreground hover:bg-primary/90'
                                >
                                    Edit
                                </Link>
                            )}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>

    </div>
    )

    function resolveRelationList(
        row: any,
        relationPath: string,
        field: string
    ) {
        const relation = relationPath
            .split('.')
            .reduce((acc, key) => acc?.[key], row);

        if (!Array.isArray(relation)) {
            return [];
        }

        return relation
            .map((item) => item?.[field])
            .filter((value) => value !== null && value !== undefined);
    }
}