import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import axios from 'axios';

// Import apps
import PosApp from './components/pos/PosApp';
import KdsApp from './components/kds/KdsApp';
import TransactionsApp from './components/transactions/TransactionsApp';
import AnalyticsApp from './components/analytics/AnalyticsApp';
import CmsApp from './components/cms/CmsApp';

// Setup global Axios headers
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mount POS App
    const posRootEl = document.getElementById('pos-root');
    if (posRootEl) {
        const user = parseDataAttribute(posRootEl, 'user');
        const settings = parseDataAttribute(posRootEl, 'settings');
        createRoot(posRootEl).render(
            <React.StrictMode>
                <PosApp user={user} initialSettings={settings} />
            </React.StrictMode>
        );
    }

    // 2. Mount Kitchen Display System (KDS) App
    const kdsRootEl = document.getElementById('kds-root');
    if (kdsRootEl) {
        const user = parseDataAttribute(kdsRootEl, 'user');
        const settings = parseDataAttribute(kdsRootEl, 'settings');
        createRoot(kdsRootEl).render(
            <React.StrictMode>
                <KdsApp user={user} initialSettings={settings} />
            </React.StrictMode>
        );
    }

    // 3. Mount Transactions Dashboard App
    const transactionsRootEl = document.getElementById('transactions-root');
    if (transactionsRootEl) {
        const user = parseDataAttribute(transactionsRootEl, 'user');
        const settings = parseDataAttribute(transactionsRootEl, 'settings');
        createRoot(transactionsRootEl).render(
            <React.StrictMode>
                <TransactionsApp user={user} initialSettings={settings} />
            </React.StrictMode>
        );
    }

    // 4. Mount Analytics App
    const analyticsRootEl = document.getElementById('analytics-root');
    if (analyticsRootEl) {
        const user = parseDataAttribute(analyticsRootEl, 'user');
        createRoot(analyticsRootEl).render(
            <React.StrictMode>
                <AnalyticsApp user={user} />
            </React.StrictMode>
        );
    }

    // 5. Mount Superadmin CMS App (Qurais Kitchen)
    const cmsRootEl = document.getElementById('cms-root');
    if (cmsRootEl) {
        const user = parseDataAttribute(cmsRootEl, 'user');
        const settings = parseDataAttribute(cmsRootEl, 'settings');
        const initialTab = cmsRootEl.getAttribute('data-initial-tab') || 'dashboard';
        createRoot(cmsRootEl).render(
            <React.StrictMode>
                <CmsApp user={user} initialSettings={settings} initialTab={initialTab} />
            </React.StrictMode>
        );
    }
});

function parseDataAttribute(element, attributeName) {
    try {
        const val = element.getAttribute(`data-${attributeName}`);
        return val ? JSON.parse(val) : null;
    } catch (e) {
        console.warn(`Failed parsing data-${attributeName}`, e);
        return null;
    }
}
