const express = require('express');
const cors = require('cors');
const QRCode = require('qrcode');
const qrcodeTerminal = require('qrcode-terminal');
const { Client, LocalAuth } = require('whatsapp-web.js');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3099;

app.use(cors());
app.use(express.json());

// Global state tracking
let qrCodeData = null;
let qrCodeDataURL = null;
let clientStatus = 'INITIALIZING'; // INITIALIZING, QR_READY, AUTHENTICATED, READY, DISCONNECTED
let clientInfo = null;
let lastError = null;

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

// Helper to format Turkish phone numbers to WhatsApp ID format (e.g. 905XXXXXXXXX@c.us)
function formatPhoneNumber(phoneStr) {
    if (!phoneStr) return null;
    let clean = phoneStr.replace(/\D/g, '');
    if (!clean) return null;

    // Handle Turkish number variations
    if (clean.length === 10 && clean.startsWith('5')) {
        clean = '90' + clean;
    } else if (clean.length === 11 && clean.startsWith('05')) {
        clean = '90' + clean.substring(1);
    } else if (clean.length === 12 && clean.startsWith('905')) {
        // already correct
    } else if (clean.length > 10 && !clean.startsWith('90')) {
        clean = '90' + clean;
    }

    return clean + '@c.us';
}

console.log('Initializing WhatsApp Client...');

const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: path.join(__dirname, '.wwebjs_auth')
    }),
    puppeteer: {
        headless: true,
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu',
            '--disable-blink-features=AutomationControlled'
        ]
    }
});

client.on('qr', async (qr) => {
    clientStatus = 'QR_READY';
    qrCodeData = qr;
    try {
        qrCodeDataURL = await QRCode.toDataURL(qr);
    } catch (err) {
        console.error('Failed to generate QR Data URL:', err);
    }
    console.log('\n--- WHATSAPP WEB QR KODU HAZIR ---');
    console.log('Terminalden de taratabilirsiniz veya http://localhost:3000/qr adresine gidiniz:\n');
    qrcodeTerminal.generate(qr, { small: true });
});

client.on('authenticated', () => {
    clientStatus = 'AUTHENTICATED';
    qrCodeData = null;
    qrCodeDataURL = null;
    console.log('WhatsApp Web Oturumu Doğrulandı (AUTHENTICATED)!');
});

client.on('ready', () => {
    clientStatus = 'READY';
    qrCodeData = null;
    qrCodeDataURL = null;
    clientInfo = client.info;
    console.log('WhatsApp İstemcisi Kullanıma Hazır (READY)!');
    if (clientInfo) {
        console.log(`Bağlı Hesap: ${clientInfo.pushname || 'Olifa Temizlik'} (${clientInfo.wid.user})`);
    }
});

client.on('auth_failure', (msg) => {
    clientStatus = 'DISCONNECTED';
    lastError = 'Kimlik doğrulama hatası: ' + msg;
    console.error('WhatsApp Kimlik Doğrulama Hatası:', msg);
});

client.on('disconnected', (reason) => {
    clientStatus = 'DISCONNECTED';
    lastError = 'Bağlantı kesildi: ' + reason;
    qrCodeData = null;
    qrCodeDataURL = null;
    console.log('WhatsApp Bağlantısı Kesildi:', reason);
});

client.initialize().catch(err => {
    console.error('Client initialization failed:', err);
    clientStatus = 'DISCONNECTED';
    lastError = err.message;
});

// --- API Endpoints ---

// Status endpoint
app.get('/status', (req, res) => {
    res.json({
        status: clientStatus,
        qr: qrCodeDataURL,
        info: clientInfo ? {
            name: clientInfo.pushname,
            phone: clientInfo.wid ? clientInfo.wid.user : null
        } : null,
        lastError: lastError
    });
});

// HTML page for QR code viewing
app.get('/qr', (req, res) => {
    if (clientStatus === 'READY') {
        return res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>WhatsApp Bağlantısı Hazır</title>
                <style>
                    body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: #f0fdf4; color: #166534; margin: 0; }
                    .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; }
                    .icon { font-size: 64px; color: #22c55e; margin-bottom: 20px; }
                    h1 { margin: 0 0 10px 0; font-size: 24px; }
                    p { color: #4b5563; font-size: 15px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="icon">✅</div>
                    <h1>WhatsApp Zaten Bağlı</h1>
                    <p>Olifa Temizlik Şirketi WhatsApp Web servisi aktif ve çalışıyor.</p>
                </div>
            </body>
            </html>
        `);
    }

    if (clientStatus === 'QR_READY' && qrCodeDataURL) {
        return res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>WhatsApp QR Kod Taraması</title>
                <meta http-equiv="refresh" content="15">
                <style>
                    body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
                    .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center; max-width: 440px; width: 100%; }
                    img { border-radius: 12px; border: 2px solid #e2e8f0; padding: 10px; background: #fff; margin: 20px 0; width: 260px; height: 260px; }
                    h1 { margin: 0 0 10px 0; font-size: 22px; color: #0f172a; }
                    p { color: #64748b; font-size: 14px; line-height: 1.5; margin-bottom: 5px; }
                    .steps { text-align: left; background: #f1f5f9; padding: 15px 20px; border-radius: 12px; margin-top: 20px; font-size: 13px; color: #334155; }
                    .steps ol { margin: 0; padding-left: 20px; }
                    .steps li { margin-bottom: 5px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h1>Olifa WhatsApp Bağlantısı</h1>
                    <p>Lütfen WhatsApp mobil uygulamanızdan bu QR kodu taratın.</p>
                    <img src="${qrCodeDataURL}" alt="WhatsApp QR Code" />
                    <div class="steps">
                        <strong>Nasıl Taranır?</strong>
                        <ol>
                            <li>Telefonunuzda WhatsApp'ı açın</li>
                            <li>Menü (⋮) veya Ayarlar'a dokunun</li>
                            <li><strong>Bağlı Cihazlar</strong> seçeneğine tıklayın</li>
                            <li><strong>Cihaz Bağla</strong> butonuna basıp ekranı taratın</li>
                        </ol>
                    </div>
                </div>
            </body>
            </html>
        `);
    }

    return res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>WhatsApp Durumu</title>
            <meta http-equiv="refresh" content="5">
            <style>
                body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: #f8fafc; color: #1e293b; margin: 0; }
                .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; }
                p { color: #64748b; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>Olifa WhatsApp Servisi Başlatılıyor...</h2>
                <p>Mevcut Durum: <strong>${clientStatus}</strong></p>
                <p>Sayfa otomatik yenileniyor...</p>
            </div>
        </body>
        </html>
    `);
});

// Helper for delay
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

// Message queue mechanism to ensure 10 seconds delay between ANY outgoing message
const messageQueue = [];
let isProcessingQueue = false;

async function processMessageQueue() {
    if (isProcessingQueue) return;
    isProcessingQueue = true;

    while (messageQueue.length > 0) {
        const task = messageQueue.shift();
        try {
            let targetChatId = task.chatId;
            try {
                const numberDetails = await client.getNumberId(targetChatId);
                if (numberDetails && numberDetails._serialized) {
                    targetChatId = numberDetails._serialized;
                }
            } catch (numErr) {
                // Keep original chatId as fallback
            }

            await client.sendMessage(targetChatId, task.message);
            console.log(`[Queue] WhatsApp mesajı başarıyla gönderildi: ${task.recipientName} (${targetChatId})`);
        } catch (err) {
            console.error(`[Queue] Mesaj gönderme hatası (${task.recipientName} - ${task.chatId}):`, err.message);
        }

        // If there are more messages waiting in queue, wait 10 seconds before sending the next one
        if (messageQueue.length > 0) {
            console.log(`[Queue] Anti-ban koruması: Sonraki mesaj için 10 saniye bekleniyor...`);
            await delay(10000);
        }
    }

    isProcessingQueue = false;
}

// Deduplication cache to prevent duplicate notifications for the same booking within 60 seconds
const recentlyProcessedBookings = new Set();

// Endpoint to send WhatsApp notifications for a booking
app.post('/send-booking', async (req, res) => {
    if (clientStatus !== 'READY') {
        return res.status(503).json({
            success: false,
            message: 'WhatsApp istemcisi hazır değil. Mevcut durum: ' + clientStatus
        });
    }

    try {
        const { booking_id, customer, booking_date, time_slot, service_name, employees } = req.body;

        if (!customer || !customer.phone) {
            return res.status(400).json({ success: false, message: 'Müşteri telefon numarası eksik.' });
        }

        // Deduplication check
        if (booking_id) {
            if (recentlyProcessedBookings.has(booking_id)) {
                console.log(`[Dedup] Randevu #${booking_id} için son 60 saniye içinde bildirim tetiklenmişti. Çift gönderim engellendi.`);
                return res.json({
                    success: true,
                    message: 'Bu randevu için yakın zamanda bildirim gönderildiği için çift gönderim engellendi.'
                });
            }
            recentlyProcessedBookings.add(booking_id);
            setTimeout(() => {
                recentlyProcessedBookings.delete(booking_id);
            }, 60000);
        }

        const formattedDateStr = formatDate(booking_date);
        const formattedSlotStr = formatTimeSlot(time_slot);
        const serviceTitle = service_name || 'Temizlik Hizmeti';

        const employeeNamesList = (employees && employees.length > 0)
            ? employees.map(e => e.name).join(', ')
            : 'Genel Temizlik Ekibimiz';

        let queuedCount = 0;

        // 1. Add Customer Message to Queue
        const customerChatId = formatPhoneNumber(customer.phone);
        if (customerChatId) {
            const customerMsg = 
`Sayın *${customer.name || 'Müşterimiz'}*,

Olifa Temizlik Şirketi'ni tercih ettiğiniz için teşekkür ederiz! 🌸

Randevunuz başarıyla onaylanmıştır:
📅 *Tarih:* ${formattedDateStr}
⏰ *Saat:* ${formattedSlotStr}
🧹 *Hizmet:* ${serviceTitle}
👷 *Temizliğe Gelecek Personeller:* ${employeeNamesList}

Sorularınız veya değişiklik talepleriniz için bu hat üzerinden bizimle iletişime geçebilirsiniz. İyi günler dileriz! ✨`;

            messageQueue.push({
                chatId: customerChatId,
                recipientName: `Müşteri: ${customer.name}`,
                message: customerMsg
            });
            queuedCount++;
        }

        // 2. Add Employee Messages to Queue
        if (employees && Array.isArray(employees) && employees.length > 0) {
            for (const emp of employees) {
                if (!emp.phone) continue;
                const empChatId = formatPhoneNumber(emp.phone);
                if (!empChatId) continue;

                const employeeMsg = 
`Merhaba *${emp.name}*,

Yeni bir temizlik görevi atandı! 🧹

📅 *Tarih:* ${formattedDateStr}
⏰ *Saat Aralığı:* ${formattedSlotStr}
👤 *Müşteri:* ${customer.name || 'Belirtilmedi'}
📞 *Müşteri Tel:* ${customer.phone || 'Belirtilmedi'}
📍 *Adres:* ${customer.address || 'Belirtilmedi'}
🧹 *Hizmet:* ${serviceTitle}

Lütfen randevu saatinde adreste olmaya özen gösteriniz. İyi çalışmalar!`;

                messageQueue.push({
                    chatId: empChatId,
                    recipientName: `Çalışan: ${emp.name}`,
                    message: employeeMsg
                });
                queuedCount++;
            }
        }

        // Start processing queue in background
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
    console.log(`Olifa WhatsApp Microservice running on http://localhost:${PORT}`);
});
