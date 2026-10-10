import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';

// Food image presets for fast selection when adding/editing products
const FOOD_IMAGE_PRESETS = [
    { label: 'Nasi Goreng', url: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=400&q=80' },
    { label: 'Ayam Bakar', url: 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=400&q=80' },
    { label: 'Bebek Crispy', url: 'https://images.unsplash.com/photo-1518492104633-130d0cc84637?auto=format&fit=crop&w=400&q=80' },
    { label: 'Kwetiau / Mie', url: 'https://images.unsplash.com/photo-1552611052-33e04de081de?auto=format&fit=crop&w=400&q=80' },
    { label: 'Burger Sapi', url: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=400&q=80' },
    { label: 'Kopi Susu', url: 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?auto=format&fit=crop&w=400&q=80' },
    { label: 'Matcha / Tea', url: 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&w=400&q=80' },
    { label: 'Lemon Mint', url: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=400&q=80' },
    { label: 'French Fries', url: 'https://images.unsplash.com/photo-1576107232684-1279f3908594?auto=format&fit=crop&w=400&q=80' },
    { label: 'Dimsum', url: 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?auto=format&fit=crop&w=400&q=80' },
    { label: 'Dessert Cake', url: 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=400&q=80' },
    { label: 'Pisang Bakar', url: 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?auto=format&fit=crop&w=400&q=80' },
];

const CATEGORY_COLORS = [
    { label: 'Merah Coral', value: '#ef4444' },
    { label: 'Biru Samudera', value: '#3b82f6' },
    { label: 'Amber Oranye', value: '#f59e0b' },
    { label: 'Pink Rose', value: '#ec4899' },
    { label: 'Hijau Zamrud', value: '#10b981' },
    { label: 'Ungu Violet', value: '#8b5cf6' },
    { label: 'Teal Laut', value: '#14b8a6' },
];

const CATEGORY_ICONS = [
    { label: 'Utensils (Makan)', value: 'utensils' },
    { label: 'Coffee (Minum)', value: 'coffee' },
    { label: 'Cookie (Snack)', value: 'cookie' },
    { label: 'Ice Cream (Dessert)', value: 'ice-cream' },
    { label: 'Pizza (Fast Food)', value: 'pizza' },
    { label: 'Flame (Spesial)', value: 'flame' },
];

export default function CmsApp({ user, initialSettings, initialTab = 'dashboard' }) {
    const [activeTab, setActiveTab] = useState(initialTab); // 'dashboard' | 'products' | 'categories'

    // Analytics state
    const [period, setPeriod] = useState('7days');
    const [analyticsLoading, setAnalyticsLoading] = useState(true);
    const [analyticsData, setAnalyticsData] = useState(null);
    const [hoveredBar, setHoveredBar] = useState(null);

    // Products / Menu state
    const [products, setProducts] = useState([]);
    const [productsLoading, setProductsLoading] = useState(false);
    const [productSearch, setProductSearch] = useState('');
    const [productCategoryFilter, setProductCategoryFilter] = useState('all');
    const [productStatusFilter, setProductStatusFilter] = useState('all');
    const [productSortBy, setProductSortBy] = useState('name');
    const [productSortDir, setProductSortDir] = useState('asc');

    // Categories state
    const [categories, setCategories] = useState([]);
    const [categoriesLoading, setCategoriesLoading] = useState(false);

    // Toast feedback state
    const [toast, setToast] = useState(null);

    // Modals
    const [productModal, setProductModal] = useState({ isOpen: false, isEdit: false, data: null });
    const [categoryModal, setCategoryModal] = useState({ isOpen: false, isEdit: false, data: null });
    const [deleteConfirmModal, setDeleteConfirmModal] = useState({ isOpen: false, type: null, id: null, name: '' });
    const [stockAdjustModal, setStockAdjustModal] = useState({ isOpen: false, product: null, newStock: 0 });

    // Toast helper
    const showToast = (message, type = 'success') => {
        setToast({ message, type });
        setTimeout(() => setToast(null), 4000);
    };

    // Initial load
    useEffect(() => {
        fetchCategories();
        if (activeTab === 'dashboard') {
            fetchAnalytics(period);
        } else if (activeTab === 'products') {
            fetchProducts();
        }
    }, [activeTab]);

    useEffect(() => {
        if (activeTab === 'dashboard') {
            fetchAnalytics(period);
        }
    }, [period]);

    // ==========================================
    // DATA FETCHING
    // ==========================================

    const fetchCategories = async () => {
        setCategoriesLoading(true);
        try {
            const res = await axios.get('/api/cms/categories');
            if (res.data.success) {
                setCategories(res.data.categories);
            }
        } catch (e) {
            console.error('Failed fetching categories', e);
            showToast('Gagal memuat kategori menu.', 'error');
        } finally {
            setCategoriesLoading(false);
        }
    };

    const fetchProducts = async () => {
        setProductsLoading(true);
        try {
            const params = {
                search: productSearch,
                category_id: productCategoryFilter,
                status: productStatusFilter,
                sort_by: productSortBy,
                sort_dir: productSortDir,
            };
            const res = await axios.get('/api/cms/products', { params });
            if (res.data.success) {
                setProducts(res.data.products);
            }
        } catch (e) {
            console.error('Failed fetching products', e);
            showToast('Gagal memuat katalog menu.', 'error');
        } finally {
            setProductsLoading(false);
        }
    };

    const fetchAnalytics = async (selectedPeriod) => {
        setAnalyticsLoading(true);
        try {
            const res = await axios.get('/api/cms/analytics', { params: { period: selectedPeriod } });
            if (res.data.success) {
                setAnalyticsData(res.data);
            }
        } catch (e) {
            console.error('Failed fetching analytics', e);
            showToast('Gagal memuat analitik penjualan.', 'error');
        } finally {
            setAnalyticsLoading(false);
        }
    };

    // ==========================================
    // MENU / PRODUCT ACTIONS
    // ==========================================

    const handleSaveProduct = async (formData) => {
        try {
            if (productModal.isEdit && productModal.data?.id) {
                const res = await axios.put(`/api/cms/products/${productModal.data.id}`, formData);
                if (res.data.success) {
                    showToast(res.data.message);
                    setProductModal({ isOpen: false, isEdit: false, data: null });
                    fetchProducts();
                    fetchCategories();
                }
            } else {
                const res = await axios.post('/api/cms/products', formData);
                if (res.data.success) {
                    showToast(res.data.message);
                    setProductModal({ isOpen: false, isEdit: false, data: null });
                    fetchProducts();
                    fetchCategories();
                }
            }
        } catch (e) {
            const msg = e.response?.data?.message || 'Gagal menyimpan menu.';
            showToast(msg, 'error');
        }
    };

    const handleToggleProductStatus = async (id) => {
        try {
            const res = await axios.patch(`/api/cms/products/${id}/toggle-status`);
            if (res.data.success) {
                showToast(res.data.message);
                setProducts(prev => prev.map(p => p.id === id ? res.data.product : p));
            }
        } catch (e) {
            showToast('Gagal mengubah status menu.', 'error');
        }
    };

    const handleSaveStock = async () => {
        if (!stockAdjustModal.product) return;
        try {
            const res = await axios.patch(`/api/cms/products/${stockAdjustModal.product.id}/stock`, {
                stock: stockAdjustModal.newStock,
            });
            if (res.data.success) {
                showToast(res.data.message);
                setStockAdjustModal({ isOpen: false, product: null, newStock: 0 });
                fetchProducts();
            }
        } catch (e) {
            showToast('Gagal mengubah stok.', 'error');
        }
    };

    const handleDeleteProduct = async (id) => {
        try {
            const res = await axios.delete(`/api/cms/products/${id}`);
            if (res.data.success) {
                showToast(res.data.message);
                setDeleteConfirmModal({ isOpen: false, type: null, id: null, name: '' });
                fetchProducts();
                fetchCategories();
            }
        } catch (e) {
            showToast('Gagal menghapus menu.', 'error');
        }
    };

    // ==========================================
    // CATEGORY ACTIONS
    // ==========================================

    const handleSaveCategory = async (formData) => {
        try {
            if (categoryModal.isEdit && categoryModal.data?.id) {
                const res = await axios.put(`/api/cms/categories/${categoryModal.data.id}`, formData);
                if (res.data.success) {
                    showToast(res.data.message);
                    setCategoryModal({ isOpen: false, isEdit: false, data: null });
                    fetchCategories();
                }
            } else {
                const res = await axios.post('/api/cms/categories', formData);
                if (res.data.success) {
                    showToast(res.data.message);
                    setCategoryModal({ isOpen: false, isEdit: false, data: null });
                    fetchCategories();
                }
            }
        } catch (e) {
            const msg = e.response?.data?.message || 'Gagal menyimpan kategori.';
            showToast(msg, 'error');
        }
    };

    const handleDeleteCategory = async (id) => {
        try {
            const res = await axios.delete(`/api/cms/categories/${id}`);
            if (res.data.success) {
                showToast(res.data.message);
                setDeleteConfirmModal({ isOpen: false, type: null, id: null, name: '' });
                fetchCategories();
            }
        } catch (e) {
            const msg = e.response?.data?.message || 'Gagal menghapus kategori.';
            showToast(msg, 'error');
        }
    };

    // Filtered products count
    const activeProductsCount = useMemo(() => {
        return products.filter(p => p.is_available).length;
    }, [products]);

    return (
        <div className="space-y-6">
            {/* Toast Notification Notification Banner */}
            {toast && (
                <div className={`fixed top-20 right-6 z-50 px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-sm font-semibold border backdrop-blur-xl transition-all animate-fade-in ${
                    toast.type === 'error'
                        ? 'bg-rose-950/90 text-rose-200 border-rose-500/50 shadow-rose-950/50'
                        : 'bg-emerald-950/90 text-emerald-200 border-emerald-500/50 shadow-emerald-950/50'
                }`}>
                    <span>{toast.type === 'error' ? '⚠️' : '✓'}</span>
                    <span>{toast.message}</span>
                </div>
            )}

            {/* Top CMS Header & Quick System Switchers */}
            <div className="flex flex-wrap items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-800 shadow-xl">
                <div className="flex items-center gap-3.5">
                    <div className="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 via-purple-600 to-pink-500 p-0.5 shadow-lg shadow-indigo-950/60">
                        <div className="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                            <span className="text-indigo-400 font-extrabold text-2xl">Q</span>
                        </div>
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-black text-white">Qurais Kitchen Dashboard</h1>
                            <span className="px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                SUPERADMIN
                            </span>
                        </div>
                        <p className="text-xs text-slate-400">
                            Pusat Kendali Menu, Kategori & Analisis Performa Transaksi Realtime
                        </p>
                    </div>
                </div>

                {/* Quick Navigation to External Pos/Kds */}
                <div className="flex items-center gap-2 text-xs">
                    <a href="/pos" 
                       className="px-3 py-2 rounded-xl bg-rose-600/10 hover:bg-rose-600/20 border border-rose-500/30 text-rose-300 hover:text-white transition-all flex items-center gap-1.5 font-bold">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        Kasir POS
                    </a>
                    <a href="/kds" 
                       className="px-3 py-2 rounded-xl bg-amber-600/10 hover:bg-amber-600/20 border border-amber-500/30 text-amber-300 hover:text-white transition-all flex items-center gap-1.5 font-bold">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        Dapur KDS
                    </a>
                    <form method="POST" action="/cms/logout" className="inline">
                        <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')} />
                        <button type="submit" 
                                className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-rose-950 hover:text-rose-300 border border-slate-700 text-slate-300 transition-all font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Logout Dashboard
                        </button>
                    </form>
                </div>
            </div>

            {/* CMS Primary Navigation Tabs */}
            <div className="flex border-b border-slate-800 gap-2">
                <button
                    type="button"
                    onClick={() => setActiveTab('dashboard')}
                    className={`pb-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2 ${
                        activeTab === 'dashboard'
                            ? 'border-indigo-500 text-indigo-400'
                            : 'border-transparent text-slate-400 hover:text-white'
                    }`}>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Dashboard & Analitik Penjualan
                </button>
                <button
                    type="button"
                    onClick={() => { setActiveTab('products'); fetchProducts(); }}
                    className={`pb-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2 ${
                        activeTab === 'products'
                            ? 'border-indigo-500 text-indigo-400'
                            : 'border-transparent text-slate-400 hover:text-white'
                    }`}>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Kelola Menu (Katalog Produk)
                    <span className="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-800 text-slate-300 font-mono">
                        {products.length}
                    </span>
                </button>
                <button
                    type="button"
                    onClick={() => { setActiveTab('categories'); fetchCategories(); }}
                    className={`pb-3 px-4 font-bold text-sm border-b-2 transition-all flex items-center gap-2 ${
                        activeTab === 'categories'
                            ? 'border-indigo-500 text-indigo-400'
                            : 'border-transparent text-slate-400 hover:text-white'
                    }`}>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Kelola Kategori Menu
                    <span className="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-800 text-slate-300 font-mono">
                        {categories.length}
                    </span>
                </button>
            </div>

            {/* TAB 1: ANALYTICS DASHBOARD */}
            {activeTab === 'dashboard' && (
                <div className="space-y-6">
                    {/* Period Switcher */}
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-white">Analitik Transaksi & Penjualan Qurais Kitchen</h2>
                            <p className="text-xs text-slate-400">Ringkasan omset, margin keuntungan, dan performa menu terlaris</p>
                        </div>
                        <div className="flex rounded-xl bg-slate-900 p-1 border border-slate-800 text-xs font-bold">
                            {[
                                { key: 'today', label: 'Hari Ini' },
                                { key: 'yesterday', label: 'Kemarin' },
                                { key: '7days', label: '7 Hari' },
                                { key: '30days', label: '30 Hari' },
                                { key: 'this_month', label: 'Bulan Ini' },
                                { key: 'all', label: 'Semua Waktu' },
                            ].map(p => (
                                <button
                                    key={p.key}
                                    type="button"
                                    onClick={() => setPeriod(p.key)}
                                    className={`px-3 py-1.5 rounded-lg transition-all ${period === p.key ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white'}`}>
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {analyticsLoading && !analyticsData ? (
                        <div className="h-64 flex items-center justify-center text-slate-400 text-sm">
                            <svg className="animate-spin h-6 w-6 text-indigo-500 mr-2" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Memuat Analitik Transaksi...
                        </div>
                    ) : (
                        <>
                            {/* KPI Cards Row */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3.5">
                                {/* Gross Revenue */}
                                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-indigo-950/40 border border-slate-800 shadow-xl relative overflow-hidden">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                        Total Penjualan Kotor
                                    </span>
                                    <div className="text-xl font-black font-mono text-white">
                                        Rp {Number(analyticsData?.kpis?.total_revenue || 0).toLocaleString('id-ID')}
                                    </div>
                                    <span className="text-[10px] text-emerald-400 font-semibold mt-1 block">
                                        ✓ Gross Sales
                                    </span>
                                </div>

                                {/* Estimated Net Profit */}
                                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-950/40 border border-slate-800 shadow-xl relative overflow-hidden">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                        Estimasi Laba Bersih
                                    </span>
                                    <div className="text-xl font-black font-mono text-emerald-400">
                                        Rp {Number(analyticsData?.kpis?.net_profit || 0).toLocaleString('id-ID')}
                                    </div>
                                    <span className="text-[10px] text-emerald-300 font-semibold mt-1 block">
                                        Margin: {analyticsData?.kpis?.profit_margin_pct || 0}%
                                    </span>
                                </div>

                                {/* Total Orders */}
                                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                        Total Transaksi
                                    </span>
                                    <div className="text-xl font-black font-mono text-white">
                                        {analyticsData?.kpis?.total_orders || 0}
                                    </div>
                                    <span className="text-[10px] text-slate-400 mt-1 block">
                                        Order berhasil
                                    </span>
                                </div>

                                {/* Average Order Value (AOV) */}
                                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                        Rata-Rata Order (AOV)
                                    </span>
                                    <div className="text-xl font-black font-mono text-amber-400">
                                        Rp {Number(analyticsData?.kpis?.average_order_value || 0).toLocaleString('id-ID')}
                                    </div>
                                    <span className="text-[10px] text-slate-400 mt-1 block">
                                        Per transaksi
                                    </span>
                                </div>

                                {/* Total Items Sold */}
                                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 shadow-xl">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                        Porsi Terjual
                                    </span>
                                    <div className="text-xl font-black font-mono text-white">
                                        {analyticsData?.kpis?.total_items_sold || 0}
                                    </div>
                                    <span className="text-[10px] text-slate-400 mt-1 block">
                                        Item makanan & minuman
                                    </span>
                                </div>

                                {/* Void / Refund */}
                                <div className="p-4 rounded-2xl bg-gradient-to-br from-slate-900 to-rose-950/30 border border-slate-800 shadow-xl">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                        Pesanan Void / Batal
                                    </span>
                                    <div className="text-xl font-black font-mono text-rose-400">
                                        {analyticsData?.kpis?.void_orders_count || 0}
                                    </div>
                                    <span className="text-[10px] text-rose-300 mt-1 block">
                                        Rp {Number(analyticsData?.kpis?.void_orders_amount || 0).toLocaleString('id-ID')}
                                    </span>
                                </div>
                            </div>

                            {/* Low Stock Banner (If any) */}
                            {analyticsData?.low_stock_products?.length > 0 && (
                                <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
                                    <div className="p-2 rounded-xl bg-amber-500/20 text-amber-300 font-bold text-sm">
                                        ⚠️
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between">
                                            <h4 className="text-xs font-bold text-amber-200">
                                                Peringatan Stok Menipis ({analyticsData.low_stock_products.length} Menu)
                                            </h4>
                                            <button
                                                type="button"
                                                onClick={() => { setActiveTab('products'); fetchProducts(); }}
                                                className="text-[11px] font-bold text-amber-300 hover:text-white underline">
                                                Kelola di Katalog &rarr;
                                            </button>
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {analyticsData.low_stock_products.map((item, idx) => (
                                                <span key={idx} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-900/80 border border-amber-500/30 text-[11px] text-white">
                                                    <span>{item.name}</span>
                                                    <span className="px-1.5 py-0.2 rounded font-mono font-bold bg-amber-500/20 text-amber-300">
                                                        Sisa {item.stock}
                                                    </span>
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Chart Row 1: Daily Revenue & Category Breakdown */}
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                {/* Daily Revenue Chart */}
                                <div className="lg:col-span-2 p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-bold text-white">Tren Pendapatan Harian</h3>
                                            <p className="text-[11px] text-slate-400">Fluktuasi omset harian Qurais Kitchen</p>
                                        </div>
                                    </div>

                                    {/* Bar Chart */}
                                    <div className="h-64 flex items-end gap-2 pt-8 pb-2 px-2 border-b border-slate-800">
                                        {(() => {
                                            const daily = analyticsData?.daily_data || [];
                                            const maxRev = Math.max(1, ...daily.map(d => d.revenue));
                                            return daily.map((day, idx) => {
                                                const heightPct = Math.max(8, Math.round((day.revenue / maxRev) * 100));
                                                return (
                                                    <div
                                                        key={idx}
                                                        onMouseEnter={() => setHoveredBar(day)}
                                                        onMouseLeave={() => setHoveredBar(null)}
                                                        className="flex-1 flex flex-col items-center h-full justify-end group cursor-pointer relative">
                                                        {hoveredBar?.date === day.date && (
                                                            <div className="absolute -top-12 z-20 px-2 py-1 rounded bg-slate-950 text-white border border-slate-700 text-[10px] whitespace-nowrap shadow-xl">
                                                                <strong>{day.label}:</strong> Rp {Number(day.revenue).toLocaleString('id-ID')} ({day.orders} order)
                                                            </div>
                                                        )}
                                                        <div
                                                            style={{ height: `${heightPct}%` }}
                                                            className="w-full max-w-[40px] bg-gradient-to-t from-indigo-700 to-purple-500 rounded-t-lg group-hover:from-indigo-500 group-hover:to-pink-400 transition-all shadow-md"></div>
                                                        <span className="text-[10px] font-mono text-slate-400 mt-2 truncate w-full text-center">
                                                            {day.label}
                                                        </span>
                                                    </div>
                                                );
                                            });
                                        })()}
                                    </div>
                                </div>

                                {/* Category Sales Breakdown */}
                                <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                                    <div>
                                        <h3 className="text-sm font-bold text-white">Distribusi Penjualan per Kategori</h3>
                                        <p className="text-[11px] text-slate-400">Kontribusi omset makanan vs minuman</p>
                                    </div>

                                    <div className="space-y-3.5">
                                        {(analyticsData?.category_breakdown || []).map((cat, idx) => (
                                            <div key={idx} className="space-y-1.5 p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="font-bold text-white flex items-center gap-1.5">
                                                        <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: cat.color }}></span>
                                                        {cat.name}
                                                    </span>
                                                    <span className="font-mono text-indigo-300 font-bold">
                                                        {cat.percentage}% ({cat.total_qty} porsi)
                                                    </span>
                                                </div>
                                                <div className="w-full h-2 rounded-full bg-slate-900 overflow-hidden">
                                                    <div
                                                        style={{ width: `${cat.percentage}%`, backgroundColor: cat.color }}
                                                        className="h-full rounded-full transition-all"
                                                    />
                                                </div>
                                                <div className="text-[10px] text-slate-400 text-right font-mono">
                                                    Rp {Number(cat.total_sales).toLocaleString('id-ID')}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Chart Row 2: Trending & Best Selling Menus Table */}
                            <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-sm font-bold text-white">🔥 Menu Trending & Paling Laris (Best Sellers)</h3>
                                        <p className="text-[11px] text-slate-400">Analisis margin keuntungan, volume porsi, dan estimasi laba per menu</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => { setActiveTab('products'); fetchProducts(); }}
                                        className="text-xs font-bold text-indigo-400 hover:text-indigo-300">
                                        Lihat Semua Menu &rarr;
                                    </button>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                                <th className="py-2.5 px-3">Ranking</th>
                                                <th className="py-2.5 px-3">Nama Menu</th>
                                                <th className="py-2.5 px-3 text-right">Porsi Terjual</th>
                                                <th className="py-2.5 px-3 text-right">Total Omset</th>
                                                <th className="py-2.5 px-3 text-right">Estimasi Laba</th>
                                                <th className="py-2.5 px-3 text-right">Profit Margin</th>
                                                <th className="py-2.5 px-3 text-center">Status Stok</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-800/60 font-mono">
                                            {(analyticsData?.top_products || []).length === 0 ? (
                                                <tr>
                                                    <td colSpan={7} className="py-6 text-center text-slate-500 font-sans">
                                                        Belum ada transaksi penjualan pada periode ini.
                                                    </td>
                                                </tr>
                                            ) : (
                                                analyticsData.top_products.map((item, idx) => (
                                                    <tr key={idx} className="hover:bg-slate-800/40 transition-colors">
                                                        <td className="py-3 px-3 font-sans">
                                                            <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full font-bold text-xs ${
                                                                idx === 0 ? 'bg-amber-400 text-slate-950' : idx === 1 ? 'bg-slate-300 text-slate-950' : idx === 2 ? 'bg-amber-700 text-white' : 'bg-slate-800 text-slate-400'
                                                            }`}>
                                                                {idx + 1}
                                                            </span>
                                                        </td>
                                                        <td className="py-3 px-3 font-sans font-bold text-white">
                                                            {item.product_name}
                                                        </td>
                                                        <td className="py-3 px-3 text-right font-extrabold text-indigo-300">
                                                            {item.total_qty}x
                                                        </td>
                                                        <td className="py-3 px-3 text-right text-slate-200">
                                                            Rp {Number(item.total_sales).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="py-3 px-3 text-right text-emerald-400 font-bold">
                                                            Rp {Number(item.profit).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="py-3 px-3 text-right font-bold text-amber-300">
                                                            {item.margin_pct}%
                                                        </td>
                                                        <td className="py-3 px-3 text-center font-sans">
                                                            {item.stock !== null ? (
                                                                <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                                    item.stock <= 10 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                                                                }`}>
                                                                    {item.stock} porsi
                                                                </span>
                                                            ) : (
                                                                <span className="text-slate-500">-</span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Chart Row 3: Recent Transactions Feed */}
                            <div className="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-sm font-bold text-white">10 Transaksi Terakhir (Live Feed)</h3>
                                        <p className="text-[11px] text-slate-400">Aktivitas kasir dan status pesanan Qurais Kitchen</p>
                                    </div>
                                    <a href="/transactions" target="_blank" className="text-xs font-bold text-indigo-400 hover:text-indigo-300">
                                        Buka Dashboard Transaksi Lengkap &rarr;
                                    </a>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                                <th className="py-2.5 px-3">No. Order</th>
                                                <th className="py-2.5 px-3">Pelanggan</th>
                                                <th className="py-2.5 px-3">Tipe / Meja</th>
                                                <th className="py-2.5 px-3">Metode Bayar</th>
                                                <th className="py-2.5 px-3 text-right">Total Bayar</th>
                                                <th className="py-2.5 px-3 text-center">Dapur</th>
                                                <th className="py-2.5 px-3 text-right">Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-800/60 font-mono">
                                            {(analyticsData?.recent_transactions || []).length === 0 ? (
                                                <tr>
                                                    <td colSpan={7} className="py-6 text-center text-slate-500 font-sans">
                                                        Belum ada catatan transaksi.
                                                    </td>
                                                </tr>
                                            ) : (
                                                analyticsData.recent_transactions.map((tx, idx) => (
                                                    <tr key={idx} className="hover:bg-slate-800/40 transition-colors">
                                                        <td className="py-3 px-3 font-bold text-indigo-400">
                                                            {tx.order_number}
                                                        </td>
                                                        <td className="py-3 px-3 font-sans text-white">
                                                            {tx.customer_name}
                                                        </td>
                                                        <td className="py-3 px-3 font-sans text-slate-300">
                                                            {tx.order_type.toUpperCase()} {tx.table_number ? `(${tx.table_number})` : ''}
                                                        </td>
                                                        <td className="py-3 px-3 font-sans uppercase font-bold text-slate-300">
                                                            {tx.payment_method}
                                                        </td>
                                                        <td className="py-3 px-3 text-right font-bold text-emerald-400">
                                                            Rp {Number(tx.total_amount).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="py-3 px-3 text-center font-sans">
                                                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                                tx.kitchen_status === 'completed'
                                                                    ? 'bg-emerald-500/20 text-emerald-300'
                                                                    : tx.kitchen_status === 'ready'
                                                                    ? 'bg-blue-500/20 text-blue-300'
                                                                    : 'bg-amber-500/20 text-amber-300'
                                                            }`}>
                                                                {tx.kitchen_status.toUpperCase()}
                                                            </span>
                                                        </td>
                                                        <td className="py-3 px-3 text-right text-slate-400 text-[11px]">
                                                            {new Date(tx.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* TAB 2: PRODUCT / MENU MANAGEMENT */}
            {activeTab === 'products' && (
                <div className="space-y-6">
                    {/* Header Controls */}
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-white">Kelola Katalog Menu Qurais Kitchen</h2>
                            <p className="text-xs text-slate-400">
                                Total: {products.length} menu ({activeProductsCount} aktif, {products.length - activeProductsCount} nonaktif)
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setProductModal({ isOpen: true, isEdit: false, data: null })}
                            className="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-lg shadow-indigo-950/50 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah Menu Baru
                        </button>
                    </div>

                    {/* Filter & Search Bar */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {/* Search */}
                        <div className="relative">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input
                                type="text"
                                placeholder="Cari nama menu / SKU..."
                                value={productSearch}
                                onChange={(e) => setProductSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && fetchProducts()}
                                className="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        {/* Category Filter */}
                        <select
                            value={productCategoryFilter}
                            onChange={(e) => { setProductCategoryFilter(e.target.value); }}
                            className="px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="all">Semua Kategori</option>
                            {categories.map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>

                        {/* Status Filter */}
                        <select
                            value={productStatusFilter}
                            onChange={(e) => { setProductStatusFilter(e.target.value); }}
                            className="px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                            <option value="all">Semua Status</option>
                            <option value="available">Aktif (Tersedia)</option>
                            <option value="unavailable">Nonaktif (Habis)</option>
                        </select>

                        {/* Apply Filter Button */}
                        <button
                            type="button"
                            onClick={fetchProducts}
                            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl transition-all">
                            Filter Menu
                        </button>
                    </div>

                    {/* Products Grid */}
                    {productsLoading ? (
                        <div className="h-64 flex items-center justify-center text-slate-400 text-sm">
                            <svg className="animate-spin h-6 w-6 text-indigo-500 mr-2" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Memuat Katalog Menu...
                        </div>
                    ) : products.length === 0 ? (
                        <div className="p-12 text-center rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3">
                            <p className="text-slate-400 text-sm">Tidak ada menu yang sesuai dengan filter pencarian.</p>
                            <button
                                type="button"
                                onClick={() => { setProductSearch(''); setProductCategoryFilter('all'); setProductStatusFilter('all'); fetchProducts(); }}
                                className="px-3 py-1.5 rounded-lg bg-indigo-600/20 text-indigo-300 text-xs font-bold border border-indigo-500/30">
                                Reset Filter
                            </button>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            {products.map(prod => {
                                const marginPct = prod.price > 0 && prod.cost_price > 0
                                    ? Math.round(((prod.price - prod.cost_price) / prod.price) * 100)
                                    : 0;
                                return (
                                    <div
                                        key={prod.id}
                                        className={`p-3.5 rounded-2xl bg-slate-900/80 border transition-all flex flex-col justify-between shadow-lg ${
                                            prod.is_available ? 'border-slate-800 hover:border-indigo-500/50' : 'border-rose-950/60 opacity-75'
                                        }`}>
                                        <div>
                                            {/* Image container */}
                                            <div className="relative aspect-video w-full rounded-xl overflow-hidden bg-slate-950 mb-3">
                                                {prod.image_url ? (
                                                    <img src={prod.image_url} alt={prod.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center text-slate-700 text-xs font-bold">
                                                        Qurais Kitchen
                                                    </div>
                                                )}
                                                <span className="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-950/80 backdrop-blur-md text-slate-300 border border-slate-800">
                                                    {prod.category?.name || 'Kategori'}
                                                </span>
                                                <span className={`absolute top-2 right-2 px-1.5 py-0.5 rounded text-[10px] font-bold ${
                                                    prod.is_available ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30'
                                                }`}>
                                                    {prod.is_available ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </div>

                                            {/* Details */}
                                            <div className="space-y-1">
                                                <div className="flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                                    <span>{prod.sku}</span>
                                                    <span className={`font-bold ${prod.stock <= 10 ? 'text-amber-400' : 'text-slate-300'}`}>
                                                        Stok: {prod.stock}
                                                    </span>
                                                </div>
                                                <h4 className="text-sm font-bold text-white line-clamp-1">{prod.name}</h4>
                                                <p className="text-[11px] text-slate-400 line-clamp-2 min-h-[32px]">
                                                    {prod.description || 'Tidak ada deskripsi.'}
                                                </p>
                                            </div>

                                            {/* Pricing & Profit */}
                                            <div className="mt-3 pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-xs">
                                                <div>
                                                    <span className="text-[10px] text-slate-400 block">Harga Jual</span>
                                                    <span className="font-extrabold text-white font-mono">
                                                        Rp {Number(prod.price).toLocaleString('id-ID')}
                                                    </span>
                                                </div>
                                                <div className="text-right">
                                                    <span className="text-[10px] text-slate-400 block">Modal / Margin</span>
                                                    <span className="text-[11px] font-mono text-emerald-400 font-semibold">
                                                        +{marginPct}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Actions */}
                                        <div className="mt-4 pt-3 border-t border-slate-800/80 flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => handleToggleProductStatus(prod.id)}
                                                title={prod.is_available ? 'Nonaktifkan Menu' : 'Aktifkan Menu'}
                                                className={`flex-1 py-1.5 rounded-lg text-[11px] font-bold transition-all ${
                                                    prod.is_available
                                                        ? 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                                                        : 'bg-emerald-600/20 text-emerald-300 border border-emerald-500/30'
                                                }`}>
                                                {prod.is_available ? 'Off' : 'On'}
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => setStockAdjustModal({ isOpen: true, product: prod, newStock: prod.stock })}
                                                title="Ubah Stok"
                                                className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-bold">
                                                Stok
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => setProductModal({ isOpen: true, isEdit: true, data: prod })}
                                                title="Edit Menu"
                                                className="px-2.5 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 text-[11px] font-bold border border-indigo-500/30">
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => setDeleteConfirmModal({ isOpen: true, type: 'product', id: prod.id, name: prod.name })}
                                                title="Hapus Menu"
                                                className="px-2 py-1.5 rounded-lg bg-rose-600/10 hover:bg-rose-600/30 text-rose-400 text-[11px] font-bold border border-rose-500/20">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* TAB 3: CATEGORY MANAGEMENT */}
            {activeTab === 'categories' && (
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-white">Kelola Kategori Menu Qurais Kitchen</h2>
                            <p className="text-xs text-slate-400">Atur kategori, ikon, warna, dan urutan tampilan pada POS kasir</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setCategoryModal({ isOpen: true, isEdit: false, data: null })}
                            className="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-lg shadow-indigo-950/50 flex items-center gap-2">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah Kategori Baru
                        </button>
                    </div>

                    {categoriesLoading ? (
                        <div className="h-64 flex items-center justify-center text-slate-400 text-sm">
                            <svg className="animate-spin h-6 w-6 text-indigo-500 mr-2" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Memuat Kategori...
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {categories.map(cat => (
                                <div key={cat.id} className="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div
                                                style={{ backgroundColor: `${cat.color}25`, borderColor: `${cat.color}60` }}
                                                className="w-10 h-10 rounded-xl border flex items-center justify-center text-lg">
                                                <span style={{ color: cat.color }}>★</span>
                                            </div>
                                            <div>
                                                <h4 className="text-sm font-bold text-white">{cat.name}</h4>
                                                <span className="text-[10px] text-slate-500 font-mono">slug: {cat.slug}</span>
                                            </div>
                                        </div>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                            cat.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-400'
                                        }`}>
                                            {cat.is_active ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </div>

                                    <div className="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between text-xs">
                                        <span className="text-slate-400">Total Menu Terkait:</span>
                                        <span className="font-bold text-white font-mono">
                                            {cat.total_products_count || 0} menu
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between text-xs pt-1 border-t border-slate-800">
                                        <span className="text-[11px] text-slate-400">Urutan: #{cat.sort_order}</span>
                                        <div className="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => setCategoryModal({ isOpen: true, isEdit: true, data: cat })}
                                                className="px-2.5 py-1 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 font-bold border border-indigo-500/30 text-xs">
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setDeleteConfirmModal({ isOpen: true, type: 'category', id: cat.id, name: cat.name })}
                                                className="px-2 py-1 rounded-lg bg-rose-600/10 hover:bg-rose-600/30 text-rose-400 font-bold border border-rose-500/20 text-xs">
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* PRODUCT MODAL (CREATE / EDIT) */}
            {productModal.isOpen && (
                <ProductFormModal
                    isOpen={productModal.isOpen}
                    isEdit={productModal.isEdit}
                    initialData={productModal.data}
                    categories={categories}
                    onClose={() => setProductModal({ isOpen: false, isEdit: false, data: null })}
                    onSave={handleSaveProduct}
                />
            )}

            {/* CATEGORY MODAL (CREATE / EDIT) */}
            {categoryModal.isOpen && (
                <CategoryFormModal
                    isOpen={categoryModal.isOpen}
                    isEdit={categoryModal.isEdit}
                    initialData={categoryModal.data}
                    onClose={() => setCategoryModal({ isOpen: false, isEdit: false, data: null })}
                    onSave={handleSaveCategory}
                />
            )}

            {/* QUICK STOCK ADJUST MODAL */}
            {stockAdjustModal.isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
                    <div className="w-full max-w-sm rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-5 space-y-4">
                        <h3 className="text-sm font-bold text-white">
                            Sesuaikan Stok: {stockAdjustModal.product?.name}
                        </h3>
                        <div>
                            <label className="block text-xs text-slate-400 mb-1">Jumlah Stok Saat Ini</label>
                            <input
                                type="number"
                                min="0"
                                value={stockAdjustModal.newStock}
                                onChange={(e) => setStockAdjustModal(prev => ({ ...prev, newStock: Math.max(0, parseInt(e.target.value) || 0) }))}
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-white font-mono font-bold text-base focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button
                                type="button"
                                onClick={() => setStockAdjustModal({ isOpen: false, product: null, newStock: 0 })}
                                className="px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700">
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={handleSaveStock}
                                className="px-4 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500">
                                Simpan Stok
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* DELETE CONFIRMATION MODAL */}
            {deleteConfirmModal.isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
                    <div className="w-full max-w-md rounded-2xl bg-slate-900 border border-rose-950/80 shadow-2xl p-6 space-y-4">
                        <div className="w-12 h-12 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center text-2xl mx-auto">
                            ⚠️
                        </div>
                        <div className="text-center">
                            <h3 className="text-base font-bold text-white">Konfirmasi Hapus</h3>
                            <p className="text-xs text-slate-400 mt-1">
                                Apakah Anda yakin ingin menghapus {deleteConfirmModal.type === 'category' ? 'kategori' : 'menu'} <strong>"{deleteConfirmModal.name}"</strong>?
                            </p>
                        </div>
                        <div className="flex gap-2.5 justify-center pt-2">
                            <button
                                type="button"
                                onClick={() => setDeleteConfirmModal({ isOpen: false, type: null, id: null, name: '' })}
                                className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700">
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    if (deleteConfirmModal.type === 'category') {
                                        handleDeleteCategory(deleteConfirmModal.id);
                                    } else {
                                        handleDeleteProduct(deleteConfirmModal.id);
                                    }
                                }}
                                className="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-500 shadow-lg shadow-rose-950/50">
                                Ya, Hapus Sekarang
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// =========================================================================
// SUBCOMPONENT: PRODUCT / MENU FORM MODAL
// =========================================================================
function ProductFormModal({ isOpen, isEdit, initialData, categories, onClose, onSave }) {
    const [name, setName] = useState(initialData?.name || '');
    const [sku, setSku] = useState(initialData?.sku || '');
    const [categoryId, setCategoryId] = useState(initialData?.category_id || (categories[0]?.id || ''));
    const [price, setPrice] = useState(initialData?.price || 0);
    const [costPrice, setCostPrice] = useState(initialData?.cost_price || 0);
    const [stock, setStock] = useState(initialData?.stock ?? 100);
    const [imageUrl, setImageUrl] = useState(initialData?.image_url || '');
    const [description, setDescription] = useState(initialData?.description || '');
    const [isAvailable, setIsAvailable] = useState(initialData?.is_available ?? true);

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave({
            name,
            sku,
            category_id: categoryId,
            price: parseFloat(price) || 0,
            cost_price: parseFloat(costPrice) || 0,
            stock: parseInt(stock) || 0,
            image_url: imageUrl,
            description,
            is_available: isAvailable,
        });
    };

    const handleGenerateSku = () => {
        const cat = categories.find(c => String(c.id) === String(categoryId));
        const prefix = cat ? cat.name.substring(0, 3).toUpperCase() : 'MNU';
        const rnd = Math.floor(100 + Math.random() * 900);
        setSku(`${prefix}-${rnd}`);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto animate-fade-in">
            <div className="w-full max-w-2xl rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-6 space-y-5 my-8">
                <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 className="text-base font-bold text-white">
                        {isEdit ? `Edit Menu: ${initialData?.name}` : 'Tambah Menu Baru ke Qurais Kitchen'}
                    </h3>
                    <button type="button" onClick={onClose} className="text-slate-400 hover:text-white text-lg">
                        ✕
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {/* Name */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Nama Menu</label>
                            <input
                                type="text"
                                required
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Contoh: Nasi Goreng Spesial"
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        {/* Category */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Kategori Menu</label>
                            <select
                                required
                                value={categoryId}
                                onChange={(e) => setCategoryId(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                                {categories.map(c => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* SKU */}
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="block text-xs font-semibold text-slate-300">Kode SKU</label>
                                <button
                                    type="button"
                                    onClick={handleGenerateSku}
                                    className="text-[10px] text-indigo-400 hover:text-indigo-300">
                                    Auto Generate
                                </button>
                            </div>
                            <input
                                type="text"
                                value={sku}
                                onChange={(e) => setSku(e.target.value)}
                                placeholder="Contoh: FOD-001"
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        {/* Stock */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Jumlah Stok</label>
                            <input
                                type="number"
                                min="0"
                                required
                                value={stock}
                                onChange={(e) => setStock(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        {/* Selling Price */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Harga Jual (Rp)</label>
                            <input
                                type="number"
                                min="0"
                                required
                                value={price}
                                onChange={(e) => setPrice(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono font-bold focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        {/* Cost Price */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Harga Modal / HPP (Rp)</label>
                            <input
                                type="number"
                                min="0"
                                value={costPrice}
                                onChange={(e) => setCostPrice(e.target.value)}
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    {/* Image URL & Preset Pickers */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">URL Foto Menu</label>
                        <input
                            type="text"
                            value={imageUrl}
                            onChange={(e) => setImageUrl(e.target.value)}
                            placeholder="https://images.unsplash.com/..."
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 mb-2"
                        />

                        {/* Presets */}
                        <div className="space-y-1">
                            <span className="text-[10px] text-slate-400 block font-semibold">Atau pilih foto cepat dari template kami:</span>
                            <div className="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                                {FOOD_IMAGE_PRESETS.map((p, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        onClick={() => setImageUrl(p.url)}
                                        className="px-2 py-0.5 rounded-lg bg-slate-950 border border-slate-800 text-[10px] text-slate-300 hover:text-white hover:border-indigo-500/50">
                                        {p.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Description */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">Deskripsi Singkat</label>
                        <textarea
                            rows="2"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="Rincian rasa, bumbu, atau porsi..."
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    {/* Is Available */}
                    <div className="flex items-center gap-2 pt-1">
                        <label className="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                            <input
                                type="checkbox"
                                checked={isAvailable}
                                onChange={(e) => setIsAvailable(e.target.checked)}
                                className="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Tersedia untuk dijual di Kasir POS (Status Aktif)</span>
                        </label>
                    </div>

                    {/* Buttons */}
                    <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700">
                            Batal
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-950/50">
                            {isEdit ? 'Simpan Perubahan' : 'Tambahkan Menu'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// =========================================================================
// SUBCOMPONENT: CATEGORY FORM MODAL
// =========================================================================
function CategoryFormModal({ isOpen, isEdit, initialData, onClose, onSave }) {
    const [name, setName] = useState(initialData?.name || '');
    const [slug, setSlug] = useState(initialData?.slug || '');
    const [icon, setIcon] = useState(initialData?.icon || 'utensils');
    const [color, setColor] = useState(initialData?.color || '#ef4444');
    const [sortOrder, setSortOrder] = useState(initialData?.sort_order ?? 1);
    const [isActive, setIsActive] = useState(initialData?.is_active ?? true);

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave({
            name,
            slug,
            icon,
            color,
            sort_order: parseInt(sortOrder) || 1,
            is_active: isActive,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
            <div className="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-6 space-y-5">
                <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 className="text-base font-bold text-white">
                        {isEdit ? `Edit Kategori: ${initialData?.name}` : 'Tambah Kategori Menu Baru'}
                    </h3>
                    <button type="button" onClick={onClose} className="text-slate-400 hover:text-white text-lg">
                        ✕
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">Nama Kategori</label>
                        <input
                            type="text"
                            required
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Contoh: Paket Hemat"
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">Slug URL</label>
                        <input
                            type="text"
                            value={slug}
                            onChange={(e) => setSlug(e.target.value)}
                            placeholder="Biarkan kosong untuk auto-generate"
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    {/* Color Picker Palette */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1.5">Warna Aksen</label>
                        <div className="flex flex-wrap gap-2">
                            {CATEGORY_COLORS.map(c => (
                                <button
                                    key={c.value}
                                    type="button"
                                    onClick={() => setColor(c.value)}
                                    style={{ backgroundColor: c.value }}
                                    className={`w-7 h-7 rounded-xl transition-transform ${color === c.value ? 'scale-125 ring-2 ring-white ring-offset-2 ring-offset-slate-900' : 'opacity-80 hover:opacity-100'}`}
                                    title={c.label}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Icon Select */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">Ikon Kategori</label>
                        <select
                            value={icon}
                            onChange={(e) => setIcon(e.target.value)}
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                            {CATEGORY_ICONS.map(i => (
                                <option key={i.value} value={i.value}>{i.label}</option>
                            ))}
                        </select>
                    </div>

                    {/* Sort Order */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">Urutan Tampil (Sort Order)</label>
                        <input
                            type="number"
                            min="1"
                            value={sortOrder}
                            onChange={(e) => setSortOrder(e.target.value)}
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    {/* Is Active */}
                    <div className="flex items-center gap-2 pt-1">
                        <label className="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                            <input
                                type="checkbox"
                                checked={isActive}
                                onChange={(e) => setIsActive(e.target.checked)}
                                className="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Tampilkan Kategori di Menu POS</span>
                        </label>
                    </div>

                    {/* Buttons */}
                    <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700">
                            Batal
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-950/50">
                            {isEdit ? 'Simpan Perubahan' : 'Tambahkan Kategori'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
