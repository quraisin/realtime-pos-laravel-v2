import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function AnalyticsApp({ user }) {
    const [period, setPeriod] = useState('7days');
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState(null);
    const [hoveredBar, setHoveredBar] = useState(null);

    useEffect(() => {
        fetchAnalytics(period);
    }, [period]);

    const fetchAnalytics = async (selectedPeriod) => {
        setLoading(true);
        try {
            const res = await axios.get('/api/analytics/summary', {
                params: { period: selectedPeriod }
            });
            if (res.data.success) {
                setData(res.data);
            }
        } catch (e) {
            console.error('Failed fetching analytics data', e);
        } finally {
            setLoading(false);
        }
    };

    if (loading && !data) {
        return (
            <div className="h-96 flex items-center justify-center text-slate-500 text-sm">
                <svg className="animate-spin h-6 w-6 text-rose-500 mr-2" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Memuat Analitik Transaksi...
            </div>
        );
    }

    const kpis = data?.kpis || {};
    const dailyData = data?.daily_data || [];
    const hourlyData = data?.hourly_data || [];
    const paymentMethods = data?.payment_methods || [];
    const orderTypes = data?.order_types || [];
    const topProducts = data?.top_products || [];

    // Max daily revenue for scaling bars
    const maxDailyRevenue = Math.max(1, ...dailyData.map(d => d.revenue));
    const maxHourlyOrders = Math.max(1, ...hourlyData.map(h => h.orders));

    return (
        <div className="space-y-6">
            {/* Header & Filter Controls */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-xl font-black text-white">Dashboard Analitik Penjualan</h1>
                    <p className="text-xs text-slate-400">Insight omset, tren transaksi, dan performa dapur</p>
                </div>

                {/* Period Selector Pills */}
                <div className="flex rounded-xl bg-slate-900 p-1 border border-slate-800 text-xs font-bold">
                    {[
                        { key: 'today', label: 'Hari Ini' },
                        { key: 'yesterday', label: 'Kemarin' },
                        { key: '7days', label: '7 Hari' },
                        { key: '30days', label: '30 Hari' },
                        { key: 'this_month', label: 'Bulan Ini' },
                    ].map(p => (
                        <button
                            key={p.key}
                            type="button"
                            onClick={() => setPeriod(p.key)}
                            className={`px-3 py-1.5 rounded-lg transition-all ${period === p.key ? 'bg-rose-600 text-white shadow-md' : 'text-slate-400 hover:text-white'}`}>
                            {p.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Top 5 KPI Metrics */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                {/* Total Revenue */}
                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl relative overflow-hidden">
                    <div className="absolute top-0 right-0 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl pointer-events-none"></div>
                    <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                        Total Omset
                    </span>
                    <div className="text-xl font-black font-mono text-white">
                        Rp {Number(kpis.total_revenue || 0).toLocaleString('id-ID')}
                    </div>
                    <span className="text-[10px] text-emerald-400 font-semibold mt-1 block">
                        ✓ Transaksi Berhasil
                    </span>
                </div>

                {/* Total Orders */}
                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl relative overflow-hidden">
                    <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                        Total Transaksi
                    </span>
                    <div className="text-xl font-black font-mono text-white">
                        {kpis.total_orders || 0}
                    </div>
                    <span className="text-[10px] text-slate-400 font-semibold mt-1 block">
                        Order selesai
                    </span>
                </div>

                {/* Average Order Value (AOV) */}
                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl relative overflow-hidden">
                    <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                        Rata-Rata Order (AOV)
                    </span>
                    <div className="text-xl font-black font-mono text-amber-400">
                        Rp {Number(kpis.average_order_value || 0).toLocaleString('id-ID')}
                    </div>
                    <span className="text-[10px] text-slate-400 font-semibold mt-1 block">
                        Per transaksi
                    </span>
                </div>

                {/* Total Items Sold */}
                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl relative overflow-hidden">
                    <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                        Produk Terjual
                    </span>
                    <div className="text-xl font-black font-mono text-white">
                        {kpis.total_items_sold || 0} <span className="text-xs font-normal text-slate-400">porsi</span>
                    </div>
                    <span className="text-[10px] text-slate-400 font-semibold mt-1 block">
                        Makanan & minuman
                    </span>
                </div>

                {/* Kitchen Efficiency */}
                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl relative overflow-hidden">
                    <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                        Waktu Masak Dapur
                    </span>
                    <div className="text-xl font-black font-mono text-emerald-400">
                        {kpis.avg_kitchen_prep_minutes || 0} <span className="text-xs font-normal text-slate-400">menit</span>
                    </div>
                    <span className="text-[10px] text-slate-400 font-semibold mt-1 block">
                        Rata-rata kecepatan
                    </span>
                </div>
            </div>

            {/* CHARTS ROW 1: Daily Revenue Trend & Hourly Peak Traffic */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Daily Revenue Bar Chart (2 cols) */}
                <div className="lg:col-span-2 p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-bold text-white">Tren Pendapatan Harian</h3>
                            <p className="text-[11px] text-slate-400">Fluktuasi omset penjualan harian</p>
                        </div>
                        <span className="text-xs font-bold text-rose-400">
                            Puncak: Rp {maxDailyRevenue.toLocaleString('id-ID')}
                        </span>
                    </div>

                    {/* Interactive CSS / SVG Bar Chart */}
                    <div className="h-64 flex items-end gap-2 pt-8 pb-2 px-2 border-b border-slate-800">
                        {dailyData.map((day, idx) => {
                            const heightPct = Math.max(8, Math.round((day.revenue / maxDailyRevenue) * 100));
                            return (
                                <div
                                    key={idx}
                                    onMouseEnter={() => setHoveredBar(day)}
                                    onMouseLeave={() => setHoveredBar(null)}
                                    className="flex-1 flex flex-col items-center h-full justify-end group cursor-pointer relative">
                                    {/* Tooltip on hover */}
                                    {hoveredBar?.date === day.date && (
                                        <div className="absolute -top-12 z-20 px-2 py-1 rounded bg-slate-950 text-white border border-slate-700 text-[10px] whitespace-nowrap shadow-xl">
                                            <strong>{day.label}:</strong> Rp {Number(day.revenue).toLocaleString('id-ID')} ({day.orders} order)
                                        </div>
                                    )}

                                    {/* Bar Pillar */}
                                    <div
                                        style={{ height: `${heightPct}%` }}
                                        className="w-full max-w-[40px] bg-gradient-to-t from-rose-700 to-orange-500 rounded-t-lg group-hover:from-rose-500 group-hover:to-amber-400 transition-all shadow-md group-hover:shadow-rose-950"></div>

                                    {/* Day Label */}
                                    <span className="text-[10px] font-mono text-slate-400 mt-2 truncate w-full text-center">
                                        {day.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Hourly Sales Traffic (Peak Hours Heatmap) */}
                <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <div>
                        <h3 className="text-sm font-bold text-white">Jam Ramai Transaksi (Peak Hours)</h3>
                        <p className="text-[11px] text-slate-400">Volume pesanan per jam operasional</p>
                    </div>

                    <div className="h-64 overflow-y-auto space-y-2 pr-1">
                        {hourlyData.map((hour, idx) => {
                            const barPct = Math.round((hour.orders / maxHourlyOrders) * 100);
                            return (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-mono text-slate-400 text-[11px]">{hour.hour}</span>
                                        <span className="text-[11px] font-bold text-white font-mono">
                                            {hour.orders} order
                                            {hour.revenue > 0 && (
                                                <span className="text-slate-500 font-normal ml-1">
                                                    (Rp {Number(hour.revenue).toLocaleString('id-ID')})
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                    <div className="w-full h-2 rounded-full bg-slate-950 overflow-hidden">
                                        <div
                                            style={{ width: `${barPct}%` }}
                                            className={`h-full rounded-full transition-all ${hour.orders > 0 ? 'bg-amber-500' : 'bg-transparent'}`}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* CHARTS ROW 2: Best Sellers, Payment Methods & Order Types */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Top 5 Best Selling Products */}
                <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-bold text-white">5 Menu Paling Laris</h3>
                            <p className="text-[11px] text-slate-400">Favorit pelanggan berdasarkan jumlah porsi</p>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {topProducts.length === 0 ? (
                            <p className="text-xs text-slate-500">Belum ada data penjualan produk.</p>
                        ) : (
                            topProducts.map((prod, idx) => (
                                <div key={idx} className="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2.5">
                                        <span className={`w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs ${
                                            idx === 0 ? 'bg-amber-400 text-slate-950' : idx === 1 ? 'bg-slate-300 text-slate-950' : idx === 2 ? 'bg-amber-700 text-white' : 'bg-slate-800 text-slate-400'
                                        }`}>
                                            {idx + 1}
                                        </span>
                                        <div>
                                            <h5 className="text-xs font-bold text-white line-clamp-1">{prod.product_name}</h5>
                                            <span className="text-[10px] text-slate-400">
                                                Rp {Number(prod.total_sales).toLocaleString('id-ID')}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-xs font-extrabold text-rose-400 font-mono">
                                            {prod.total_qty}x
                                        </span>
                                        <span className="text-[10px] text-slate-500 block">terjual</span>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Payment Methods Distribution */}
                <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <div>
                        <h3 className="text-sm font-bold text-white">Distribusi Metode Pembayaran</h3>
                        <p className="text-[11px] text-slate-400">Tunai vs QRIS vs Kartu</p>
                    </div>

                    <div className="space-y-3">
                        {paymentMethods.length === 0 ? (
                            <p className="text-xs text-slate-500">Belum ada transaksi.</p>
                        ) : (
                            paymentMethods.map((pm, idx) => (
                                <div key={idx} className="space-y-1.5 p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-bold text-white flex items-center gap-1.5">
                                            <span className={`w-2 h-2 rounded-full ${
                                                pm.method === 'cash' ? 'bg-emerald-400' : pm.method === 'qris' ? 'bg-rose-500' : 'bg-blue-400'
                                            }`}></span>
                                            {pm.label}
                                        </span>
                                        <span className="font-mono text-slate-300 font-semibold">
                                            {pm.percentage}% ({pm.count}x)
                                        </span>
                                    </div>
                                    <div className="w-full h-2 rounded-full bg-slate-900 overflow-hidden">
                                        <div
                                            style={{ width: `${pm.percentage}%` }}
                                            className={`h-full rounded-full ${
                                                pm.method === 'cash' ? 'bg-emerald-500' : pm.method === 'qris' ? 'bg-rose-600' : 'bg-blue-500'
                                            }`}
                                        />
                                    </div>
                                    <div className="text-[10px] text-slate-400 text-right font-mono">
                                        Rp {Number(pm.total).toLocaleString('id-ID')}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Order Type Ratio */}
                <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                    <div>
                        <h3 className="text-sm font-bold text-white">Tipe Layanan Pesanan</h3>
                        <p className="text-[11px] text-slate-400">Dine In vs Take Away</p>
                    </div>

                    <div className="space-y-3">
                        {orderTypes.length === 0 ? (
                            <p className="text-xs text-slate-500">Belum ada data tipe order.</p>
                        ) : (
                            orderTypes.map((ot, idx) => (
                                <div key={idx} className="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between">
                                    <div>
                                        <span className="text-xs font-bold text-white block">{ot.label}</span>
                                        <span className="text-[10px] text-slate-400">{ot.count} transaksi</span>
                                    </div>
                                    <span className="text-xs font-mono font-bold text-amber-400">
                                        Rp {Number(ot.total).toLocaleString('id-ID')}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
