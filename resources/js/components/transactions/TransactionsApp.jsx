import React, { useState, useEffect } from 'react';
import axios from 'axios';
import ThermalReceiptModal from '../ThermalReceiptModal';

export default function TransactionsApp({ user, initialSettings }) {
    const [transactions, setTransactions] = useState([]);
    const [summary, setSummary] = useState({ total_revenue: 0, total_orders: 0, paid_orders: 0 });
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [loading, setLoading] = useState(true);

    // Filters
    const [search, setSearch] = useState('');
    const [paymentStatus, setPaymentStatus] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('');
    const [kitchenStatus, setKitchenStatus] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    // Modals
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [receiptData, setReceiptData] = useState(null);
    const [isReceiptModalOpen, setIsReceiptModalOpen] = useState(false);

    const [voidModal, setVoidModal] = useState({ isOpen: false, order: null, reason: '' });
    const [voiding, setVoiding] = useState(false);

    // Detail & Item Notes Modal State
    const [detailModal, setDetailModal] = useState({ isOpen: false, order: null });
    const [editingNotes, setEditingNotes] = useState({});
    const [editingOrderNotes, setEditingOrderNotes] = useState('');
    const [savingItemId, setSavingItemId] = useState(null);
    const [savingOrderNotes, setSavingOrderNotes] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);

    useEffect(() => {
        fetchTransactions(1);
    }, [paymentStatus, paymentMethod, kitchenStatus, dateFrom, dateTo]);

    const showToast = (msg, duration = 3000) => {
        setToastMessage(msg);
        setTimeout(() => setToastMessage(null), duration);
    };

    const fetchTransactions = async (page = 1) => {
        setLoading(true);
        try {
            const params = {
                page,
                search,
                payment_status: paymentStatus || undefined,
                payment_method: paymentMethod || undefined,
                kitchen_status: kitchenStatus || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            };

            const res = await axios.get('/api/transactions', { params });
            if (res.data.success) {
                setTransactions(res.data.orders.data);
                setSummary(res.data.summary);
                setPagination({
                    current_page: res.data.orders.current_page,
                    last_page: res.data.orders.last_page,
                    total: res.data.orders.total,
                });
            }
        } catch (e) {
            console.error('Failed fetching transactions', e);
        } finally {
            setLoading(false);
        }
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        fetchTransactions(1);
    };

    const handleViewReceipt = async (orderId) => {
        try {
            const res = await axios.get(`/api/transactions/${orderId}`);
            if (res.data.success) {
                setSelectedOrder(res.data.order);
                setReceiptData(res.data.receipt);
                setIsReceiptModalOpen(true);
            }
        } catch (e) {
            alert('Gagal mengambil data struk: ' + e.message);
        }
    };

    const handleOpenDetailModal = async (order) => {
        try {
            const res = await axios.get(`/api/transactions/${order.id}`);
            const fullOrder = res.data.success ? res.data.order : order;
            const notesMap = {};
            (fullOrder.items || []).forEach(it => {
                notesMap[it.id] = it.notes || '';
            });
            setEditingNotes(notesMap);
            setEditingOrderNotes(fullOrder.notes || '');
            setDetailModal({
                isOpen: true,
                order: fullOrder,
            });
        } catch (e) {
            const notesMap = {};
            (order.items || []).forEach(it => {
                notesMap[it.id] = it.notes || '';
            });
            setEditingNotes(notesMap);
            setEditingOrderNotes(order.notes || '');
            setDetailModal({
                isOpen: true,
                order: order,
            });
        }
    };

    const handleSaveItemNote = async (itemId) => {
        setSavingItemId(itemId);
        try {
            const res = await axios.post(`/api/transactions/items/${itemId}/note`, {
                notes: editingNotes[itemId] ?? '',
            });
            if (res.data.success) {
                showToast('Catatan menu berhasil disimpan!');
                setDetailModal(prev => {
                    if (!prev.order) return prev;
                    return {
                        ...prev,
                        order: {
                            ...prev.order,
                            items: (prev.order.items || []).map(it =>
                                it.id === itemId ? { ...it, notes: editingNotes[itemId] } : it
                            ),
                        },
                    };
                });
                setTransactions(prev => prev.map(o => {
                    if (o.id !== detailModal.order?.id) return o;
                    return {
                        ...o,
                        items: (o.items || []).map(it =>
                            it.id === itemId ? { ...it, notes: editingNotes[itemId] } : it
                        ),
                    };
                }));
            }
        } catch (err) {
            alert('Gagal menyimpan catatan menu: ' + (err.response?.data?.message || err.message));
        } finally {
            setSavingItemId(null);
        }
    };

    const handleSaveOrderNotes = async (orderId) => {
        setSavingOrderNotes(true);
        try {
            const res = await axios.post(`/api/transactions/${orderId}/notes`, {
                notes: editingOrderNotes ?? '',
            });
            if (res.data.success) {
                showToast('Catatan transaksi berhasil disimpan!');
                setDetailModal(prev => {
                    if (!prev.order) return prev;
                    return {
                        ...prev,
                        order: { ...prev.order, notes: editingOrderNotes },
                    };
                });
                setTransactions(prev => prev.map(o => o.id === orderId ? { ...o, notes: editingOrderNotes } : o));
            }
        } catch (err) {
            alert('Gagal menyimpan catatan transaksi: ' + (err.response?.data?.message || err.message));
        } finally {
            setSavingOrderNotes(false);
        }
    };

    const handlePrintKitchenTicket = async (order) => {
        try {
            const res = await axios.get(`/api/orders/${order.id}/kitchen-ticket`);
            if (res.data.success) {
                setSelectedOrder(order);
                setReceiptData(res.data);
                setIsReceiptModalOpen(true);
            }
        } catch (e) {
            alert('Gagal mengambil tiket dapur: ' + e.message);
        }
    };

    const handlePrintItemTicket = async (item, order) => {
        try {
            const res = await axios.get(`/api/items/${item.id}/kitchen-ticket`);
            if (res.data.success) {
                setSelectedOrder(order);
                setReceiptData(res.data);
                setIsReceiptModalOpen(true);
            }
        } catch (e) {
            alert('Gagal mengambil tiket menu: ' + e.message);
        }
    };

    const openVoidModal = (order) => {
        setVoidModal({
            isOpen: true,
            order,
            reason: '',
        });
    };

    const handleConfirmVoid = async () => {
        if (!voidModal.order) return;
        setVoiding(true);
        try {
            const res = await axios.post(`/api/transactions/${voidModal.order.id}/void`, {
                reason: voidModal.reason || 'Dibatalkan oleh kasir',
            });
            if (res.data.success) {
                alert(res.data.message);
                setVoidModal({ isOpen: false, order: null, reason: '' });
                fetchTransactions(pagination.current_page);
            }
        } catch (err) {
            alert('Gagal membatalkan transaksi: ' + (err.response?.data?.message || err.message));
        } finally {
            setVoiding(false);
        }
    };

    const handleExportCsv = () => {
        const queryParams = new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo,
        }).toString();
        window.location.href = `/api/transactions-export?${queryParams}`;
    };

    return (
        <div className="space-y-6">
            {/* Header & KPI Summary Cards */}
            <div>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div>
                        <h1 className="text-xl font-black text-white">Dashboard Riwayat Transaksi</h1>
                        <p className="text-xs text-slate-400">Kelola dan pantau seluruh transaksi kasir POS</p>
                    </div>

                    <button
                        type="button"
                        onClick={handleExportCsv}
                        className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 transition-all flex items-center gap-2 border border-slate-700/80 shadow">
                        <svg className="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Laporan CSV
                    </button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-lg">
                        <span className="text-xs font-semibold text-slate-400 block mb-1">Total Pendapatan Terfilter</span>
                        <div className="text-2xl font-black text-white font-mono">
                            Rp {summary.total_revenue.toLocaleString('id-ID')}
                        </div>
                    </div>
                    <div className="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-lg">
                        <span className="text-xs font-semibold text-slate-400 block mb-1">Total Pesanan</span>
                        <div className="text-2xl font-black text-white font-mono">
                            {summary.total_orders} <span className="text-xs font-sans text-slate-500 font-normal">transaksi</span>
                        </div>
                    </div>
                    <div className="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-lg">
                        <span className="text-xs font-semibold text-slate-400 block mb-1">Transaksi Lunas (Paid)</span>
                        <div className="text-2xl font-black text-emerald-400 font-mono">
                            {summary.paid_orders} <span className="text-xs font-sans text-slate-500 font-normal">selesai</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3">
                <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2">
                    <div className="flex-1 min-w-[200px]">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari no. order, pelanggan, atau meja..."
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500"
                        />
                    </div>

                    <select
                        value={paymentStatus}
                        onChange={(e) => setPaymentStatus(e.target.value)}
                        className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="">Semua Status Bayar</option>
                        <option value="paid">Lunas (Paid)</option>
                        <option value="void">Dibatalkan (Void)</option>
                    </select>

                    <select
                        value={paymentMethod}
                        onChange={(e) => setPaymentMethod(e.target.value)}
                        className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="">Semua Metode</option>
                        <option value="cash">Tunai (Cash)</option>
                        <option value="qris">QRIS</option>
                        <option value="debit_card">Kartu Debit</option>
                    </select>

                    <select
                        value={kitchenStatus}
                        onChange={(e) => setKitchenStatus(e.target.value)}
                        className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="">Semua Status Dapur</option>
                        <option value="pending">Menunggu (Pending)</option>
                        <option value="preparing">Sedang Dimasak</option>
                        <option value="ready">Siap Saji</option>
                        <option value="completed">Selesai</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>

                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="px-2.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"
                        title="Dari Tanggal"
                    />

                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="px-2.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"
                        title="Sampai Tanggal"
                    />

                    <button
                        type="submit"
                        className="px-4 py-2 bg-rose-600 hover:bg-rose-500 rounded-xl text-xs font-bold text-white transition-all shadow">
                        Cari
                    </button>

                    {(search || paymentStatus || paymentMethod || kitchenStatus || dateFrom || dateTo) && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setPaymentStatus('');
                                setPaymentMethod('');
                                setKitchenStatus('');
                                setDateFrom('');
                                setDateTo('');
                            }}
                            className="px-3 py-2 text-xs text-slate-400 hover:text-white">
                            Reset Filter
                        </button>
                    )}
                </form>
            </div>

            {/* Table Content */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-xl">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-950/80 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
                            <tr>
                                <th className="p-3.5 font-bold">No. Order</th>
                                <th className="p-3.5 font-bold">Waktu</th>
                                <th className="p-3.5 font-bold">Pelanggan & Meja</th>
                                <th className="p-3.5 font-bold">Tipe</th>
                                <th className="p-3.5 font-bold">Total Tagihan</th>
                                <th className="p-3.5 font-bold">Metode</th>
                                <th className="p-3.5 font-bold">Status Bayar</th>
                                <th className="p-3.5 font-bold">Status Dapur</th>
                                <th className="p-3.5 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/80">
                            {loading ? (
                                <tr>
                                    <td colSpan="9" className="p-8 text-center text-slate-500">
                                        Memuat data transaksi...
                                    </td>
                                </tr>
                            ) : transactions.length === 0 ? (
                                <tr>
                                    <td colSpan="9" className="p-8 text-center text-slate-500">
                                        Tidak ada transaksi yang cocok dengan filter.
                                    </td>
                                </tr>
                            ) : (
                                transactions.map(order => (
                                    <tr key={order.id} className="hover:bg-slate-850/50 transition-colors">
                                        <td className="p-3.5 font-mono font-bold text-white">
                                            {order.order_number}
                                        </td>
                                        <td className="p-3.5 text-slate-400 whitespace-nowrap">
                                            {new Date(order.created_at).toLocaleString('id-ID', {
                                                day: 'numeric',
                                                month: 'short',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </td>
                                        <td className="p-3.5">
                                            <div className="font-semibold text-white">{order.customer_name}</div>
                                            <div className="text-[11px] text-slate-400">{order.table_number || '-'}</div>
                                            {(order.notes || order.items?.some(it => it.notes)) && (
                                                <span className="inline-flex items-center gap-1 text-[10px] text-amber-300 font-semibold mt-1 bg-amber-500/15 border border-amber-500/30 px-1.5 py-0.5 rounded">
                                                    📝 Catatan Khusus
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3.5 uppercase text-[10px] font-bold text-slate-300">
                                            {order.order_type.replace('_', ' ')}
                                        </td>
                                        <td className="p-3.5 font-mono font-bold text-white">
                                            Rp {Number(order.total_amount).toLocaleString('id-ID')}
                                        </td>
                                        <td className="p-3.5 font-bold uppercase text-[10px]">
                                            <span className="px-2 py-0.5 rounded-md bg-slate-800 text-slate-300 border border-slate-700">
                                                {order.payment_method}
                                            </span>
                                        </td>
                                        <td className="p-3.5">
                                            {order.payment_status === 'paid' ? (
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                    LUNAS
                                                </span>
                                            ) : (
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                                    VOID
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-3.5">
                                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                                                order.kitchen_status === 'completed'
                                                    ? 'bg-slate-800 text-slate-400'
                                                    : order.kitchen_status === 'ready'
                                                    ? 'bg-emerald-500/20 text-emerald-300'
                                                    : order.kitchen_status === 'preparing'
                                                    ? 'bg-amber-500/20 text-amber-300'
                                                    : 'bg-rose-500/20 text-rose-300'
                                            }`}>
                                                {order.kitchen_status}
                                            </span>
                                        </td>
                                        <td className="p-3.5 text-right space-x-1.5 whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() => handleOpenDetailModal(order)}
                                                className="px-2.5 py-1.5 rounded-lg bg-rose-600/10 hover:bg-rose-600/20 text-xs font-semibold text-rose-400 border border-rose-500/30 transition-all inline-flex items-center gap-1"
                                                title="Lihat Detail & Kelola Catatan Khusus Tiap Menu">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                <span>Detail & Catatan</span>
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => handleViewReceipt(order.id)}
                                                className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-all inline-flex items-center gap-1">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                Cetak Struk
                                            </button>

                                            {order.payment_status === 'paid' && (
                                                <button
                                                    type="button"
                                                    onClick={() => openVoidModal(order)}
                                                    className="px-2.5 py-1.5 rounded-lg bg-rose-600/10 hover:bg-rose-600/20 text-xs font-semibold text-rose-400 border border-rose-500/30 transition-all">
                                                    Void
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination Controls */}
                {pagination.last_page > 1 && (
                    <div className="p-4 border-t border-slate-800 bg-slate-950/60 flex items-center justify-between text-xs">
                        <span className="text-slate-400">
                            Halaman {pagination.current_page} dari {pagination.last_page} ({pagination.total} transaksi)
                        </span>

                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                disabled={pagination.current_page <= 1}
                                onClick={() => fetchTransactions(pagination.current_page - 1)}
                                className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40">
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                disabled={pagination.current_page >= pagination.last_page}
                                onClick={() => fetchTransactions(pagination.current_page + 1)}
                                className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40">
                                Selanjutnya
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* VOID CONFIRMATION MODAL */}
            {voidModal.isOpen && voidModal.order && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-sm w-full p-5 space-y-4">
                        <div className="flex items-center gap-3 text-rose-400">
                            <div className="w-10 h-10 rounded-xl bg-rose-500/20 border border-rose-500/30 flex items-center justify-center">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-white">Konfirmasi Void Transaksi</h3>
                                <p className="text-xs text-slate-400">#{voidModal.order.order_number}</p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-300">
                            Membatalkan transaksi ini akan mengembalikan stok seluruh produk dan membatalkan status pesanan di dapur.
                        </p>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Alasan Pembatalan / Void:</label>
                            <input
                                type="text"
                                value={voidModal.reason}
                                onChange={(e) => setVoidModal(prev => ({ ...prev, reason: e.target.value }))}
                                placeholder="Contoh: Kesalahan input kasir / Pelanggan batal"
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white"
                            />
                        </div>

                        <div className="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                onClick={() => setVoidModal({ isOpen: false, order: null, reason: '' })}
                                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white">
                                Batal
                            </button>
                            <button
                                type="button"
                                disabled={voiding}
                                onClick={handleConfirmVoid}
                                className="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-xs font-bold text-white transition-all shadow-md shadow-rose-950">
                                {voiding ? 'Memproses...' : 'Ya, Batalkan Transaksi'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* DETAIL & MENU NOTES MODAL */}
            {detailModal.isOpen && detailModal.order && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[92vh] flex flex-col overflow-hidden">
                        {/* Modal Header */}
                        <div className="p-4 sm:p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div>
                                    <h3 className="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                                        <span>Detail Order #{detailModal.order.order_number}</span>
                                    </h3>
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-slate-400 mt-0.5">
                                        <span>{detailModal.order.customer_name || 'Pelanggan Umum'}</span>
                                        <span>•</span>
                                        <span className="text-amber-400 font-semibold">{detailModal.order.table_number || detailModal.order.order_type}</span>
                                        <span>•</span>
                                        <span>{new Date(detailModal.order.created_at).toLocaleString('id-ID')}</span>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDetailModal({ isOpen: false, order: null })}
                                className="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-all">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {/* Modal Body */}
                        <div className="p-4 sm:p-5 overflow-y-auto space-y-5">
                            {/* Order Status & Financial Summary Banner */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-xl bg-slate-950 border border-slate-800/80 text-xs">
                                <div>
                                    <span className="text-[10px] text-slate-400 block font-semibold">Status Bayar</span>
                                    <span className={`font-bold uppercase ${detailModal.order.payment_status === 'paid' ? 'text-emerald-400' : 'text-rose-400'}`}>
                                        {detailModal.order.payment_status}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 block font-semibold">Status Dapur</span>
                                    <span className="font-bold uppercase text-amber-400">
                                        {detailModal.order.kitchen_status}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 block font-semibold">Metode Bayar</span>
                                    <span className="font-bold uppercase text-slate-200">
                                        {detailModal.order.payment_method}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 block font-semibold">Total Nilai</span>
                                    <span className="font-bold font-mono text-white">
                                        Rp {Number(detailModal.order.total_amount).toLocaleString('id-ID')}
                                    </span>
                                </div>
                            </div>

                            {/* Section: Daftar Menu & Catatan Khusus */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="text-xs font-bold text-white flex items-center gap-1.5">
                                            <svg className="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                            <span>Daftar Menu & Catatan Khusus Tiap Menu</span>
                                        </h4>
                                        <p className="text-[11px] text-slate-400 mt-0.5">
                                            Textarea luas disediakan pada masing-masing menu untuk mencatat instruksi khusus koki.
                                        </p>
                                    </div>
                                    <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-800 text-slate-300">
                                        {detailModal.order.items?.length || 0} Menu
                                    </span>
                                </div>

                                <div className="space-y-3">
                                    {(detailModal.order.items || []).map((item) => (
                                        <div key={item.id} className="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 space-y-2.5">
                                            {/* Item Info Header */}
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <span className="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 font-mono font-bold text-xs border border-amber-500/30">
                                                        {item.quantity}x
                                                    </span>
                                                    <span className="text-xs font-bold text-white">
                                                        {item.product_name}
                                                    </span>
                                                    <span className="text-[11px] text-slate-400">
                                                        (@ Rp {Number(item.price).toLocaleString('id-ID')})
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs font-mono font-bold text-slate-200">
                                                        Rp {Number(item.subtotal).toLocaleString('id-ID')}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => handlePrintItemTicket(item, detailModal.order)}
                                                        title="Cetak Tiket Menu ke Thermal Printer"
                                                        className="px-2 py-1 rounded-lg bg-slate-800 hover:bg-orange-600 hover:text-white text-slate-300 text-[10px] font-semibold transition-all flex items-center gap-1 border border-slate-700">
                                                        <svg className="w-3 h-3 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                        <span>Cetak Menu</span>
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Spacious Textarea for Item Notes */}
                                            <div>
                                                <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                                                    Catatan Khusus Menu Ini:
                                                </label>
                                                <textarea
                                                    rows={3}
                                                    value={editingNotes[item.id] !== undefined ? editingNotes[item.id] : (item.notes || '')}
                                                    onChange={(e) => setEditingNotes(prev => ({ ...prev, [item.id]: e.target.value }))}
                                                    placeholder="Tulis catatan khusus menu di sini (contoh: Level 3 pedas, jangan pakai seledri & bawang, kuah dipisah, paha atas, dll)..."
                                                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 rounded-xl text-xs text-white placeholder-slate-500 resize-y min-h-[80px] leading-relaxed transition-all"
                                                />
                                            </div>

                                            {/* Suggestion Chips & Save Button */}
                                            <div className="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-800/80">
                                                <div className="flex flex-wrap items-center gap-1">
                                                    <span className="text-[10px] text-slate-500 font-semibold mr-1">Saran:</span>
                                                    {['Tanpa Bawang', 'Pedas Sedang', 'Ekstra Sambal', 'Kuah Dipisah', 'Es Sedikit', 'Bungkus Rapi'].map((chip) => (
                                                        <button
                                                            key={chip}
                                                            type="button"
                                                            onClick={() => {
                                                                setEditingNotes(prev => {
                                                                    const cur = (prev[item.id] !== undefined ? prev[item.id] : (item.notes || '')).trim();
                                                                    const updated = cur ? `${cur}, ${chip}` : chip;
                                                                    return { ...prev, [item.id]: updated };
                                                                });
                                                            }}
                                                            className="px-1.5 py-0.5 rounded bg-slate-850 hover:bg-slate-700 text-slate-400 hover:text-white text-[10px] font-medium border border-slate-800 transition-all">
                                                            + {chip}
                                                        </button>
                                                    ))}
                                                </div>

                                                <button
                                                    type="button"
                                                    disabled={savingItemId === item.id}
                                                    onClick={() => handleSaveItemNote(item.id)}
                                                    className="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 disabled:opacity-50">
                                                    {savingItemId === item.id ? (
                                                        <>
                                                            <svg className="animate-spin w-3 h-3" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                            <span>Menyimpan...</span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                                                            <span>Simpan Catatan Menu</span>
                                                        </>
                                                    )}
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Section: Catatan Tambahan Transaksi */}
                            <div className="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 space-y-2">
                                <label className="block text-xs font-bold text-white">
                                    Catatan Umum Pesanan / Transaksi:
                                </label>
                                <textarea
                                    rows={2}
                                    value={editingOrderNotes}
                                    onChange={(e) => setEditingOrderNotes(e.target.value)}
                                    placeholder="Contoh: Tamu VIP, meja dipersiapkan lebih awal, bungkus kantong ganda..."
                                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 rounded-xl text-xs text-white placeholder-slate-500 resize-y min-h-[60px]"
                                />
                                <div className="flex justify-end">
                                    <button
                                        type="button"
                                        disabled={savingOrderNotes}
                                        onClick={() => handleSaveOrderNotes(detailModal.order.id)}
                                        className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-all">
                                        {savingOrderNotes ? 'Menyimpan...' : 'Simpan Catatan Pesanan'}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="p-4 border-t border-slate-800 bg-slate-950/80 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => handlePrintKitchenTicket(detailModal.order)}
                                    className="px-3 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition-all flex items-center gap-1.5 shadow-md shadow-orange-950">
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    <span>Cetak Tiket Dapur</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleViewReceipt(detailModal.order.id)}
                                    className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-all flex items-center gap-1.5">
                                    <svg className="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Cetak Struk Transaksi</span>
                                </button>
                            </div>

                            <button
                                type="button"
                                onClick={() => setDetailModal({ isOpen: false, order: null })}
                                className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* THERMAL RECEIPT MODAL */}
            <ThermalReceiptModal
                isOpen={isReceiptModalOpen}
                onClose={() => setIsReceiptModalOpen(false)}
                order={selectedOrder}
                receiptData={receiptData}
            />

            {/* Floating Toast Notification */}
            {toastMessage && (
                <div className="fixed bottom-6 right-6 z-50 bg-slate-900 border border-emerald-500 text-emerald-300 px-4 py-2.5 rounded-xl shadow-2xl flex items-center gap-2 animate-in fade-in slide-in-from-bottom duration-200">
                    <svg className="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                    <span className="text-xs font-semibold">{toastMessage}</span>
                </div>
            )}
        </div>
    );
}
