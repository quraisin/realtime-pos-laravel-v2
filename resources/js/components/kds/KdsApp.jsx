import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { playKitchenAlertChime } from '../../utils/audio';
import ThermalReceiptModal from '../ThermalReceiptModal';

export default function KdsApp({ user, initialSettings }) {
    const [orders, setOrders] = useState([]);
    const [filterStatus, setFilterStatus] = useState('active'); // 'active' | 'pending' | 'preparing' | 'ready' | 'completed'
    const [filterType, setFilterType] = useState('all'); // 'all' | 'dine_in' | 'take_away'
    const [currentTime, setCurrentTime] = useState(new Date());
    const [isMuted, setIsMuted] = useState(false);
    const [sseConnected, setSseConnected] = useState(false);
    const [newOrderAlert, setNewOrderAlert] = useState(null);
    const [newOrderRibbonIds, setNewOrderRibbonIds] = useState(new Set());
    const [updatingOrderId, setUpdatingOrderId] = useState(null);
    const [printingItemId, setPrintingItemId] = useState(null);
    const [toastMessage, setToastMessage] = useState(null);

    // Thermal modal for kitchen ticket & per-menu ticket reprint
    const [kitchenTicketModal, setKitchenTicketModal] = useState({ isOpen: false, order: null, receiptData: null });

    const eventSourceRef = useRef(null);
    const knownOrderIdsRef = useRef(new Set());
    const lastTimestampRef = useRef(0);
    const ribbonTimersRef = useRef({});

    // Toast helper
    const showToast = (msg, duration = 3000) => {
        setToastMessage(msg);
        setTimeout(() => setToastMessage(null), duration);
    };

    // Live kitchen clock
    useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    // Initial fetch, SSE Setup, and 3-second background polling
    useEffect(() => {
        fetchOrders(true);
        setupRealtimeSSE();

        // 3-second polling interval guarantees real-time freshness without blocking
        const pollInterval = setInterval(() => {
            fetchOrders(false);
        }, 3000);

        return () => {
            clearInterval(pollInterval);
            if (eventSourceRef.current) {
                eventSourceRef.current.close();
            }
            Object.values(ribbonTimersRef.current).forEach(t => clearTimeout(t));
        };
    }, []);

    const fetchOrders = async (isInitial = false) => {
        try {
            const res = await axios.get('/api/kds/orders');
            if (res.data.success) {
                processIncomingOrders(res.data.orders, isInitial);
                lastTimestampRef.current = res.data.timestamp || Date.now() / 1000;
            }
        } catch (e) {
            console.error('Failed fetching KDS orders', e);
        }
    };

    const processIncomingOrders = (newOrdersList, isInitial = false) => {
        // Detect newly arrived orders
        const incomingNew = isInitial
            ? newOrdersList.filter(o => {
                if (o.kitchen_status !== 'pending') return false;
                const createdMs = new Date(o.created_at).getTime();
                return (Date.now() - createdMs) < 5000;
            })
            : newOrdersList.filter(o => 
                !knownOrderIdsRef.current.has(o.id) && o.kitchen_status === 'pending'
            );

        if (incomingNew.length > 0) {
            // Apply 5-second ribbon to each new order
            incomingNew.forEach(newOrder => {
                const orderId = newOrder.id;
                let duration = 5000;
                if (isInitial) {
                    const elapsed = Date.now() - new Date(newOrder.created_at).getTime();
                    duration = Math.max(1000, 5000 - elapsed);
                }

                if (ribbonTimersRef.current[orderId]) {
                    clearTimeout(ribbonTimersRef.current[orderId]);
                }

                setNewOrderRibbonIds(prev => new Set(prev).add(orderId));

                ribbonTimersRef.current[orderId] = setTimeout(() => {
                    setNewOrderRibbonIds(prev => {
                        const next = new Set(prev);
                        next.delete(orderId);
                        return next;
                    });
                    delete ribbonTimersRef.current[orderId];
                }, duration);
            });

            if (!isInitial) {
                // Play kitchen audio chime
                if (!isMuted) {
                    playKitchenAlertChime();
                }

                // Show visual alert banner
                const newest = incomingNew[0];
                setNewOrderAlert({
                    orderNumber: newest.order_number,
                    table: newest.table_number || newest.order_type,
                    itemsCount: newest.items?.length || 0,
                });
                setTimeout(() => setNewOrderAlert(null), 5000);
            }
        }

        // Update known IDs set and orders state
        knownOrderIdsRef.current = new Set(newOrdersList.map(o => o.id));
        setOrders(newOrdersList);
    };

    const setupRealtimeSSE = () => {
        if (eventSourceRef.current) {
            eventSourceRef.current.close();
        }

        try {
            const url = `/api/kds/stream?last_seen=${lastTimestampRef.current}`;
            const es = new EventSource(url);
            eventSourceRef.current = es;

            es.onopen = () => {
                setSseConnected(true);
            };

            es.addEventListener('ping', () => {
                setSseConnected(true);
            });

            es.addEventListener('update', (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'orders_update' && data.orders) {
                        lastTimestampRef.current = data.timestamp;
                        processIncomingOrders(data.orders, false);
                    }
                } catch (err) {
                    console.error('SSE JSON error', err);
                }
            });

            es.onerror = () => {
                setSseConnected(false);
                es.close();
                // Reconnect after 3 seconds
                setTimeout(setupRealtimeSSE, 3000);
            };
        } catch (e) {
            console.warn('SSE initialization error, relying on active polling:', e);
            setSseConnected(false);
        }
    };

    // Dynamic counts computed directly from orders state
    const counts = {
        pending: orders.filter(o => o.kitchen_status === 'pending').length,
        preparing: orders.filter(o => o.kitchen_status === 'preparing').length,
        ready: orders.filter(o => o.kitchen_status === 'ready').length,
        completed: orders.filter(o => o.kitchen_status === 'completed').length,
    };

    // Update Status with optimistic UI
    const updateOrderStatus = async (orderId, newStatus) => {
        setUpdatingOrderId(orderId);

        // Optimistic UI state update
        setOrders(prev => prev.map(o => {
            if (o.id !== orderId) return o;
            const updated = { ...o, kitchen_status: newStatus };
            if (newStatus === 'preparing' && !o.kitchen_started_at) {
                updated.kitchen_started_at = new Date().toISOString();
            } else if (newStatus === 'ready') {
                updated.kitchen_ready_at = new Date().toISOString();
                updated.items = (o.items || []).map(it => ({ ...it, is_prepared: true }));
            } else if (newStatus === 'completed') {
                updated.kitchen_completed_at = new Date().toISOString();
            }
            return updated;
        }));

        try {
            const res = await axios.post(`/api/kds/orders/${orderId}/status`, { status: newStatus });
            if (res.data.success) {
                if (ribbonTimersRef.current[orderId]) {
                    clearTimeout(ribbonTimersRef.current[orderId]);
                    delete ribbonTimersRef.current[orderId];
                }
                setNewOrderRibbonIds(prev => {
                    const next = new Set(prev);
                    next.delete(orderId);
                    return next;
                });
                setOrders(prev => prev.map(o => o.id === orderId ? res.data.order : o));
                showToast(`Status pesanan diperbarui ke ${newStatus.toUpperCase()}`);
            }
        } catch (e) {
            alert('Gagal update status: ' + (e.response?.data?.message || e.message));
            fetchOrders(false);
        } finally {
            setUpdatingOrderId(null);
        }
    };

    // Toggle checklist item with optimistic UI
    const toggleItemChecklist = async (itemId, orderId) => {
        // Optimistic UI toggle
        setOrders(prev => prev.map(o => {
            if (o.id !== orderId) return o;
            const updatedItems = (o.items || []).map(it => 
                it.id === itemId ? { ...it, is_prepared: !it.is_prepared } : it
            );
            const allPrepared = updatedItems.length > 0 && updatedItems.every(it => it.is_prepared);
            return {
                ...o,
                kitchen_status: (allPrepared && o.kitchen_status === 'preparing') ? 'ready' : o.kitchen_status,
                items: updatedItems,
            };
        }));

        try {
            const res = await axios.post(`/api/kds/items/${itemId}/toggle`);
            if (res.data.success && res.data.order) {
                setOrders(prev => prev.map(o => o.id === res.data.order.id ? res.data.order : o));
            }
        } catch (e) {
            console.error('Toggle item error', e);
            fetchOrders(false);
        }
    };

    // Kitchen Ticket Printing: Full Order
    const handlePrintKitchenTicket = async (order) => {
        try {
            const res = await axios.get(`/api/orders/${order.id}/kitchen-ticket`);
            if (res.data.success) {
                setKitchenTicketModal({
                    isOpen: true,
                    order: order,
                    receiptData: res.data,
                });
            }
        } catch (e) {
            alert('Gagal memuat tiket dapur: ' + e.message);
        }
    };

    // Quick direct print whole order to physical thermal printer
    const handleDirectPrintOrder = async (order) => {
        setUpdatingOrderId(order.id);
        try {
            const res = await axios.post(`/api/printer/print-order/${order.id}`, { type: 'kitchen' });
            if (res.data.success) {
                showToast(`Tiket Dapur #${order.order_number} berhasil dicetak!`);
            } else {
                handlePrintKitchenTicket(order);
            }
        } catch (e) {
            handlePrintKitchenTicket(order);
        } finally {
            setUpdatingOrderId(null);
        }
    };

    // Kitchen Item Ticket Printing: Individual Menu Item
    const handlePrintItemTicket = async (item, order) => {
        setPrintingItemId(item.id);
        try {
            const res = await axios.get(`/api/items/${item.id}/kitchen-ticket`);
            if (res.data.success) {
                setKitchenTicketModal({
                    isOpen: true,
                    order: order,
                    receiptData: res.data,
                });
            }
        } catch (e) {
            alert('Gagal memuat tiket menu: ' + e.message);
        } finally {
            setPrintingItemId(null);
        }
    };

    // Quick direct print individual menu item to physical thermal printer
    const handleDirectPrintItem = async (e, item, order) => {
        e.stopPropagation();
        setPrintingItemId(item.id);
        try {
            const res = await axios.post(`/api/printer/print-item/${item.id}`);
            if (res.data.success) {
                showToast(`Tiket Menu "${item.product_name}" tercetak ke printer!`);
            } else {
                handlePrintItemTicket(item, order);
            }
        } catch (e) {
            handlePrintItemTicket(item, order);
        } finally {
            setPrintingItemId(null);
        }
    };

    // Filter orders
    const statusPriority = { pending: 1, preparing: 2, ready: 3, completed: 4 };

    const filteredOrders = orders
        .filter(o => {
            // Status filter
            if (filterStatus === 'active') {
                if (!['pending', 'preparing', 'ready'].includes(o.kitchen_status)) return false;
            } else if (filterStatus !== 'all') {
                if (o.kitchen_status !== filterStatus) return false;
            }

            // Type filter
            if (filterType !== 'all') {
                if (o.order_type !== filterType) return false;
            }

            return true;
        })
        .sort((a, b) => {
            // Sort by status priority first if multiple statuses visible
            const priorityA = statusPriority[a.kitchen_status] || 99;
            const priorityB = statusPriority[b.kitchen_status] || 99;
            if (priorityA !== priorityB) {
                return priorityA - priorityB;
            }
            // For each status, order by created_at descending (latest order first)
            const timeA = new Date(a.created_at).getTime();
            const timeB = new Date(b.created_at).getTime();
            return timeB - timeA;
        });

    return (
        <div className="flex-1 flex flex-col h-full bg-slate-950 overflow-hidden">
            {/* TOP BAR: Clock, Status, Realtime Indicator */}
            <div className="px-4 py-3 bg-slate-900 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                {/* Left: Title & Live Beacon */}
                <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-sm font-extrabold text-white">Kitchen Display System (KDS)</h2>
                            <div className="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition-colors bg-emerald-500/10 border-emerald-500/30 text-emerald-400">
                                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                <span>{sseConnected ? 'Realtime Aktif (SSE)' : 'Realtime Aktif (KDS Online)'}</span>
                            </div>
                        </div>
                        <p className="text-[11px] text-slate-400">Monitor antrean pesanan dapur & cetak tiket thermal</p>
                    </div>
                </div>

                {/* Center: Live Kitchen Clock */}
                <div className="hidden md:flex items-center gap-3 px-4 py-1.5 rounded-xl bg-slate-950 border border-slate-800">
                    <div className="text-right">
                        <span className="text-[10px] text-slate-400 block leading-tight">Waktu Dapur</span>
                        <span className="text-xs font-mono font-bold text-amber-400">
                            {currentTime.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })}
                        </span>
                    </div>
                    <div className="text-lg font-mono font-black text-white tracking-widest pl-2 border-l border-slate-800">
                        {currentTime.toLocaleTimeString('id-ID')}
                    </div>
                </div>

                {/* Right: Sound chime toggle, Test chime, Manual refresh */}
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            playKitchenAlertChime();
                            showToast('Bel notifikasi dapur berbunyi');
                        }}
                        className="px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-[11px] font-semibold text-slate-300 transition-all"
                        title="Tes Nada Notifikasi">
                        Tes Bel
                    </button>

                    <button
                        type="button"
                        onClick={() => setIsMuted(!isMuted)}
                        className={`p-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 ${isMuted ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'}`}
                        title={isMuted ? 'Audio Dibisukan' : 'Audio Aktif'}>
                        {isMuted ? (
                            <>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                                <span className="text-[11px]">Mute</span>
                            </>
                        ) : (
                            <>
                                <svg className="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                                <span className="text-[11px]">Suara ON</span>
                            </>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            fetchOrders(false);
                            showToast('Memperbarui antrean pesanan...');
                        }}
                        className="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300"
                        title="Segarkan data">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>

            {/* REALTIME ALERT POPUP BANNER */}
            {newOrderAlert && (
                <div className="bg-gradient-to-r from-rose-600 via-amber-600 to-rose-600 text-white px-4 py-2.5 shadow-2xl flex items-center justify-between animate-bounce">
                    <div className="flex items-center gap-3">
                        <span className="w-3 h-3 rounded-full bg-white animate-ping"></span>
                        <strong className="text-xs uppercase tracking-wide">
                            ORDER BARU MASUK: #{newOrderAlert.orderNumber} ({newOrderAlert.table})
                        </strong>
                        <span className="text-xs opacity-90">• {newOrderAlert.itemsCount} menu makanan/minuman</span>
                    </div>
                    <button
                        type="button"
                        onClick={() => setNewOrderAlert(null)}
                        className="text-white hover:opacity-75 text-xs font-bold px-2 py-0.5 rounded bg-black/20">
                        Tutup
                    </button>
                </div>
            )}

            {/* SECONDARY CONTROLS & FILTER TABS */}
            <div className="px-4 py-2.5 bg-slate-900/40 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                {/* Status Filter Tabs */}
                <div className="flex items-center gap-1.5 overflow-x-auto text-xs font-bold">
                    <button
                        type="button"
                        onClick={() => setFilterStatus('active')}
                        className={`px-3 py-1.5 rounded-xl transition-all ${filterStatus === 'active' ? 'bg-amber-600 text-white shadow' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                        Aktif di Dapur ({counts.pending + counts.preparing + counts.ready})
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterStatus('pending')}
                        className={`px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 ${filterStatus === 'pending' ? 'bg-rose-600 text-white shadow' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                        <span>Menunggu</span>
                        <span className="w-5 h-5 rounded-full bg-rose-500/30 text-rose-200 text-[10px] flex items-center justify-center font-bold">
                            {counts.pending}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterStatus('preparing')}
                        className={`px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 ${filterStatus === 'preparing' ? 'bg-amber-600 text-white shadow' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                        <span>Sedang Dimasak</span>
                        <span className="w-5 h-5 rounded-full bg-amber-500/30 text-amber-200 text-[10px] flex items-center justify-center font-bold">
                            {counts.preparing}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterStatus('ready')}
                        className={`px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 ${filterStatus === 'ready' ? 'bg-emerald-600 text-white shadow' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                        <span>Siap Saji</span>
                        <span className="w-5 h-5 rounded-full bg-emerald-500/30 text-emerald-200 text-[10px] flex items-center justify-center font-bold">
                            {counts.ready}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterStatus('completed')}
                        className={`px-3 py-1.5 rounded-xl transition-all ${filterStatus === 'completed' ? 'bg-slate-700 text-white' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                        Selesai ({counts.completed})
                    </button>
                </div>

                {/* Order Type Filter */}
                <div className="flex items-center gap-1 text-xs">
                    <span className="text-slate-500 mr-1 text-[11px]">Tipe:</span>
                    <button
                        type="button"
                        onClick={() => setFilterType('all')}
                        className={`px-2.5 py-1 rounded-lg text-xs font-semibold ${filterType === 'all' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white'}`}>
                        Semua
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterType('dine_in')}
                        className={`px-2.5 py-1 rounded-lg text-xs font-semibold ${filterType === 'dine_in' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white'}`}>
                        Dine In
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilterType('take_away')}
                        className={`px-2.5 py-1 rounded-lg text-xs font-semibold ${filterType === 'take_away' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white'}`}>
                        Take Away
                    </button>
                </div>
            </div>

            {/* KDS ORDER CARDS CONTAINER */}
            <div className="flex-1 p-4 overflow-y-auto">
                {filteredOrders.length === 0 ? (
                    <div className="h-full flex flex-col items-center justify-center text-slate-500 text-sm">
                        <div className="w-16 h-16 rounded-full bg-slate-900 flex items-center justify-center text-slate-700 mb-3">
                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p className="font-bold text-slate-400">Tidak ada antrian pesanan saat ini</p>
                        <p className="text-xs text-slate-500 mt-1">Pesanan baru dari kasir akan muncul secara realtime di layar ini.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {filteredOrders.map(order => {
                            const isNewOrder = newOrderRibbonIds.has(order.id);

                            const statusTheme = {
                                pending: {
                                    bgBorder: isNewOrder
                                        ? 'border-rose-500 ring-4 ring-rose-500/60 shadow-2xl shadow-rose-950/70 bg-slate-900/95 scale-[1.01]'
                                        : 'border-rose-500/40 bg-slate-900/90',
                                    headerBg: isNewOrder ? 'bg-gradient-to-r from-rose-600/30 via-amber-500/20 to-rose-600/30 text-rose-200' : 'bg-rose-500/20 text-rose-300',
                                    label: 'MENUNGGU',
                                },
                                preparing: {
                                    bgBorder: 'border-amber-500/50 bg-slate-900/90',
                                    headerBg: 'bg-amber-500/20 text-amber-300',
                                    label: 'SEDANG DIMASAK',
                                },
                                ready: {
                                    bgBorder: 'border-emerald-500/50 bg-slate-900/90',
                                    headerBg: 'bg-emerald-500/20 text-emerald-300',
                                    label: 'SIAP SAJI',
                                },
                                completed: {
                                    bgBorder: 'border-slate-800 bg-slate-900/60 opacity-75',
                                    headerBg: 'bg-slate-800 text-slate-400',
                                    label: 'SELESAI',
                                },
                            }[order.kitchen_status] || { bgBorder: 'border-slate-800', headerBg: 'bg-slate-800', label: 'STATUS' };

                            return (
                                <div
                                    key={order.id}
                                    className={`relative rounded-2xl border shadow-xl flex flex-col justify-between overflow-hidden transition-all duration-300 ${statusTheme.bgBorder}`}>
                                    
                                    {/* TOP RIBBON BANNER STRIP (Disappears in 5 seconds) */}
                                    {isNewOrder && (
                                        <div className="bg-gradient-to-r from-rose-600 via-amber-500 to-rose-600 text-white text-[11px] font-black uppercase tracking-wider py-1 px-3 flex items-center justify-between border-b border-rose-400/40 shadow-inner animate-pulse z-20">
                                            <div className="flex items-center gap-1.5">
                                                <span className="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                                <span>✨ PESANAN BARU ✨</span>
                                            </div>
                                            <span className="text-[9px] bg-black/40 px-2 py-0.5 rounded-full font-bold text-amber-200">
                                                5 DETIK
                                            </span>
                                        </div>
                                    )}

                                    {/* Order Card Content Container */}
                                    <div className="flex-1 flex flex-col">
                                        {/* Order Card Header: 2 Clean Rows, No Overlapping */}
                                        <div className={`p-3 border-b border-slate-800 ${statusTheme.headerBg} flex flex-col gap-2`}>
                                            {/* Baris 1: Meja / Tipe Pesanan di Kiri, Status Badge di Kanan */}
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-1.5 min-w-0">
                                                    <span className="px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider bg-slate-950/80 text-amber-300 border border-amber-500/30 shadow-sm shrink-0">
                                                        {order.table_number || (order.order_type === 'take_away' ? 'Take Away' : 'Dine In')}
                                                    </span>
                                                    {isNewOrder && (
                                                        <span className="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-rose-600 text-white shadow ring-1 ring-white/40 animate-bounce shrink-0">
                                                            NEW
                                                        </span>
                                                    )}
                                                </div>

                                                {/* Status Badge */}
                                                <span className={`px-2.5 py-1 rounded-lg font-black text-[11px] inline-flex items-center gap-1.5 shadow-sm shrink-0 ${
                                                    order.kitchen_status === 'pending'
                                                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30'
                                                        : order.kitchen_status === 'preparing'
                                                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                                        : order.kitchen_status === 'ready'
                                                        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                                                        : 'bg-slate-800 text-slate-300'
                                                }`}>
                                                    <span className={`w-1.5 h-1.5 rounded-full ${
                                                        order.kitchen_status === 'pending'
                                                            ? 'bg-rose-400'
                                                            : order.kitchen_status === 'preparing'
                                                            ? 'bg-amber-400 animate-pulse'
                                                            : order.kitchen_status === 'ready'
                                                            ? 'bg-emerald-400'
                                                            : 'bg-slate-400'
                                                    }`}></span>
                                                    <span>{statusTheme.label}</span>
                                                </span>
                                            </div>

                                            {/* Baris 2: No Order & Pelanggan di Kiri, Jam Pemesanan di Kanan */}
                                            <div className="flex items-center justify-between gap-2 text-xs border-t border-white/10 pt-1.5">
                                                <div className="min-w-0 flex items-center gap-1.5 truncate">
                                                    <span className="font-mono font-bold text-white text-xs shrink-0 tracking-tight">
                                                        #{order.order_number}
                                                    </span>
                                                    <span className="text-[11px] text-slate-300/80 truncate">
                                                        • {order.customer_name || 'Pelanggan Umum'}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1 text-[11px] font-mono font-bold text-slate-200 shrink-0">
                                                    <svg className="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <span>{new Date(order.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Order Special Notes */}
                                        {order.notes && (
                                            <div className="p-2.5 bg-amber-500/10 border-b border-amber-500/20 text-amber-300 text-xs flex items-start gap-1.5">
                                                <span className="font-bold shrink-0">Catatan:</span>
                                                <span>{order.notes}</span>
                                            </div>
                                        )}

                                        {/* Items Checklist with Per-Menu Thermal Print Button */}
                                        <div className="p-3.5 space-y-2 flex-1 max-h-72 overflow-y-auto">
                                            {order.items?.map(item => (
                                                <div
                                                    key={item.id}
                                                    className={`p-2 rounded-xl border flex items-center justify-between gap-2 transition-all ${
                                                        item.is_prepared
                                                            ? 'bg-slate-950/40 border-emerald-500/30 text-slate-400 opacity-75'
                                                            : 'bg-slate-950/80 border-slate-800 text-white hover:border-slate-700'
                                                    }`}>
                                                    {/* Left: Checkbox & Name */}
                                                    <div 
                                                        onClick={() => toggleItemChecklist(item.id, order.id)}
                                                        className="flex items-start gap-2.5 flex-1 cursor-pointer select-none">
                                                        <input
                                                            type="checkbox"
                                                            checked={Boolean(item.is_prepared)}
                                                            onChange={() => toggleItemChecklist(item.id, order.id)}
                                                            onClick={(e) => e.stopPropagation()}
                                                            className="mt-0.5 rounded border-slate-700 text-emerald-600 focus:ring-emerald-500 cursor-pointer w-4 h-4"
                                                        />
                                                        <div className="flex-1">
                                                            <div className="flex items-baseline gap-1.5">
                                                                <span className={`font-mono font-black text-xs ${item.is_prepared ? 'text-emerald-400 line-through' : 'text-amber-400'}`}>
                                                                    {item.quantity}x
                                                                </span>
                                                                <span className={`text-xs font-bold leading-tight ${item.is_prepared ? 'line-through text-slate-400' : 'text-slate-100'}`}>
                                                                    {item.product_name}
                                                                </span>
                                                            </div>
                                                            {item.notes && (
                                                                <div className="text-[10px] text-amber-300 font-medium mt-0.5 bg-amber-500/10 px-1.5 py-0.5 rounded inline-block">
                                                                    CATATAN: {item.notes}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Right: Thermal Print Button for EACH MENU ITEM */}
                                                    <div className="flex items-center gap-1 shrink-0">
                                                        <button
                                                            type="button"
                                                            onClick={(e) => handleDirectPrintItem(e, item, order)}
                                                            disabled={printingItemId === item.id}
                                                            title={`Cetak Tiket Menu: ${item.product_name} ke Thermal Printer`}
                                                            className="px-2 py-1 rounded-lg bg-slate-800 hover:bg-orange-600 hover:text-white text-slate-300 text-[10px] font-semibold transition-all flex items-center gap-1 border border-slate-700/50 shadow-sm">
                                                            {printingItemId === item.id ? (
                                                                <svg className="animate-spin w-3 h-3 text-orange-400" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                            ) : (
                                                                <svg className="w-3 h-3 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                            )}
                                                            <span>Cetak Menu</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Order Action Buttons */}
                                    <div className="p-3 border-t border-slate-800 bg-slate-950/60 flex items-center justify-between gap-2">
                                        {/* Print Full Kitchen Ticket */}
                                        <button
                                            type="button"
                                            onClick={() => handleDirectPrintOrder(order)}
                                            className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold flex items-center gap-1.5 transition-all"
                                            title="Cetak Tiket Dapur Lengkap (Semua Menu)">
                                            <svg className="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            <span className="hidden sm:inline">Cetak Tiket</span>
                                        </button>

                                        {order.kitchen_status === 'pending' && (
                                            <button
                                                type="button"
                                                disabled={updatingOrderId === order.id}
                                                onClick={() => updateOrderStatus(order.id, 'preparing')}
                                                className="flex-1 py-2 px-3 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs shadow-md shadow-amber-950 transition-all flex items-center justify-center gap-1.5">
                                                {updatingOrderId === order.id ? (
                                                    <svg className="animate-spin w-3.5 h-3.5 text-white" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                ) : (
                                                    <>
                                                        <span>Mulai Memasak</span>
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                                    </>
                                                )}
                                            </button>
                                        )}

                                        {order.kitchen_status === 'preparing' && (
                                            <button
                                                type="button"
                                                disabled={updatingOrderId === order.id}
                                                onClick={() => updateOrderStatus(order.id, 'ready')}
                                                className="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-950 transition-all flex items-center justify-center gap-1.5">
                                                {updatingOrderId === order.id ? (
                                                    <svg className="animate-spin w-3.5 h-3.5 text-white" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                ) : (
                                                    <>
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                                                        <span>Pesanan Siap</span>
                                                    </>
                                                )}
                                            </button>
                                        )}

                                        {order.kitchen_status === 'ready' && (
                                            <button
                                                type="button"
                                                disabled={updatingOrderId === order.id}
                                                onClick={() => updateOrderStatus(order.id, 'completed')}
                                                className="flex-1 py-2 px-3 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs transition-all flex items-center justify-center gap-1.5">
                                                {updatingOrderId === order.id ? (
                                                    <svg className="animate-spin w-3.5 h-3.5 text-white" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                ) : (
                                                    <>
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <span>Selesai / Diambil</span>
                                                    </>
                                                )}
                                            </button>
                                        )}

                                        {order.kitchen_status === 'completed' && (
                                            <span className="text-xs text-slate-500 font-semibold italic text-center w-full">
                                                Selesai pada {order.kitchen_completed_at ? new Date(order.kitchen_completed_at).toLocaleTimeString('id-ID') : '-'}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Kitchen Ticket Modal */}
            <ThermalReceiptModal
                isOpen={kitchenTicketModal.isOpen}
                onClose={() => setKitchenTicketModal({ isOpen: false, order: null, receiptData: null })}
                order={kitchenTicketModal.order}
                receiptData={kitchenTicketModal.receiptData}
            />

            {/* Instant Floating Toast Feedback */}
            {toastMessage && (
                <div className="fixed bottom-6 right-6 z-50 bg-slate-900 border border-emerald-500 text-emerald-300 px-4 py-2.5 rounded-xl shadow-2xl flex items-center gap-2 animate-in fade-in slide-in-from-bottom duration-200">
                    <svg className="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                    <span className="text-xs font-semibold">{toastMessage}</span>
                </div>
            )}
        </div>
    );
}
