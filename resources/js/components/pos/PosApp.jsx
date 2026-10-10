import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import confetti from 'canvas-confetti';
import ThermalReceiptModal from '../ThermalReceiptModal';
import PrinterSettingsModal from '../PrinterSettingsModal';
import { playCashierSuccessDing } from '../../utils/audio';
import { printReceiptWindow } from '../../utils/thermalPrinter';

export default function PosApp({ user, initialSettings }) {
    const [categories, setCategories] = useState([]);
    const [products, setProducts] = useState([]);
    const [selectedCategory, setSelectedCategory] = useState(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [loading, setLoading] = useState(true);

    // Cart State
    const [cart, setCart] = useState([]);
    const [customerName, setCustomerName] = useState('Pelanggan Umum');
    const [tableNumber, setTableNumber] = useState('Meja 01');
    const [orderType, setOrderType] = useState('dine_in'); // 'dine_in' | 'take_away' | 'delivery'
    const [discountAmount, setDiscountAmount] = useState(0);
    const [taxRate, setTaxRate] = useState(10.00); // 10% PB1
    const [orderNotes, setOrderNotes] = useState('');

    // Modal States
    const [isCheckoutOpen, setIsCheckoutOpen] = useState(false);
    const [isReceiptOpen, setIsReceiptOpen] = useState(false);
    const [isPrinterSettingsOpen, setIsPrinterSettingsOpen] = useState(false);
    const [printerSettings, setPrinterSettings] = useState(initialSettings || {});
    const [lastCompletedOrder, setLastCompletedOrder] = useState(null);
    const [lastReceiptData, setLastReceiptData] = useState(null);

    // Checkout Form State
    const [paymentMethod, setPaymentMethod] = useState('cash'); // 'cash' | 'qris' | 'debit_card'
    const [cashTendered, setCashTendered] = useState('');
    const [submittingOrder, setSubmittingOrder] = useState(false);
    const [itemNoteModal, setItemNoteModal] = useState({ isOpen: false, itemIndex: -1, currentNote: '' });

    // Fetch initial data
    useEffect(() => {
        fetchCategories();
        fetchProducts();
    }, []);

    const fetchCategories = async () => {
        try {
            const res = await axios.get('/api/pos/categories');
            if (res.data.success) {
                setCategories(res.data.categories);
            }
        } catch (e) {
            console.error('Failed fetching categories', e);
        }
    };

    const fetchProducts = async (catId = null, search = '') => {
        setLoading(true);
        try {
            const params = {};
            if (catId) params.category_id = catId;
            if (search) params.search = search;
            const res = await axios.get('/api/pos/products', { params });
            if (res.data.success) {
                setProducts(res.data.products);
            }
        } catch (e) {
            console.error('Failed fetching products', e);
        } finally {
            setLoading(false);
        }
    };

    // Filter products on frontend or trigger search
    const handleCategorySelect = (catId) => {
        setSelectedCategory(catId);
        fetchProducts(catId, searchQuery);
    };

    const handleSearchChange = (e) => {
        const val = e.target.value;
        setSearchQuery(val);
        fetchProducts(selectedCategory, val);
    };

    // Cart calculations
    const subtotal = useMemo(() => {
        return cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
    }, [cart]);

    const discountVal = useMemo(() => {
        return Math.min(subtotal, Math.max(0, Number(discountAmount) || 0));
    }, [subtotal, discountAmount]);

    const taxableAmount = useMemo(() => {
        return Math.max(0, subtotal - discountVal);
    }, [subtotal, discountVal]);

    const taxAmount = useMemo(() => {
        return Math.round((taxableAmount * taxRate) / 100);
    }, [taxableAmount, taxRate]);

    const grandTotal = useMemo(() => {
        return taxableAmount + taxAmount;
    }, [taxableAmount, taxAmount]);

    // Change Due calculation
    const changeDue = useMemo(() => {
        if (paymentMethod !== 'cash') return 0;
        const tendered = Number(cashTendered) || 0;
        return Math.max(0, tendered - grandTotal);
    }, [paymentMethod, cashTendered, grandTotal]);

    // Cart Actions
    const addToCart = (product) => {
        const availableStock = Number(product.stock) || 0;
        if (availableStock <= 0) {
            alert(`Maaf, menu "${product.name}" sedang habis!`);
            return;
        }

        setCart(prev => {
            const existingIndex = prev.findIndex(item => item.product_id === product.id);
            if (existingIndex > -1) {
                const currentQty = prev[existingIndex].quantity;
                if (currentQty >= availableStock) {
                    alert(`Stok maksimal untuk "${product.name}" adalah ${availableStock} porsi.`);
                    return prev;
                }
                const next = [...prev];
                next[existingIndex] = {
                    ...next[existingIndex],
                    quantity: currentQty + 1,
                    subtotal: (currentQty + 1) * product.price,
                };
                return next;
            } else {
                return [...prev, {
                    product_id: product.id,
                    name: product.name,
                    price: product.price,
                    image_url: product.image_url,
                    quantity: 1,
                    subtotal: product.price,
                    notes: '',
                }];
            }
        });
    };

    const updateQty = (index, delta) => {
        setCart(prev => {
            const next = [...prev];
            const item = next[index];
            if (!item) return prev;

            if (delta > 0) {
                const prod = products.find(p => p.id === item.product_id);
                const maxStock = prod ? Number(prod.stock) : 999;
                if (item.quantity >= maxStock) {
                    alert(`Stok maksimal untuk "${item.name}" adalah ${maxStock} porsi.`);
                    return prev;
                }
            }

            const newQty = item.quantity + delta;
            if (newQty <= 0) {
                next.splice(index, 1);
            } else {
                next[index] = {
                    ...item,
                    quantity: newQty,
                    subtotal: newQty * item.price,
                };
            }
            return next;
        });
    };

    const removeItem = (index) => {
        setCart(prev => prev.filter((_, i) => i !== index));
    };

    const clearCart = () => {
        if (cart.length > 0 && window.confirm('Kosongkan keranjang pesanan?')) {
            setCart([]);
            setDiscountAmount(0);
            setOrderNotes('');
        }
    };

    const openNoteModal = (index) => {
        setItemNoteModal({
            isOpen: true,
            itemIndex: index,
            currentNote: cart[index]?.notes || '',
        });
    };

    const saveItemNote = () => {
        if (itemNoteModal.itemIndex > -1) {
            setCart(prev => {
                const next = [...prev];
                next[itemNoteModal.itemIndex].notes = itemNoteModal.currentNote;
                return next;
            });
        }
        setItemNoteModal({ isOpen: false, itemIndex: -1, currentNote: '' });
    };

    // Open Checkout
    const handleOpenCheckout = () => {
        if (cart.length === 0) return;
        setCashTendered(grandTotal.toString()); // Pre-fill exact cash
        setIsCheckoutOpen(true);
    };

    // Quick cash presets
    const setPresetCash = (amount) => {
        setCashTendered(amount.toString());
    };

    // Submit Order
    const handleProcessOrder = async () => {
        if (cart.length === 0) return;
        if (paymentMethod === 'cash' && Number(cashTendered) < grandTotal) {
            alert('Uang tunai yang diterima kurang dari total pembayaran!');
            return;
        }

        setSubmittingOrder(true);
        try {
            const payload = {
                customer_name: customerName,
                table_number: orderType === 'take_away' ? 'Take Away' : tableNumber,
                order_type: orderType,
                payment_method: paymentMethod,
                cash_tendered: paymentMethod === 'cash' ? Number(cashTendered) : grandTotal,
                discount_amount: discountVal,
                tax_rate: taxRate,
                notes: orderNotes,
                items: cart.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    notes: item.notes,
                })),
            };

            const res = await axios.post('/api/pos/orders', payload);
            if (res.data.success) {
                // Instantly update product stocks in local state
                if (res.data.updated_stocks && Array.isArray(res.data.updated_stocks)) {
                    const stockMap = new Map(res.data.updated_stocks.map(s => [s.id, s.stock]));
                    setProducts(prev => prev.map(p => {
                        if (stockMap.has(p.id)) {
                            return { ...p, stock: stockMap.get(p.id) };
                        }
                        return p;
                    }));
                }

                // Re-fetch products to ensure full synchronization across categories & search
                fetchProducts(selectedCategory, searchQuery);

                // Play audio ding
                playCashierSuccessDing();

                // Trigger confetti
                confetti({
                    particleCount: 80,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#e11d48', '#f59e0b', '#10b981', '#3b82f6'],
                });

                setLastCompletedOrder(res.data.order);
                setLastReceiptData(res.data.receipt);

                // Auto-print fallback: if auto_print is on, and direct hardware print is disabled or failed
                const receipt = res.data.receipt;
                const directPrinted = receipt?.direct_print_result?.success;
                if (receipt?.auto_print && !directPrinted && !receipt?.direct_hardware_print) {
                    setTimeout(() => {
                        printReceiptWindow(receipt.plain_text, receipt.paper_size);
                    }, 400);
                }

                // Reset Cart & Close Checkout
                setCart([]);
                setDiscountAmount(0);
                setOrderNotes('');
                setIsCheckoutOpen(false);

                // Open Thermal Receipt Modal
                setIsReceiptOpen(true);
            }
        } catch (err) {
            alert('Gagal memproses transaksi: ' + (err.response?.data?.message || err.message));
        } finally {
            setSubmittingOrder(false);
        }
    };

    return (
        <div className="flex-1 flex flex-col lg:flex-row overflow-hidden bg-slate-950">
            {/* LEFT / CENTER: Catalog & Product Grid */}
            <div className="flex-1 flex flex-col h-full border-r border-slate-800/80 overflow-hidden">
                {/* Search & Header Bar */}
                <div className="p-4 bg-slate-900/60 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex-1 min-w-[240px] relative">
                        <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={handleSearchChange}
                            placeholder="Cari produk makanan atau minuman..."
                            className="w-full pl-9 pr-8 py-2 bg-slate-950 border border-slate-700/80 rounded-xl text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => { setSearchQuery(''); fetchProducts(selectedCategory, ''); }}
                                className="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-white">
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setIsPrinterSettingsOpen(true)}
                            className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 transition-all flex items-center gap-1.5"
                            title="Konfigurasi Printer Thermal">
                            <svg className="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>Printer ({printerSettings.paper_size || '58mm'})</span>
                        </button>
                    </div>
                </div>

                {/* Categories Bar */}
                <div className="px-4 py-3 bg-slate-900/40 border-b border-slate-800 overflow-x-auto flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => handleCategorySelect(null)}
                        className={`px-3.5 py-1.5 rounded-xl text-xs font-bold shrink-0 transition-all ${selectedCategory === null ? 'bg-rose-600 text-white shadow-md shadow-rose-950' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                        Semua Menu ({products.length})
                    </button>
                    {categories.map(cat => (
                        <button
                            key={cat.id}
                            type="button"
                            onClick={() => handleCategorySelect(cat.id)}
                            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold shrink-0 transition-all ${selectedCategory === cat.id ? 'bg-rose-600 text-white shadow-md shadow-rose-950' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`}>
                            {cat.name} ({cat.products_count ?? ''})
                        </button>
                    ))}
                </div>

                {/* Product Cards Grid */}
                <div className="flex-1 p-4 overflow-y-auto">
                    {loading ? (
                        <div className="h-64 flex items-center justify-center text-slate-500 text-sm">
                            Memuat menu...
                        </div>
                    ) : products.length === 0 ? (
                        <div className="h-64 flex flex-col items-center justify-center text-slate-500 text-sm">
                            <p>Tidak ada produk ditemukan.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3.5">
                            {products.map(product => {
                                const currentStock = Number(product.stock) || 0;
                                const isOutOfStock = currentStock <= 0;
                                const isLowStock = !isOutOfStock && currentStock <= 5;

                                return (
                                    <div
                                        key={product.id}
                                        onClick={() => {
                                            if (!isOutOfStock) {
                                                addToCart(product);
                                            }
                                        }}
                                        className={`group relative border rounded-2xl p-3 flex flex-col justify-between transition-all ${
                                            isOutOfStock
                                                ? 'bg-slate-900/40 border-red-950/60 opacity-80 cursor-not-allowed select-none'
                                                : 'bg-slate-900/80 hover:bg-slate-800/90 border-slate-800 hover:border-rose-500/50 cursor-pointer hover:scale-[1.02] shadow-sm active:scale-95 hover:shadow-lg hover:shadow-rose-950/20'
                                        }`}>
                                        
                                        {/* Image Container */}
                                        <div className="relative aspect-square w-full rounded-xl overflow-hidden bg-slate-950 mb-2.5">
                                            {product.image_url ? (
                                                <img
                                                    src={product.image_url}
                                                    alt={product.name}
                                                    className={`w-full h-full object-cover transition-transform duration-300 ${
                                                        isOutOfStock ? 'grayscale contrast-125 opacity-50' : 'group-hover:scale-105'
                                                    }`}
                                                />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center text-slate-600 font-bold text-center px-1 text-xs">
                                                    Qurais Kitchen
                                                </div>
                                            )}

                                            {/* Category Tag (Top-Left) */}
                                            <span className="absolute top-2 left-2 z-10 px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-950/90 backdrop-blur-md text-slate-300 border border-slate-800 shadow-md">
                                                {product.category?.name || 'Menu'}
                                            </span>

                                            {/* OUT OF STOCK RIBBON & OVERLAY */}
                                            {isOutOfStock ? (
                                                <>
                                                    {/* Top Right Ribbon */}
                                                    <div className="absolute top-2 right-2 z-20">
                                                        <span className="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-red-600 text-white shadow-lg shadow-red-950 border border-red-400">
                                                            HABIS
                                                        </span>
                                                    </div>

                                                    {/* Centered Frosted Overlay Banner */}
                                                    <div className="absolute inset-0 z-10 bg-slate-950/65 backdrop-blur-[2px] flex items-center justify-center p-2">
                                                        <span className="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-black uppercase tracking-wider shadow-xl shadow-red-950 border border-red-400 flex items-center gap-1.5 animate-pulse">
                                                            <svg className="w-3.5 h-3.5 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                            Stok Habis
                                                        </span>
                                                    </div>
                                                </>
                                            ) : (
                                                /* Stock Pill on Image (Bottom-Right) - High Contrast & Clear */
                                                <div className="absolute bottom-2 right-2 z-10">
                                                    {isLowStock ? (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-400 text-slate-950 border border-amber-300 shadow-lg shadow-amber-950/60">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-slate-950 animate-ping"></span>
                                                            Sisa {product.stock}
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-slate-950/95 text-emerald-300 border border-emerald-500/50 shadow-lg shadow-black/80 backdrop-blur-md">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                            Stok: {product.stock}
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Product Details */}
                                        <div>
                                            <h4 className={`text-xs font-bold line-clamp-1 transition-colors ${
                                                isOutOfStock ? 'text-slate-400' : 'text-white group-hover:text-rose-400'
                                            }`}>
                                                {product.name}
                                            </h4>
                                            <p className="text-[10px] text-slate-400 line-clamp-1 mt-0.5">
                                                {product.description || 'Pilihan lezat'}
                                            </p>
                                        </div>

                                        {/* Redesigned Stock Information Bar */}
                                        <div className="mt-2 pt-1.5 border-t border-slate-800/80 flex items-center justify-between">
                                            <span className="text-[10px] font-medium text-slate-400">Sisa Stok:</span>
                                            {isOutOfStock ? (
                                                <span className="text-[10px] font-black uppercase text-red-400 bg-red-950/80 border border-red-800/70 px-2 py-0.5 rounded-md">
                                                    0 (Habis)
                                                </span>
                                            ) : isLowStock ? (
                                                <span className="text-[10px] font-black text-amber-300 bg-amber-950/80 border border-amber-800/70 px-2 py-0.5 rounded-md flex items-center gap-1">
                                                    <span className="w-1 h-1 rounded-full bg-amber-400 animate-pulse"></span>
                                                    {product.stock} Porsi (Menipis)
                                                </span>
                                            ) : (
                                                <span className="text-[10px] font-extrabold text-emerald-300 bg-emerald-950/80 border border-emerald-800/60 px-2 py-0.5 rounded-md">
                                                    {product.stock} Porsi
                                                </span>
                                            )}
                                        </div>

                                        {/* Price & Action Button */}
                                        <div className="mt-2.5 flex items-center justify-between">
                                            <span className={`text-xs font-extrabold ${isOutOfStock ? 'text-slate-400' : 'text-white'}`}>
                                                Rp {Number(product.price).toLocaleString('id-ID')}
                                            </span>

                                            {isOutOfStock ? (
                                                <button
                                                    type="button"
                                                    disabled
                                                    onClick={(e) => e.stopPropagation()}
                                                    title="Stok Habis"
                                                    className="w-7 h-7 rounded-lg bg-slate-800/90 text-slate-500 border border-slate-700/80 flex items-center justify-center text-xs font-bold cursor-not-allowed shadow-none">
                                                    <svg className="w-3.5 h-3.5 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        addToCart(product);
                                                    }}
                                                    title="Tambah ke keranjang"
                                                    className="w-7 h-7 rounded-lg bg-rose-600 group-hover:bg-rose-500 text-white flex items-center justify-center text-xs font-bold shadow-md shadow-rose-950 transition-all hover:scale-110 active:scale-95">
                                                    +
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* RIGHT: Order Cart & Billing Panel */}
            <div className="w-full lg:w-96 flex flex-col h-full bg-slate-900/90 border-t lg:border-t-0 lg:border-l border-slate-800 shadow-2xl">
                {/* Cart Header */}
                <div className="p-4 border-b border-slate-800 bg-slate-950/40">
                    <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                            <h3 className="text-sm font-extrabold text-white">Keranjang Pesanan</h3>
                            <span className="px-2 py-0.5 text-[11px] font-bold rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                {cart.length} item
                            </span>
                        </div>
                        {cart.length > 0 && (
                            <button
                                type="button"
                                onClick={clearCart}
                                className="text-[11px] text-slate-400 hover:text-rose-400">
                                Kosongkan
                            </button>
                        )}
                    </div>

                    {/* Order Type Tabs */}
                    <div className="flex rounded-xl bg-slate-950 p-1 border border-slate-800 text-[11px] font-bold mb-3">
                        <button
                            type="button"
                            onClick={() => setOrderType('dine_in')}
                            className={`flex-1 py-1.5 rounded-lg transition-all ${orderType === 'dine_in' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-white'}`}>
                            Dine In
                        </button>
                        <button
                            type="button"
                            onClick={() => setOrderType('take_away')}
                            className={`flex-1 py-1.5 rounded-lg transition-all ${orderType === 'take_away' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-white'}`}>
                            Take Away
                        </button>
                        <button
                            type="button"
                            onClick={() => setOrderType('delivery')}
                            className={`flex-1 py-1.5 rounded-lg transition-all ${orderType === 'delivery' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-white'}`}>
                            Delivery
                        </button>
                    </div>

                    {/* Customer & Table Inputs */}
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label className="block text-[10px] font-semibold text-slate-400 mb-1">Nama Pemesan</label>
                            <input
                                type="text"
                                value={customerName}
                                onChange={(e) => setCustomerName(e.target.value)}
                                className="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500"
                                placeholder="Pelanggan"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-semibold text-slate-400 mb-1">
                                {orderType === 'dine_in' ? 'Nomor Meja' : 'Lokasi / Catatan'}
                            </label>
                            <select
                                value={tableNumber}
                                onChange={(e) => setTableNumber(e.target.value)}
                                disabled={orderType === 'take_away'}
                                className="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white">
                                {orderType === 'take_away' ? (
                                    <option value="Take Away">Take Away</option>
                                ) : (
                                    <>
                                        <option value="Meja 01">Meja 01</option>
                                        <option value="Meja 02">Meja 02</option>
                                        <option value="Meja 03">Meja 03</option>
                                        <option value="Meja 04">Meja 04</option>
                                        <option value="Meja 05">Meja 05</option>
                                        <option value="Meja 06">Meja 06</option>
                                        <option value="Meja 07">Meja 07</option>
                                        <option value="Meja 08">Meja 08</option>
                                        <option value="VIP Room">VIP Room</option>
                                        <option value="Area Outdoor">Area Outdoor</option>
                                    </>
                                )}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Cart Items List */}
                <div className="flex-1 overflow-y-auto p-3 space-y-2.5">
                    {cart.length === 0 ? (
                        <div className="h-full flex flex-col items-center justify-center text-slate-500 text-xs text-center p-6">
                            <svg className="w-12 h-12 stroke-1 text-slate-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <p className="font-semibold text-slate-400">Keranjang masih kosong</p>
                            <p className="mt-1">Pilih menu dari katalog di sebelah kiri untuk memulai order.</p>
                        </div>
                    ) : (
                        cart.map((item, idx) => {
                            const prod = products.find(p => p.id === item.product_id);
                            const availableStock = prod ? Number(prod.stock) : 999;
                            const isMaxReached = item.quantity >= availableStock;

                            return (
                                <div key={idx} className="p-2.5 bg-slate-950/60 border border-slate-800/80 rounded-xl space-y-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex-1">
                                            <h5 className="text-xs font-bold text-white line-clamp-1">{item.name}</h5>
                                            <div className="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                                                <span>Rp {Number(item.price).toLocaleString('id-ID')}</span>
                                                <span className="text-slate-600">·</span>
                                                <span className={`text-[10px] font-semibold px-1.5 py-0.2 rounded ${
                                                    isMaxReached
                                                        ? 'bg-amber-950/80 text-amber-300 border border-amber-800/60'
                                                        : 'bg-slate-900 text-slate-400 border border-slate-800'
                                                }`}>
                                                    Maks: {availableStock}
                                                </span>
                                            </div>
                                        </div>
                                        <span className="text-xs font-extrabold text-white">
                                            Rp {Number(item.subtotal).toLocaleString('id-ID')}
                                        </span>
                                    </div>

                                    {item.notes && (
                                        <div className="text-[10px] bg-rose-500/10 text-rose-300 border border-rose-500/20 px-2 py-0.5 rounded">
                                            * {item.notes}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between pt-1 border-t border-slate-800/60">
                                        <button
                                            type="button"
                                            onClick={() => openNoteModal(idx)}
                                            className="text-[10px] text-slate-400 hover:text-white flex items-center gap-1">
                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            {item.notes ? 'Edit Catatan' : '+ Catatan'}
                                        </button>

                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => updateQty(idx, -1)}
                                                className="w-6 h-6 rounded-md bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center text-xs font-bold">
                                                -
                                            </button>
                                            <span className="w-5 text-center text-xs font-bold text-white">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                disabled={isMaxReached}
                                                onClick={() => updateQty(idx, 1)}
                                                className={`w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold transition-all ${
                                                    isMaxReached
                                                        ? 'bg-slate-800 text-slate-600 cursor-not-allowed border border-slate-700/50'
                                                        : 'bg-rose-600 hover:bg-rose-500 text-white shadow'
                                                }`}
                                                title={isMaxReached ? `Stok maksimal (${availableStock}) tercapai` : 'Tambah qty'}>
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Calculation Summary & Checkout Trigger */}
                <div className="p-4 bg-slate-950 border-t border-slate-800 space-y-2">
                    <div className="flex items-center justify-between text-xs text-slate-400">
                        <span>Subtotal</span>
                        <span className="font-mono text-white">Rp {subtotal.toLocaleString('id-ID')}</span>
                    </div>

                    <div className="flex items-center justify-between text-xs text-slate-400">
                        <span className="flex items-center gap-1">
                            Diskon
                            <input
                                type="number"
                                min="0"
                                value={discountAmount || ''}
                                onChange={(e) => setDiscountAmount(e.target.value)}
                                placeholder="0"
                                className="w-16 px-1.5 py-0.5 bg-slate-900 border border-slate-800 rounded text-[10px] text-right text-rose-400 focus:outline-none"
                            />
                        </span>
                        <span className="font-mono text-rose-400">-Rp {discountVal.toLocaleString('id-ID')}</span>
                    </div>

                    <div className="flex items-center justify-between text-xs text-slate-400">
                        <span>Pajak Resto (PB1 10%)</span>
                        <span className="font-mono text-white">Rp {taxAmount.toLocaleString('id-ID')}</span>
                    </div>

                    <div className="pt-2 border-t border-slate-800 flex items-center justify-between">
                        <div>
                            <span className="text-[10px] uppercase font-bold text-slate-400 block">TOTAL TAGIHAN</span>
                            <span className="text-lg font-black text-white">
                                Rp {grandTotal.toLocaleString('id-ID')}
                            </span>
                        </div>

                        <button
                            type="button"
                            disabled={cart.length === 0}
                            onClick={handleOpenCheckout}
                            className={`px-5 py-3 rounded-xl font-extrabold text-xs tracking-wide transition-all shadow-xl flex items-center gap-2 ${cart.length === 0 ? 'bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-gradient-to-r from-rose-600 to-orange-500 hover:from-rose-500 hover:to-orange-400 text-white shadow-rose-950/50 hover:scale-105 active:scale-95'}`}>
                            <span>BAYAR SEKARANG</span>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {/* CHECKOUT PAYMENT MODAL */}
            {isCheckoutOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-lg w-full flex flex-col overflow-hidden">
                        {/* Header */}
                        <div className="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                            <div>
                                <h3 className="text-sm font-bold text-white">Pembayaran Pesanan</h3>
                                <p className="text-[11px] text-slate-400">
                                    {orderType.toUpperCase()} • {customerName} • {tableNumber}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsCheckoutOpen(false)}
                                className="p-2 text-slate-400 hover:text-white">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {/* Body */}
                        <div className="p-6 space-y-4 overflow-y-auto max-h-[80vh]">
                            {/* Total Banner */}
                            <div className="p-4 rounded-xl bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border border-slate-800 text-center">
                                <span className="text-xs text-slate-400 font-medium">Total Yang Harus Dibayar:</span>
                                <div className="text-2xl font-black text-white mt-1">
                                    Rp {grandTotal.toLocaleString('id-ID')}
                                </div>
                            </div>

                            {/* Payment Method Selector */}
                            <div>
                                <label className="block text-xs font-bold text-slate-300 mb-2">Metode Pembayaran</label>
                                <div className="grid grid-cols-3 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setPaymentMethod('cash')}
                                        className={`p-3 rounded-xl border text-xs font-bold flex flex-col items-center gap-1.5 transition-all ${paymentMethod === 'cash' ? 'bg-rose-600/20 border-rose-500 text-rose-300' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white'}`}>
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Tunai (Cash)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPaymentMethod('qris')}
                                        className={`p-3 rounded-xl border text-xs font-bold flex flex-col items-center gap-1.5 transition-all ${paymentMethod === 'qris' ? 'bg-rose-600/20 border-rose-500 text-rose-300' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white'}`}>
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                        QRIS
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPaymentMethod('debit_card')}
                                        className={`p-3 rounded-xl border text-xs font-bold flex flex-col items-center gap-1.5 transition-all ${paymentMethod === 'debit_card' ? 'bg-rose-600/20 border-rose-500 text-rose-300' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white'}`}>
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        Kartu Debit
                                    </button>
                                </div>
                            </div>

                            {/* Cash Tendered Input & Change */}
                            {paymentMethod === 'cash' && (
                                <div className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                                            Jumlah Uang Tunai Diterima (Rp)
                                        </label>
                                        <input
                                            type="number"
                                            value={cashTendered}
                                            onChange={(e) => setCashTendered(e.target.value)}
                                            className="w-full px-3 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-base font-bold font-mono text-white focus:outline-none focus:ring-2 focus:ring-rose-500"
                                            placeholder="0"
                                        />
                                    </div>

                                    {/* Quick Amount Buttons */}
                                    <div className="grid grid-cols-4 gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setPresetCash(grandTotal)}
                                            className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[11px] font-bold text-slate-200">
                                            Uang Pas
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setPresetCash(50000)}
                                            className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[11px] font-bold text-slate-200">
                                            50.000
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setPresetCash(100000)}
                                            className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[11px] font-bold text-slate-200">
                                            100.000
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setPresetCash(200000)}
                                            className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[11px] font-bold text-slate-200">
                                            200.000
                                        </button>
                                    </div>

                                    {/* Kembalian Box */}
                                    <div className="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                                        <span className="text-xs font-bold text-slate-400">Kembalian:</span>
                                        <span className="text-lg font-black font-mono text-emerald-400">
                                            Rp {changeDue.toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* QRIS Display */}
                            {paymentMethod === 'qris' && (
                                <div className="p-4 rounded-xl bg-white text-slate-900 flex flex-col items-center justify-center space-y-2">
                                    <div className="w-40 h-40 bg-slate-100 rounded-lg p-2 border-2 border-dashed border-slate-400 flex flex-col items-center justify-center text-center">
                                        <svg className="w-24 h-24 text-slate-800" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h4v4H4V4zm2 2h0v0h0v0zm8-2h4v4h-4V4zm2 2h0v0h0v0zM4 14h4v4H4v-4zm2 2h0v0h0v0zm10 0h2v2h-2v-2zm-4-6h2v2h-2v-2zm4 2h2v2h-2v-2zm-6 2h2v2h-2v-2zm4 4h2v2h-2v-2zm2-2h2v2h-2v-2z"/></svg>
                                        <span className="text-[10px] font-bold tracking-wider text-slate-600 mt-1">QRIS NATIONAL</span>
                                    </div>
                                    <span className="text-xs font-bold text-slate-800">Scan QRIS Qurais Kitchen</span>
                                    <span className="text-[11px] text-slate-500">Mendukung GoPay, OVO, Dana, ShopeePay, BCA</span>
                                </div>
                            )}

                            {/* Card Display */}
                            {paymentMethod === 'debit_card' && (
                                <div className="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2 text-xs">
                                    <p className="text-slate-300">Gesek atau tap kartu pada mesin EDC Kasir.</p>
                                    <div className="flex gap-2">
                                        <span className="px-2 py-1 rounded bg-slate-800 font-bold text-slate-300">BCA</span>
                                        <span className="px-2 py-1 rounded bg-slate-800 font-bold text-slate-300">Mandiri</span>
                                        <span className="px-2 py-1 rounded bg-slate-800 font-bold text-slate-300">BRI</span>
                                        <span className="px-2 py-1 rounded bg-slate-800 font-bold text-slate-300">BNI</span>
                                    </div>
                                </div>
                            )}

                            {/* Order Notes */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1">Catatan Tambahan Transaksi</label>
                                <textarea
                                    rows={3}
                                    value={orderNotes}
                                    onChange={(e) => setOrderNotes(e.target.value)}
                                    placeholder="Contoh: Tamu VIP / Kemasan Khusus / Pengantaran terpisah..."
                                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white resize-y min-h-[75px] focus:outline-none focus:ring-2 focus:ring-rose-500"
                                />
                            </div>
                        </div>

                        {/* Footer Actions */}
                        <div className="p-4 border-t border-slate-800 bg-slate-950/80 flex items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={() => setIsCheckoutOpen(false)}
                                className="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-400 hover:text-white">
                                Batalkan
                            </button>

                            <button
                                type="button"
                                disabled={submittingOrder || (paymentMethod === 'cash' && Number(cashTendered) < grandTotal)}
                                onClick={handleProcessOrder}
                                className={`px-6 py-2.5 rounded-xl font-bold text-xs text-white shadow-lg flex items-center gap-2 transition-all ${submittingOrder || (paymentMethod === 'cash' && Number(cashTendered) < grandTotal) ? 'bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-950'}`}>
                                {submittingOrder ? (
                                    <>
                                        <svg className="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                        <span>Memproses...</span>
                                    </>
                                ) : (
                                    <>
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Konfirmasi Pembayaran & Cetak</span>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ITEM NOTE MODAL */}
            {itemNoteModal.isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-5 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div>
                                <h4 className="text-sm font-bold text-white">Catatan Khusus Menu Dapur</h4>
                                <p className="text-xs text-rose-400 font-semibold mt-0.5">
                                    {cart[itemNoteModal.itemIndex]?.name || 'Menu Item'}
                                </p>
                            </div>
                            <div className="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                        </div>

                        <div>
                            <label className="block text-[11px] font-semibold text-slate-400 mb-1.5">
                                Masukkan instruksi khusus dapur (ruang luas untuk catatan panjang):
                            </label>
                            <textarea
                                rows={4}
                                value={itemNoteModal.currentNote}
                                onChange={(e) => setItemNoteModal(prev => ({ ...prev, currentNote: e.target.value }))}
                                placeholder="Contoh: Pedas level 3, jangan pakai seledri & bawang goreng, kuah dipisah di mangkok kecil, minta sendok garpu tambahan..."
                                className="w-full px-3 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white placeholder-slate-500 resize-y min-h-[120px] focus:outline-none focus:ring-2 focus:ring-rose-500 leading-relaxed"
                                autoFocus
                            />
                        </div>

                        {/* Quick Suggestion Chips */}
                        <div className="space-y-1.5">
                            <span className="text-[10px] text-slate-500 font-semibold">Saran Cepat:</span>
                            <div className="flex flex-wrap gap-1.5">
                                {['Tanpa Bawang', 'Pedas Sedang', 'Ekstra Sambal', 'Kuah Dipisah', 'Es Sedikit', 'Bungkus Rapi', 'Paha Atas'].map((chip) => (
                                    <button
                                        key={chip}
                                        type="button"
                                        onClick={() => {
                                            setItemNoteModal(prev => {
                                                const existing = (prev.currentNote || '').trim();
                                                const updated = existing ? `${existing}, ${chip}` : chip;
                                                return { ...prev, currentNote: updated };
                                            });
                                        }}
                                        className="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-[11px] font-medium border border-slate-700/60 transition-all">
                                        + {chip}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-2 border-t border-slate-800">
                            <button
                                type="button"
                                onClick={() => setItemNoteModal({ isOpen: false, itemIndex: -1, currentNote: '' })}
                                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white">
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={saveItemNote}
                                className="px-5 py-2 bg-rose-600 hover:bg-rose-500 rounded-xl text-xs font-bold text-white shadow-md shadow-rose-950 transition-all">
                                Simpan Catatan
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* THERMAL RECEIPT MODAL */}
            <ThermalReceiptModal
                isOpen={isReceiptOpen}
                onClose={() => setIsReceiptOpen(false)}
                receiptData={lastReceiptData}
                order={lastCompletedOrder}
            />

            {/* PRINTER SETTINGS MODAL */}
            <PrinterSettingsModal
                isOpen={isPrinterSettingsOpen}
                onClose={() => setIsPrinterSettingsOpen(false)}
                initialSettings={printerSettings}
                onSettingsUpdated={(newSettings) => setPrinterSettings(newSettings)}
            />
        </div>
    );
}
