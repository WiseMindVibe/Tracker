
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';


interface Summary {
    clicks: number;
    conversions: number;
    cr: number;
    cost: number;
    revenue: number;
    profit: number;
    roi: number;
    epc: number;
}

interface TrendPoint {
    date: string;
    clicks: number;
    revenue: number;
}

interface Props {
    summary: Summary;
    trend: TrendPoint[];
    start: string;
    end: string;
}

export default function Dashboard({ summary, trend, start, end }: Props) {
    const [startDate, setStartDate] = useState(start);
    const [endDate, setEndDate] = useState(end);

    const applyRange = () => {
        router.get('/dashboard', { start: startDate, end: endDate }, { preserveState: true });
    };

    const colorClass = (value: number, goodAt: number, okAt: number) =>
        value >= goodAt ? 'text-green-500' : value >= okAt ? 'text-yellow-500' : 'text-red-500';

    const cards = [
        { label: 'Clicks', value: summary.clicks.toLocaleString() },
        { label: 'Conversions', value: summary.conversions.toLocaleString() },
        { label: 'CR', value: `${summary.cr}%`, color: colorClass(summary.cr, 0.3, 0.1) },
        { label: 'Cost', value: `$${summary.cost.toFixed(2)}`, color: 'text-red-500' },
        { label: 'Revenue', value: `$${summary.revenue.toFixed(2)}`, color: 'text-green-500' },
        { label: 'Profit', value: `$${summary.profit.toFixed(2)}`, color: summary.profit >= 0 ? 'text-green-500' : 'text-red-500' },
        { label: 'ROI', value: `${summary.roi}%`, color: colorClass(summary.roi, 300, 100) },
        { label: 'EPC', value: `$${summary.epc.toFixed(4)}`, color: 'text-green-500' },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Dashboard</h1>
                    <div className="flex items-center gap-2">
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="border rounded px-2 py-1"
                        />
                        <span>→</span>
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="border rounded px-2 py-1"
                        />
                        <button
                            onClick={applyRange}
                            className="bg-blue-600 text-white px-3 py-1 rounded"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {cards.map((card) => (
                        <div key={card.label} className="border rounded-lg p-4">
                            <div className="text-sm text-gray-500">{card.label}</div>
                            <div className={`text-xl font-semibold ${card.color ?? ''}`}>
                                {card.value}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="border rounded-lg p-4">
                    <h2 className="font-semibold mb-4">Clicks &amp; Revenue Trend</h2>
                    <ResponsiveContainer width="100%" height={300}>
                        <LineChart data={trend}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="date" />
                            <YAxis yAxisId="left" />
                            <YAxis yAxisId="right" orientation="right" />
                            <Tooltip />
                            <Legend />
                            <Line yAxisId="left" type="monotone" dataKey="clicks" stroke="#4c8dff" />
                            <Line yAxisId="right" type="monotone" dataKey="revenue" stroke="#3fb950" />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </>
    );
}
