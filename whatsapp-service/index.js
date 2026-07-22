const express = require('express');
const cors = require('cors');
const QRCode = require('qrcode');
const path = require('path');
const pino = require('pino');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');

const app = express();
const PORT = process.env.PORT || 3099;

app.use(cors());
app.use(express.json());

// Global State
let sock = null;
let qrCodeDataURL = null;
let clientStatus = 'INITIALIZING'; // INITIALIZING, QR_READY, READY, DISCONNECTED, OFFLINE
let clientInfo = null;
let lastError = null;

// Anti-ban message queue (10s delay)
const messageQueue = [];
let isProcessingQueue = false;
const recentlyProcessedBookings = new Map();

// Helper to format Turkish time slot
function formatTimeSlot(slot) {
    if (!slot) return 'Belirtilmedi';
    switch (slot) {
        case '08-12': return '08:00 - 12:00 (Yarım Gün - Sabah)';
        case '13-17': return '13:00 - 17:00 (Yarım Gün - Öğleden Sonra)';
        case '08-17': return '08:00 - 17:00 (Tam Gün)';
        default: return slot;
    }
}

// Helper to format date (YYYY-MM-DD -> DD.MM.YYYY)
function formatDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    return dateStr;
}

// Helper to format Turkish phone numbers to Baileys JID format (905XXXXXXXXX@s.whatsapp.net)
function formatPhoneNumber(phoneStr) {
    if (!phoneStr) return null;
    let clean = phoneStr.replace(/\D/g, '');
    if (!clean) return null;

    if (clean.length === 10 && clean.startsWith('5')) {
        clean = '90' + clean;
    } else if (clean.length === 11 && clean.startsWith('05')) {
        clean = '90' + clean.substring(1);
    } else if (clean.length === 12 && clean.startsWith('905')) {
        // already correct
    } else if (clean.length > 10 && !clean.startsWith('90')) {
        clean = '90' + clean;
    }

    return clean + '@s.whatsapp.net';
}

console.log('Initializing Baileys WhatsApp Engine (Pure Node.js Sockets)...');

async function startBaileys() {
    try {
        const authFolder = path.join(__dirname, 'baileys_auth_info');
        const { state, saveCreds } = await useMultiFileAuthState(authFolder);
        
        let version = [2, 3000, 1015901307];
        try {
            const versionRes = await fetchLatestBaileysVersion();
            if (versionRes && versionRes.version) version = versionRes.version;
        } catch(e) {
            console.log('Using default Baileys version');
        }

        sock = makeWASocket({
            version,
            auth: state,
            logger: pino({ level: 'silent' }),
            browser: ['OLiFA Temizlik', 'Chrome', '1.0.0'],
            generateHighQualityLinkPreview: false
        });

        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                clientStatus = 'QR_READY';
                try {
                    qrCodeDataURL = await QRCode.toDataURL(qr);
                    console.log('--- BAILEYS QR KODU OLUŞTURULDU ---');
                } catch (err) {
                    console.error('QR Data URL üretilemedi:', err);
                }
            }

            if (connection === 'open') {
                clientStatus = 'READY';
                qrCodeDataURL = null;
                lastError = null;
                const user = sock.user;
                const cleanPhone = user ? (user.id || '').split(':')[0].split('@')[0] : '';
                clientInfo = {
                    name: (user && user.name) ? user.name : 'OLiFA WhatsApp',
                    phone: cleanPhone
                };
                console.log('--- BAILEYS WHATSAPP BAŞARIYLA BAĞLANDI! ---', clientInfo);
            }

            if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = (statusCode !== DisconnectReason.loggedOut);
                console.log('Baileys bağlantı kapandı. Reconnect edilecek mi:', shouldReconnect);
                
                if (shouldReconnect) {
                    clientStatus = 'INITIALIZING';
                    setTimeout(startBaileys, 3000);
                } else {
                    clientStatus = 'DISCONNECTED';
                    qrCodeDataURL = null;
                }
            }
        });

    } catch (err) {
        console.error('Baileys başlatma hatası:', err);
        clientStatus = 'OFFLINE';
        lastError = err.message;
    }
}

startBaileys();

// Process Message Queue
async function processMessageQueue() {
    if (isProcessingQueue) return;
    isProcessingQueue = true;

    while (messageQueue.length > 0) {
        const item = messageQueue.shift();
        const { jid, recipientName, message } = item;

        try {
            if (clientStatus === 'READY' && sock) {
                console.log(`Sending message to ${recipientName} (${jid})...`);
                await sock.sendMessage(jid, { text: message });
                console.log(`Successfully sent to ${recipientName}`);
            } else {
                console.log(`Skipping message to ${recipientName}: WhatsApp not ready (${clientStatus})`);
            }
        } catch (err) {
            console.error(`Failed to send message to ${recipientName}:`, err);
        }

        // Wait 10 seconds anti-ban delay
        if (messageQueue.length > 0) {
            await new Promise(resolve => setTimeout(resolve, 10000));
        }
    }

    isProcessingQueue = false;
}

// 1. Connection Status API
app.get('/status', (req, res) => {
    res.json({
        status: clientStatus,
        qr: qrCodeDataURL,
        info: clientInfo,
        lastError: lastError,
        engine: 'Baileys Pure JS Sockets'
    });
});

// 1.5. Send Admin Alert Endpoint
app.post('/send-admin-alert', async (req, res) => {
    try {
        if (clientStatus !== 'READY' || !sock) {
            return res.status(503).json({ success: false, message: 'WhatsApp servisi henüz bağlı değil.' });
        }
        const { phone, message } = req.body;
        if (!phone || !message) {
            return res.status(400).json({ success: false, message: 'Telefon ve mesaj alanları zorunludur.' });
        }
        const adminJid = formatPhoneNumber(phone);
        if (adminJid) {
            console.log(`Sending Admin Alert to ${phone}...`);
            await sock.sendMessage(adminJid, { text: message });
            console.log(`Successfully sent Admin Alert to ${phone}`);
        }
        return res.json({ success: true, message: 'Yönetici uyarısı gönderildi.' });
    } catch (err) {
        console.error('Error in /send-admin-alert:', err);
        return res.status(500).json({ success: false, message: err.message });
    }
});

// 2. Send Booking Notification Queue Endpoint
app.post('/send-booking', async (req, res) => {
    try {
        if (clientStatus !== 'READY' || !sock) {
            return res.status(503).json({
                success: false,
                message: 'WhatsApp servisi henüz bağlı değil.'
            });
        }

        const { booking_id, customer, booking_date, time_slot, service_name, employees } = req.body;

        if (!customer || !customer.phone) {
            return res.status(400).json({ success: false, message: 'Müşteri telefon bilgisi eksik.' });
        }

        // 60-second deduplication
        if (booking_id) {
            const now = Date.now();
            const lastProcessed = recentlyProcessedBookings.get(booking_id);
            if (lastProcessed && (now - lastProcessed < 60000)) {
                return res.json({
                    success: true,
                    message: 'Bu rezervasyon için mesajlar son 60 saniye içinde zaten iletildi.'
                });
            }
            recentlyProcessedBookings.set(booking_id, now);
        }

        const customerJid = formatPhoneNumber(customer.phone);
        const formattedDateStr = formatDate(booking_date);
        const formattedSlotStr = formatTimeSlot(time_slot);
        const serviceTitle = service_name || 'Temizlik Hizmeti';

        let empNamesList = [];
        if (Array.isArray(employees) && employees.length > 0) {
            empNamesList = employees.map(e => e.name).filter(Boolean);
        }
        const assignedTeamStr = empNamesList.length > 0 ? empNamesList.join(', ') : 'Ekip Atanıyor';

        let queuedCount = 0;

        // Customer Message
        if (customerJid) {
            const customerMsg = `✨ *OLiFA TEMİZLİK BİLDİRİMİ*\n\nSayın *${customer.name}*,\nRezervasyonunuz başarıyla onaylanmıştır.\n\n🧹 *Hizmet:* ${serviceTitle}\n📅 *Tarih:* ${formattedDateStr}\n⏰ *Saat Dilimi:* ${formattedSlotStr}\n👷‍♂️ *Görevli Ekip:* ${assignedTeamStr}\n\nBizi tercih ettiğiniz için teşekkür ederiz!`;

            messageQueue.push({
                jid: customerJid,
                recipientName: `Müşteri: ${customer.name}`,
                message: customerMsg
            });
            queuedCount++;
        }

        // Employees Messages
        if (Array.isArray(employees)) {
            for (const emp of employees) {
                if (!emp.phone) continue;
                const empJid = formatPhoneNumber(emp.phone);
                if (!empJid) continue;

                const employeeMsg = `📋 *YENİ GÖREV ATAMASI (OLiFA TEMİZLİK)*\n\nSayın *${emp.name}*,\nYeni bir temizlik görevi atanmıştır:\n\n📅 *Tarih:* ${formattedDateStr}\n⏰ *Saat Aralığı:* ${formattedSlotStr}\n👤 *Müşteri:* ${customer.name || 'Belirtilmedi'}\n📞 *Müşteri Tel:* ${customer.phone || 'Belirtilmedi'}\n📍 *Adres:* ${customer.address || 'Belirtilmedi'}\n🧹 *Hizmet:* ${serviceTitle}\n\nLütfen randevu saatinde adreste olmaya özen gösteriniz. İyi çalışmalar!`;

                messageQueue.push({
                    jid: empJid,
                    recipientName: `Çalışan: ${emp.name}`,
                    message: employeeMsg
                });
                queuedCount++;
            }
        }

        processMessageQueue();

        return res.json({
            success: true,
            message: 'Mesajlar 10 saniye aralıklı anti-ban koruma kuyruğuna eklendi.',
            queued_count: queuedCount
        });

    } catch (err) {
        console.error('Error in /send-booking:', err);
        return res.status(500).json({ success: false, message: err.message });
    }
});

app.listen(PORT, () => {
    console.log(`Olifa Baileys WhatsApp Microservice running on http://localhost:${PORT}`);
});
