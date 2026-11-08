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
 * Convert Base64 ESC/POS payload to Uint8Array
 */
function base64ToUint8Array(base64Bytes) {
    const binaryString = atob(base64Bytes);
    const len = binaryString.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes;
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Direct print over WebUSB API (Works on macOS Ventura, Windows, Linux, ChromeOS, Android via USB cable)
 */
export async function printDirectWebUsb(base64Bytes, allowPrompt = true) {
    if (!('usb' in navigator)) {
        throw new Error('WebUSB API tidak didukung di browser ini. Gunakan Google Chrome / Microsoft Edge / Brave.');
    }

    const rawBytes = base64ToUint8Array(base64Bytes);

    // Check previously paired USB devices first so user doesn't have to re-select every time
    let devices = await navigator.usb.getDevices();
    let device = devices.find(d =>
        d.configuration?.interfaces?.some(iface =>
            iface.alternates?.some(alt => alt.interfaceClass === 7 || alt.endpoints?.some(ep => ep.direction === 'out' && ep.type === 'bulk'))
        )
    ) || devices[0];

    if (!device) {
        if (!allowPrompt) {
            throw new Error('Belum ada printer USB yang dipasangkan di browser.');
        }
        device = await navigator.usb.requestDevice({ filters: [] });
    }

    await device.open();
    if (device.configuration === null) {
        await device.selectConfiguration(1);
    }

    // Find interface with Bulk OUT endpoint (prefer USB Printer Class 7)
    let targetInterfaceNumber = null;
    let targetAlternateSetting = 0;
    let targetEndpointNumber = null;

    const interfaces = device.configuration.interfaces || [];
    for (const iface of interfaces) {
        for (const alt of iface.alternates) {
            const outEp = alt.endpoints?.find(ep => ep.direction === 'out' && ep.type === 'bulk')
                || alt.endpoints?.find(ep => ep.direction === 'out');
            if (outEp) {
                targetInterfaceNumber = iface.interfaceNumber;
                targetAlternateSetting = alt.alternateSetting;
                targetEndpointNumber = outEp.endpointNumber;
                if (alt.interfaceClass === 7) break;
            }
        }
        if (targetInterfaceNumber !== null && targetEndpointNumber !== null) {
            break;
        }
    }

    if (targetInterfaceNumber === null || targetEndpointNumber === null) {
        await device.close();
        throw new Error('Endpoint output USB tidak ditemukan pada perangkat ini.');
    }

    await device.claimInterface(targetInterfaceNumber);
    if (targetAlternateSetting !== 0) {
        try {
            await device.selectAlternateInterface(targetInterfaceNumber, targetAlternateSetting);
        } catch (e) {
            // ignore if default alternate is already active
        }
    }

    // 1. Send ESC @ (0x1B 0x40) hardware reset in its own packet and wait 60ms
    // so the thermal printer microcontroller finishes resetting before receiving header text
    const initCmd = new Uint8Array([0x1B, 0x40]);
    await device.transferOut(targetEndpointNumber, initCmd);
    await sleep(60);

    // Strip leading ESC @ from payload if present so it doesn't reset a second time mid-stream
    let startOffset = 0;
    if (rawBytes.length >= 2 && rawBytes[0] === 0x1B && rawBytes[1] === 0x40) {
        startOffset = 2;
    }
    const payload = rawBytes.slice(startOffset);

    // 2. Send in 64-byte chunks (USB 2.0 Full-Speed wMaxPacketSize) with 10ms pacing
    // to prevent 58mm thermal printer buffer overflow during heavy head heating
    const chunkSize = 64;
    for (let offset = 0; offset < payload.length; offset += chunkSize) {
        const chunk = payload.slice(offset, offset + chunkSize);
        await device.transferOut(targetEndpointNumber, chunk);
        await sleep(10);
    }

    // Wait briefly before releasing USB interface so final line feeds complete
    await sleep(80);

    try {
        await device.releaseInterface(targetInterfaceNumber);
    } catch (e) {
        // ignore
    }
    await device.close();

    const printerName = [device.manufacturerName, device.productName].filter(Boolean).join(' ') || 'USB Thermal Printer';
    return {
        success: true,
        method: 'web_usb',
        printer: printerName,
        message: `Struk berhasil dicetak via WebUSB (${printerName})!`,
    };
}

/**
 * Direct print over Web Serial API or WebUSB API (Universal Cross-OS USB Thermal Printing)
 */
export async function printDirectWebSerial(base64Bytes) {
    // 1. Try WebUSB first (Standard for USB Thermal Printers on macOS Ventura, Windows, Linux)
    if ('usb' in navigator) {
        try {
            return await printDirectWebUsb(base64Bytes, true);
        } catch (usbErr) {
            console.warn('WebUSB fallback to WebSerial:', usbErr);
            // If user explicitly cancelled the picker, only fall back if serial is available
            if (!('serial' in navigator)) {
                throw usbErr;
            }
        }
    }

    // 2. Fallback to Web Serial API (for USB-to-Serial CH340/PL2303 thermal printers)
    if (!('serial' in navigator)) {
        throw new Error('WebUSB / Web Serial API tidak didukung di browser ini. Gunakan Google Chrome / Microsoft Edge.');
    }

    try {
        const ports = await navigator.serial.getPorts();
        const port = ports.length > 0 ? ports[0] : await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 }); // Default baud rate for POS thermal printers (XP-58, POS-80)

        const rawBytes = base64ToUint8Array(base64Bytes);
        const writer = port.writable.getWriter();

        // Send ESC @ reset first and wait 60ms
        await writer.write(new Uint8Array([0x1B, 0x40]));
        await sleep(60);

        let startOffset = 0;
        if (rawBytes.length >= 2 && rawBytes[0] === 0x1B && rawBytes[1] === 0x40) {
            startOffset = 2;
        }
        const payload = rawBytes.slice(startOffset);

        const chunkSize = 64;
        for (let offset = 0; offset < payload.length; offset += chunkSize) {
            await writer.write(payload.slice(offset, offset + chunkSize));
            await sleep(10);
        }

        await sleep(80);
        writer.releaseLock();
        await port.close();

        return {
            success: true,
            method: 'web_serial',
            printer: 'USB Serial Thermal Printer',
            message: 'Data ESC/POS berhasil dikirim ke printer thermal USB Serial!',
        };
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
