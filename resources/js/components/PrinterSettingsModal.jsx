import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { printReceiptWindow } from '../utils/thermalPrinter';

export default function PrinterSettingsModal({ isOpen, onClose, initialSettings, onSettingsUpdated }) {
    const [settings, setSettings] = useState(initialSettings || {
        store_name: 'Qurais Kitchen',
        store_tagline: 'Cita Rasa Istimewa Setiap Saat',
        store_address: 'Jl. Boulevard Raya No. 88, Jakarta Selatan',
        store_phone: '0812-3456-7890',
        paper_size: '58mm',
        windows_printer_name: 'Putian POS Thermal 2',
        direct_hardware_print: true,
        receipt_footer: "Terima Kasih Atas Kunjungan Anda!\nBarang yang sudah dibeli tidak dapat ditukar.",
        auto_print_cashier: true,
        auto_print_kitchen: true,
        cut_paper: true,
        open_cash_drawer: true,
    });
    const [installedPrinters, setInstalledPrinters] = useState(['Putian POS Thermal 2']);
    const [saving, setSaving] = useState(false);
    const [statusMsg, setStatusMsg] = useState('');

    useEffect(() => {
        if (isOpen) {
            fetchInstalledPrinters();
        }
    }, [isOpen]);

    const fetchInstalledPrinters = async () => {
        try {
            const res = await axios.get('/api/printer/installed-printers');
            if (res.data.success && res.data.printers?.length > 0) {
                setInstalledPrinters(res.data.printers);
            }
        } catch (e) {
            console.warn('Failed fetching installed printers', e);
        }
    };

    if (!isOpen) return null;

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setSettings(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        setStatusMsg('');
        try {
            const res = await axios.post('/api/printer/settings', settings);
            if (res.data.success) {
                setStatusMsg('Pengaturan printer thermal berhasil disimpan!');
                if (onSettingsUpdated) onSettingsUpdated(res.data.settings);
                setTimeout(() => {
                    setStatusMsg('');
                    onClose();
                }, 1200);
            }
        } catch (err) {
            setStatusMsg('Gagal menyimpan pengaturan: ' + (err.response?.data?.message || err.message));
        } finally {
            setSaving(false);
        }
    };

    const handleTestPrint = async () => {
        setStatusMsg('Mengirim perintah cetak langsung ke ' + (settings.windows_printer_name || 'Putian POS Thermal 2') + '...');
        try {
            const res = await axios.post('/api/printer/test', { force_direct: true });
            if (res.data.success) {
                setStatusMsg(res.data.message || 'Pola test print berhasil dikirim ke printer!');
                setTimeout(() => setStatusMsg(''), 4000);
            }
        } catch (err) {
            setStatusMsg('Gagal test print: ' + err.message);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden">
                <div className="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                    <div className="flex items-center gap-2.5">
                        <div className="w-8 h-8 rounded-lg bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-orange-400">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-white">Konfigurasi Mesin Printer Thermal</h3>
                            <p className="text-[11px] text-slate-400">Putian POS & Integrasi ESC/POS Otomatis</p>
                        </div>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 text-slate-400 hover:text-white">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {statusMsg && (
                    <div className="px-4 py-2.5 text-xs font-semibold bg-rose-500/10 border-b border-rose-500/20 text-rose-300 flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full bg-rose-400 animate-ping"></span>
                        {statusMsg}
                    </div>
                )}

                <form onSubmit={handleSave} className="p-4 sm:p-6 overflow-y-auto flex-1 space-y-4">
                    {/* HARDWARE PRINTER SELECTOR */}
                    <div className="p-3.5 rounded-xl bg-slate-950/80 border border-amber-500/30 space-y-2.5">
                        <div className="flex items-center justify-between">
                            <label className="text-xs font-bold text-amber-300 flex items-center gap-1.5">
                                <span className="w-2 h-2 rounded-full bg-amber-400"></span>
                                Target Printer Fisik Windows
                            </label>
                            <span className="text-[10px] text-slate-400 font-mono">USB / Spooler</span>
                        </div>

                        <select
                            name="windows_printer_name"
                            value={settings.windows_printer_name || 'Putian POS Thermal 2'}
                            onChange={handleChange}
                            className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-mono focus:border-amber-400">
                            {installedPrinters.map(p => (
                                <option key={p} value={p}>
                                    {p} {p.includes('Putian') ? '★ (Rekomendasi / Terdeteksi)' : ''}
                                </option>
                            ))}
                        </select>

                        <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer pt-1">
                            <input
                                type="checkbox"
                                name="direct_hardware_print"
                                checked={settings.direct_hardware_print}
                                onChange={handleChange}
                                className="rounded border-slate-700 bg-slate-900 text-amber-500 focus:ring-amber-500"
                            />
                            <span className="font-semibold text-white">
                                Aktifkan Cetak Langsung ke Hardware (Bypass Dialog / Auto Silent Print)
                            </span>
                        </label>
                        <p className="text-[10px] text-slate-400">
                            Struk akan dikirim langsung ke mesin Putian POS di latar belakang setiap kali order selesai.
                        </p>
                    </div>

                    {/* Paper Size */}
                    <div>
                        <label className="block text-xs font-bold text-slate-300 mb-1.5">Ukuran Kertas Thermal</label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className={`flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all ${settings.paper_size === '58mm' ? 'bg-rose-500/10 border-rose-500 text-white' : 'bg-slate-950 border-slate-800 text-slate-400'}`}>
                                <input
                                    type="radio"
                                    name="paper_size"
                                    value="58mm"
                                    checked={settings.paper_size === '58mm'}
                                    onChange={handleChange}
                                    className="text-rose-600 focus:ring-rose-500"
                                />
                                <div>
                                    <div className="text-xs font-bold">58mm (Putian POS-58)</div>
                                    <div className="text-[10px] opacity-75">32 Kolom Karakter</div>
                                </div>
                            </label>

                            <label className={`flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all ${settings.paper_size === '80mm' ? 'bg-rose-500/10 border-rose-500 text-white' : 'bg-slate-950 border-slate-800 text-slate-400'}`}>
                                <input
                                    type="radio"
                                    name="paper_size"
                                    value="80mm"
                                    checked={settings.paper_size === '80mm'}
                                    onChange={handleChange}
                                    className="text-rose-600 focus:ring-rose-500"
                                />
                                <div>
                                    <div className="text-xs font-bold">80mm (Putian POS-80)</div>
                                    <div className="text-[10px] opacity-75">48 Kolom Karakter</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {/* Store Name & Tagline */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Nama Toko / Resto</label>
                            <input
                                type="text"
                                name="store_name"
                                value={settings.store_name}
                                onChange={handleChange}
                                required
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Slogan / Tagline</label>
                            <input
                                type="text"
                                name="store_tagline"
                                value={settings.store_tagline || ''}
                                onChange={handleChange}
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white"
                            />
                        </div>
                    </div>

                    {/* Address & Phone */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">Alamat Outlet</label>
                            <input
                                type="text"
                                name="store_address"
                                value={settings.store_address}
                                onChange={handleChange}
                                required
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">No. Telepon / WA</label>
                            <input
                                type="text"
                                name="store_phone"
                                value={settings.store_phone}
                                onChange={handleChange}
                                required
                                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white"
                            />
                        </div>
                    </div>

                    {/* Footer text */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">Catatan Kaki Struk (Footer)</label>
                        <textarea
                            name="receipt_footer"
                            rows="2"
                            value={settings.receipt_footer || ''}
                            onChange={handleChange}
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono"
                        />
                    </div>

                    {/* Checkboxes */}
                    <div className="space-y-2 pt-2 border-t border-slate-800">
                        <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input
                                type="checkbox"
                                name="auto_print_cashier"
                                checked={settings.auto_print_cashier}
                                onChange={handleChange}
                                className="rounded border-slate-700 bg-slate-950 text-rose-600 focus:ring-rose-500"
                            />
                            <span>Cetak otomatis struk kasir saat checkout selesai</span>
                        </label>
                        <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input
                                type="checkbox"
                                name="cut_paper"
                                checked={settings.cut_paper}
                                onChange={handleChange}
                                className="rounded border-slate-700 bg-slate-950 text-rose-600 focus:ring-rose-500"
                            />
                            <span>Kirim perintah potong kertas otomatis (Auto-Cutter GS V)</span>
                        </label>
                        <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input
                                type="checkbox"
                                name="open_cash_drawer"
                                checked={settings.open_cash_drawer}
                                onChange={handleChange}
                                className="rounded border-slate-700 bg-slate-950 text-rose-600 focus:ring-rose-500"
                            />
                            <span>Tendang laci uang tunai (Cash Drawer Kick ESC p) saat bayar Cash</span>
                        </label>
                    </div>

                    <div className="pt-2">
                        <button
                            type="button"
                            onClick={handleTestPrint}
                            className="w-full py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-amber-300 border border-amber-500/20 transition-all flex items-center justify-center gap-2">
                            <svg className="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Tes Cetak Langsung ke Putian POS Thermal
                        </button>
                    </div>

                    <div className="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white">
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-lg shadow-rose-950/50">
                            {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
