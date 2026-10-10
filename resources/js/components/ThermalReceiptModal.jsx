import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { downloadRawEscPos, printDirectWebSerial } from '../utils/thermalPrinter';

export default function ThermalReceiptModal({ isOpen, onClose, receiptData, order }) {
    const [viewMode, setViewMode] = useState('receipt'); // 'receipt' | 'escpos'
    const [serialStatus, setSerialStatus] = useState('');
    const [copied, setCopied] = useState(false);
    const [loadingReceipt, setLoadingReceipt] = useState(false);
    const [fetchedReceipt, setFetchedReceipt] = useState(null);

    const orderId = order?.id || receiptData?.order_id;
    const orderNumber = order?.order_number || receiptData?.order_number || 'TRX';

    const isKitchen = receiptData?.type === 'kitchen';
    const isKitchenItem = receiptData?.type === 'kitchen_item';
    const isKitchenType = isKitchen || isKitchenItem;

    // Check if receipt data exists and is complete
    const rawText = receiptData?.plain_text || '';
    const hasFullReceipt = isKitchenType ? Boolean(rawText) : (rawText && rawText.includes('TOTAL'));

    // Fetch fresh and full receipt data if missing or incomplete (for cashier receipts only)
    useEffect(() => {
        let isMounted = true;
        if (isOpen && !isKitchenType && orderId && (!hasFullReceipt && !fetchedReceipt)) {
            setLoadingReceipt(true);
            axios.get(`/api/orders/${orderId}/receipt`)
                .then(res => {
                    if (isMounted && res.data.success) {
                        setFetchedReceipt(res.data);
                    }
                })
                .catch(err => {
                    console.error('Gagal mengambil struk lengkap:', err);
                })
                .finally(() => {
                    if (isMounted) setLoadingReceipt(false);
                });
        }
        return () => {
            isMounted = false;
        };
    }, [isOpen, orderId, hasFullReceipt, fetchedReceipt, isKitchenType]);

    // Reset local state on close
    useEffect(() => {
        if (!isOpen) {
            setFetchedReceipt(null);
            setSerialStatus('');
            setCopied(false);
        }
    }, [isOpen]);

    if (!isOpen || (!receiptData && !order)) return null;

    const activeReceipt = fetchedReceipt || (hasFullReceipt ? receiptData : (receiptData || {}));
    const paperSize = activeReceipt?.paper_size || '58mm';
    const is80mm = paperSize === '80mm';

    // Reliable fallback formatter if network is down
    const generateFallbackPlainText = () => {
        if (!order) return 'Data struk tidak tersedia.';
        const width = is80mm ? 48 : 32;
        const pad = (l, r) => {
            const leftStr = String(l ?? '');
            const rightStr = String(r ?? '');
            const spaces = Math.max(1, width - leftStr.length - rightStr.length);
            return leftStr + ' '.repeat(spaces) + rightStr;
        };
        const center = (t) => {
            const textStr = String(t ?? '').trim();
            const left = Math.max(0, Math.floor((width - textStr.length) / 2));
            const right = Math.max(0, width - textStr.length - left);
            return ' '.repeat(left) + textStr + ' '.repeat(right);
        };

        const lines = [];
        lines.push(center('Qurais Kitchen'));
        lines.push(center('Cita Rasa Istimewa Setiap Saat'));
        lines.push(center('Jl. Boulevard Raya No. 88,'));
        lines.push(center('Jakarta Selatan'));
        lines.push(center('Telp: 0812-3456-7890'));
        lines.push('='.repeat(width));
        lines.push(`No: ${order.order_number || 'TRX'}`);
        const dateStr = order.created_at ? new Date(order.created_at).toLocaleDateString('id-ID') : new Date().toLocaleDateString('id-ID');
        lines.push(pad(dateStr, `Tipe: ${(order.order_type || 'dine_in').toUpperCase()}`));
        lines.push(`Kasir: ${order.cashier?.name || 'Qurais Kitchen'}`);
        if (order.table_number) lines.push(`Meja / Lokasi: ${order.table_number}`);
        lines.push(`Pelanggan: ${order.customer_name || 'Pelanggan Umum'}`);
        lines.push('-'.repeat(width));
        lines.push(pad('Item / Qty x Harga', 'Subtotal'));
        lines.push('-'.repeat(width));

        if (order.items && order.items.length > 0) {
            order.items.forEach(it => {
                const name = it.product_name || it.product?.name || 'Item';
                lines.push(name);
                const q = Number(it.quantity || 1);
                const p = Number(it.price || it.product?.price || 0);
                const s = Number(it.subtotal || (p * q));
                lines.push(pad(` ${q} x ${p.toLocaleString('id-ID')}`, s.toLocaleString('id-ID')));
                if (it.notes) lines.push(`   * ${it.notes}`);
            });
        }
        lines.push('-'.repeat(width));
        lines.push(pad('Subtotal', `Rp ${Number(order.subtotal || 0).toLocaleString('id-ID')}`));
        if (Number(order.discount_amount) > 0) {
            lines.push(pad('Diskon', `-Rp ${Number(order.discount_amount).toLocaleString('id-ID')}`));
        }
        if (Number(order.tax_amount) > 0) {
            lines.push(pad(`Pajak (PB1 ${Number(order.tax_rate || 10)}%)`, `Rp ${Number(order.tax_amount).toLocaleString('id-ID')}`));
        }
        lines.push('='.repeat(width));
        lines.push(pad('TOTAL', `Rp ${Number(order.total_amount || 0).toLocaleString('id-ID')}`));
        lines.push(pad('Metode Bayar', (order.payment_method || 'CASH').toUpperCase()));
        if (order.payment_method === 'cash') {
            lines.push(pad('Tunai', `Rp ${Number(order.cash_tendered || order.total_amount).toLocaleString('id-ID')}`));
            lines.push(pad('Kembalian', `Rp ${Number(order.change_due || 0).toLocaleString('id-ID')}`));
        }
        lines.push(pad('Status', (order.payment_status || 'PAID').toUpperCase()));
        if (order.notes) {
            lines.push('-'.repeat(width));
            lines.push('CATATAN PESANAN:');
            lines.push(order.notes);
        }
        lines.push('='.repeat(width));
        lines.push(center('Terima Kasih Telah Berkunjung!'));
        lines.push(center('Barang yang dibeli tidak dapat'));
        lines.push(center('ditukar.'));
        lines.push(center('Simpan struk ini sebagai bukti.'));
        lines.push(center('POS System Powered by Qurais Kitchen'));
        return lines.join('\n');
    };

    const plainText = isKitchenType
        ? (activeReceipt?.plain_text || '')
        : ((activeReceipt?.plain_text && activeReceipt.plain_text.includes('TOTAL')) 
            ? activeReceipt.plain_text 
            : (generateFallbackPlainText() || activeReceipt?.plain_text || ''));
    const escposBase64 = activeReceipt?.escpos_base64 || '';

    const handleDirectHardwarePrint = async () => {
        setSerialStatus('Mengirim langsung ke printer thermal...');
        try {
            let res;
            if (isKitchenItem && activeReceipt.item_id) {
                res = await axios.post(`/api/printer/print-item/${activeReceipt.item_id}`);
            } else if (isKitchen && orderId) {
                res = await axios.post(`/api/printer/print-order/${orderId}`, { type: 'kitchen' });
            } else if (orderId) {
                res = await axios.post(`/api/printer/print-order/${orderId}`, { type: 'receipt' });
            } else {
                return;
            }

            if (res.data.success) {
                const latency = res.data.latency_ms ? ` (⏱️ Latency: ${res.data.latency_ms} ms)` : '';
                setSerialStatus((res.data.message || 'Tiket berhasil dicetak di printer thermal!') + latency);
                setTimeout(() => setSerialStatus(''), 4500);
            } else {
                setSerialStatus('Gagal: ' + res.data.message);
                setTimeout(() => setSerialStatus(''), 5000);
            }
        } catch (err) {
            setSerialStatus('Gagal cetak: ' + (err.response?.data?.message || err.message));
            setTimeout(() => setSerialStatus(''), 5000);
        }
    };

    const handleDownloadBin = () => {
        if (orderId) {
            downloadRawEscPos(orderId, orderNumber);
        }
    };

    const handlePrintWebSerial = async () => {
        if (!escposBase64) return;
        const tStart = performance.now();
        setSerialStatus('Menghubungkan ke printer USB...');
        try {
            const res = await printDirectWebSerial(escposBase64);
            const duration = Math.round(performance.now() - tStart);
            if (orderId) {
                // Record print latency to backend database & Laravel log
                axios.post(`/api/printer/record-print-latency/${orderId}`, {
                    method: 'web_serial',
                    duration_ms: duration,
                    success: true,
                    printer: 'USB Web Serial Thermal'
                }).then(latRes => {
                    const lat = latRes.data.latency_ms ? ` (⏱️ Latency: ${latRes.data.latency_ms} ms)` : '';
                    setSerialStatus(res.message + lat);
                }).catch(() => {
                    setSerialStatus(res.message);
                });
            } else {
                setSerialStatus(res.message);
            }
            setTimeout(() => setSerialStatus(''), 4500);
        } catch (err) {
            setSerialStatus('Gagal: ' + (err.message || 'Perangkat tidak ditemukan'));
            setTimeout(() => setSerialStatus(''), 5000);
        }
    };

    const handleCopyText = () => {
        navigator.clipboard.writeText(plainText);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
            <div className={`bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl w-full ${is80mm ? 'max-w-xl' : 'max-w-lg'} max-h-[94vh] flex flex-col overflow-hidden`}>
                {/* Header */}
                <div className="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div className="flex items-center gap-2.5">
                        <div className="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-white">
                                {isKitchenItem 
                                    ? `Tiket Menu: ${activeReceipt.product_name || 'Item'}` 
                                    : (isKitchen ? 'Tiket Dapur (KDS)' : 'Struk Transaksi')}
                            </h3>
                            <p className="text-[11px] text-slate-400">Order #{orderNumber}</p>
                        </div>
                    </div>

                    {/* Mode Switcher Tabs */}
                    <div className="flex bg-slate-950 rounded-lg p-0.5 border border-slate-800 text-xs font-semibold">
                        <button
                            type="button"
                            onClick={() => setViewMode('receipt')}
                            className={`px-3 py-1 rounded-md transition-all ${viewMode === 'receipt' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-white'}`}>
                            {isKitchenType ? 'Preview Tiket' : 'Preview Struk'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewMode('escpos')}
                            className={`px-3 py-1 rounded-md transition-all ${viewMode === 'escpos' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-white'}`}>
                            Raw ESC/POS
                        </button>
                    </div>
                </div>

                {/* Status alert message */}
                {activeReceipt?.direct_print_result?.success && !serialStatus && (
                    <div className="px-4 py-2 text-xs font-semibold bg-emerald-500/10 border-b border-emerald-500/20 text-emerald-300 flex items-center justify-between">
                        <span className="flex items-center gap-1.5">
                            <svg className="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                            Struk otomatis tercetak ke mesin: <strong>{activeReceipt.direct_print_result.printer}</strong>
                        </span>
                        <div className="flex items-center gap-1.5">
                            {activeReceipt.direct_print_result.latency_ms && (
                                <span className="text-[10px] font-mono bg-emerald-950 px-2 py-0.5 rounded text-emerald-300 border border-emerald-700/60 font-bold">
                                    ⏱️ {activeReceipt.direct_print_result.latency_ms} ms
                                </span>
                            )}
                            <span className="text-[10px] font-mono uppercase bg-emerald-500/20 px-1.5 py-0.5 rounded text-emerald-200">Hardware OK</span>
                        </div>
                    </div>
                )}

                {activeReceipt?.direct_print_result && !activeReceipt.direct_print_result.success && !serialStatus && (
                    <div className="px-4 py-2 text-xs font-semibold bg-amber-500/10 border-b border-amber-500/20 text-amber-300 flex items-center justify-between">
                        <span className="flex items-center gap-1.5">
                            <svg className="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Auto-print: {activeReceipt.direct_print_result.message}
                        </span>
                        <span className="text-[10px] font-mono uppercase bg-amber-500/20 px-1.5 py-0.5 rounded text-amber-200">Periksa USB</span>
                    </div>
                )}

                {serialStatus && (
                    <div className="px-4 py-2 text-xs font-medium bg-amber-500/10 border-b border-amber-500/20 text-amber-300 flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        {serialStatus}
                    </div>
                )}

                {/* Body Content */}
                <div className="p-3 sm:p-5 overflow-y-auto flex-1 flex flex-col items-center bg-slate-950/50 min-h-0">
                    {loadingReceipt ? (
                        <div className="h-64 flex flex-col items-center justify-center text-slate-400 gap-2">
                            <svg className="animate-spin h-6 w-6 text-rose-500" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span className="text-xs">Memuat data struk lengkap...</span>
                        </div>
                    ) : viewMode === 'receipt' ? (
                        <div className={`w-full ${is80mm ? 'max-w-[440px]' : 'max-w-[340px]'} thermal-paper rounded shadow-2xl px-4 sm:px-5 pt-4 pb-6 thermal-paper-tear-top thermal-paper-tear-bottom border-y border-dashed border-amber-300/40 my-3 transition-all shrink-0 h-fit`}>
                            <pre
                                className="text-[11.5px] sm:text-xs text-neutral-900 leading-snug select-text whitespace-pre overflow-visible m-0 p-0"
                                style={{
                                    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
                                }}
                            >
                                {plainText}
                            </pre>
                        </div>
                    ) : (
                        <div className="w-full space-y-3">
                            <div className="p-3 rounded-xl bg-slate-950 border border-slate-800">
                                <div className="flex items-center justify-between mb-1.5">
                                    <span className="text-xs font-bold text-slate-300">Base64 ESC/POS Binary Stream</span>
                                    <span className="text-[10px] text-slate-500">Siap dikirim ke Raw TCP/USB Socket</span>
                                </div>
                                <textarea
                                    readOnly
                                    value={escposBase64}
                                    className="w-full h-36 bg-slate-900 text-emerald-400 font-mono text-[11px] p-2 rounded border border-slate-800 focus:outline-none"
                                />
                            </div>

                            <div className="p-3 rounded-xl bg-slate-950/70 border border-slate-800 text-xs text-slate-400 space-y-1">
                                <p className="font-semibold text-white">Instruksi Mesin Thermal Printer Putian POS:</p>
                                <p>• <strong>Cetak Putian POS:</strong> Kirim byte ESC/POS langsung ke port hardware spooler Windows (USB002).</p>
                                <p>• <strong>Direct USB:</strong> Gunakan Web Serial API langsung dari browser.</p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer Actions */}
                <div className="p-4 border-t border-slate-800 bg-slate-950/80 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleCopyText}
                            className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-all">
                            {copied ? 'Tersalin!' : 'Salin Teks'}
                        </button>
                        <button
                            type="button"
                            onClick={handleDownloadBin}
                            className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-all flex items-center gap-1.5">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download .bin
                        </button>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleDirectHardwarePrint}
                            className="px-3.5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition-all shadow-lg shadow-orange-950/50 flex items-center gap-1.5"
                            title="Kirim cetak langsung ke printer Putian POS via spooler">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            {isKitchenType ? 'Cetak Tiket ke Printer' : 'Cetak Putian POS'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
