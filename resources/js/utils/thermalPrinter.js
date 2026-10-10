/**
 * Thermal Printer Utilities for ESC/POS, Browser Print, and Web Serial API
 */

/**
 * Print receipt using Browser Print with 58mm / 80mm roll formatting
 */
export function printReceiptWindow(plainText, paperSize = '58mm') {
    const printSection = document.getElementById('print-section');
    if (!printSection) return;

    const widthPx = paperSize === '80mm' ? '280px' : '220px';
    const fontSize = paperSize === '80mm' ? '12px' : '10.5px';

    printSection.innerHTML = `
        <div style="
            width: ${widthPx};
            max-width: 100%;
            margin: 0 auto;
            padding: 4px 2px 0px 2px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
            font-size: ${fontSize};
            line-height: 1.25;
            color: #000;
            background: #fff;
            white-space: pre;
            overflow-wrap: normal;
        ">${escapeHtml(plainText.trimEnd())}</div>
    `;

    printSection.classList.remove('hidden');

    setTimeout(() => {
        window.print();
        setTimeout(() => {
            printSection.classList.add('hidden');
            printSection.innerHTML = '';
        }, 1000);
    }, 150);
}

/**
 * Download raw binary ESC/POS file for physical printer spooling
 */
export function downloadRawEscPos(orderId, orderNumber) {
    const url = `/api/orders/${orderId}/escpos-download`;
    const link = document.createElement('a');
    link.href = url;
    link.download = `receipt-${orderNumber}.bin`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Direct print over Web Serial API (Chrome/Edge supporting Web Serial to USB Thermal Printer)
 */
export async function printDirectWebSerial(base64Bytes) {
    if (!('serial' in navigator)) {
        throw new Error('Web Serial API tidak didukung di browser ini. Gunakan Google Chrome / Microsoft Edge.');
    }

    try {
        const port = await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 }); // Default baud rate for POS thermal printers (XP-58, POS-80)

        const binaryString = atob(base64Bytes);
        const len = binaryString.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }

        const writer = port.writable.getWriter();
        await writer.write(bytes);
        writer.releaseLock();
        await port.close();

        return { success: true, message: 'Data ESC/POS berhasil dikirim ke printer thermal USB!' };
    } catch (err) {
        console.error('Web Serial Print Error:', err);
        throw err;
    }
}

function escapeHtml(string) {
    const entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    };
    return String(string).replace(/[&<>"']/g, (s) => entityMap[s]);
}
