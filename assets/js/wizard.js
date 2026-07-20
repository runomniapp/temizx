// OLiFA Temizlik - Çok Adımlı Teklif Sihirbazı Kontrolcüsü (wizard.js)

let currentStep = 1;
let selectedCategory = null;
let selectedSubcategory = null;
let selectedPackage = null;
let selectedDate = null;
let selectedTimeSlot = null;
let personCount = 2;

// Takvim Tarih Hesaplamaları
let currentDateObj = new Date();
let displayYear = currentDateObj.getFullYear();
let displayMonth = currentDateObj.getMonth(); // 0-11

const monthsTr = [
    "Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", 
    "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"
];

const daysTr = ["Pzt", "Sal", "Çar", "Per", "Cum", "Cmt", "Paz"];

document.addEventListener("DOMContentLoaded", () => {
    initWizard();
    setupPhoneInput();
    setupFieldClearListeners();
});

function initWizard() {
    renderCategories();
    
    // Eğer parametreyle varsayılan kategori geldiyse seç
    if (typeof defaultCategoryId !== 'undefined' && defaultCategoryId > 0) {
        selectCategory(defaultCategoryId);
    }
    
    // Eğer parametreyle varsayılan paket geldiyse seç
    if (typeof defaultPackageId !== 'undefined' && defaultPackageId > 0) {
        selectPackage(defaultPackageId);
    }
    
    updateProgress();
}

/**
 * Telefon Giriş Formatlayıcı (+90 Sabit, 555-555-55-55 Maskesi)
 */
function setupPhoneInput() {
    const phoneInput = document.getElementById("c_phone");
    if (!phoneInput) return;
    
    phoneInput.placeholder = "555 555 55 55";
    phoneInput.maxLength = 13;
    
    phoneInput.addEventListener("input", (e) => {
        let value = phoneInput.value.replace(/\D/g, ""); // Sadece rakamları al
        
        // İlk hanenin 0 olmasını engelle veya temizle
        if (value.startsWith("0")) {
            value = value.substring(1);
        }
        
        // En fazla 10 hane
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        
        // Formatlama: 555 555 55 55
        let formatted = "";
        if (value.length > 0) {
            formatted += value.substring(0, 3);
        }
        if (value.length > 3) {
            formatted += " " + value.substring(3, 6);
        }
        if (value.length > 6) {
            formatted += " " + value.substring(6, 8);
        }
        if (value.length > 8) {
            formatted += " " + value.substring(8, 10);
        }
        
        phoneInput.value = formatted;
        clearFieldError(phoneInput);
    });
    
    // Rakamlar ve kontrol tuşları dışındakileri engelle
    phoneInput.addEventListener("keydown", (e) => {
        const allowedKeys = ["Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight", "Enter", "Control", "a", "c", "v", "x"];
        if (allowedKeys.includes(e.key) || (e.ctrlKey && ["a", "c", "v", "x"].includes(e.key.toLowerCase()))) return;
        if (!/\d/.test(e.key)) {
            e.preventDefault();
        }
    });
}

/**
 * Kategorileri ekrana çiz (Pill tasarımı)
 */
function renderCategories() {
    const grid = document.getElementById("categoryGrid");
    if (!grid) return;
    grid.innerHTML = "";
    
    categoriesData.forEach(cat => {
        const card = document.createElement("div");
        card.className = "service-pill-card";
        if (selectedCategory && selectedCategory.id === cat.id) {
            card.classList.add("selected");
        }
        card.onclick = () => selectCategory(cat.id);
        
        let iconHtml = '<i class="fa-solid fa-check"></i>';
        if (cat.icon === 'home') iconHtml = '<i class="fa-solid fa-house"></i>';
        else if (cat.icon === 'briefcase') iconHtml = '<i class="fa-solid fa-briefcase"></i>';
        else if (cat.icon === 'hammer') iconHtml = '<i class="fa-solid fa-trowel-bricks"></i>';
        else if (cat.icon === 'building') iconHtml = '<i class="fa-solid fa-hotel"></i>';
        else if (cat.icon === 'eye') iconHtml = '<i class="fa-solid fa-wand-magic-sparkles"></i>';
        else if (cat.icon === 'coffee') iconHtml = '<i class="fa-solid fa-couch"></i>';
        
        card.innerHTML = `
            <div class="service-pill-icon">
                ${iconHtml}
            </div>
            <div class="service-pill-name">${cat.name}</div>
        `;
        grid.appendChild(card);
    });
}

/**
 * Kategori seçildiğinde
 */
function selectCategory(id) {
    selectedCategory = categoriesData.find(c => c.id === id);
    document.getElementById("input_category_id").value = id;
    
    const grid = document.getElementById("categoryGrid");
    clearFieldError(grid);
    
    // State sıfırla
    selectedSubcategory = null;
    selectedPackage = null;
    selectedDate = null;
    selectedTimeSlot = null;
    personCount = 2;
    document.getElementById("input_subcategory_id").value = "";
    document.getElementById("input_package_id").value = "";
    document.getElementById("input_booking_date").value = "";
    document.getElementById("input_booking_time_slot").value = "";
    document.getElementById("input_person_count").value = "2";
    
    // Seçim durumlarını görsel olarak temizle
    document.querySelectorAll(".time-pill-btn").forEach(btn => btn.classList.remove("selected"));
    
    renderCategories();
    renderSubcategories();
    renderPackages();
    renderCalendar();
    updateLivePrice();
    
    // Seçim yapılınca otomatik 2. adıma geç
    setTimeout(() => {
        nextStep();
    }, 200);
}

/**
 * Alt kategorileri (Süre/m2) ekrana çiz (Pills şeklinde)
 */
function renderSubcategories() {
    const container = document.getElementById("subcategoriesContainer");
    const grid = document.getElementById("subcategoriesListPills");
    if (!container || !grid) return;
    grid.innerHTML = "";
    
    if (!selectedCategory || !selectedCategory.subcategories || selectedCategory.subcategories.length === 0) {
        container.style.display = "none";
        return;
    }
    
    container.style.display = "block";
    
    selectedCategory.subcategories.forEach(sub => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "package-pill-btn";
        if (selectedSubcategory && selectedSubcategory.id === sub.id) {
            btn.classList.add("selected");
        }
        btn.onclick = () => selectSubcategory(sub.id);
        
        btn.innerHTML = `${sub.name} (+${parseFloat(sub.price).toLocaleString('tr-TR')} ₺)`;
        grid.appendChild(btn);
    });
}

/**
 * Alt kategori seçildiğinde
 */
function selectSubcategory(id) {
    selectedSubcategory = selectedCategory.subcategories.find(s => s.id === id);
    document.getElementById("input_subcategory_id").value = id;
    renderSubcategories();
    updateLivePrice();
}

/**
 * Paketleri ekrana çiz (Small Pills)
 */
function renderPackages() {
    const grid = document.getElementById("packagesListPills");
    if (!grid) return;
    grid.innerHTML = "";
    
    // Her zaman "Tek Seferlik" seçeneği en başta bulunur
    const oneTimeBtn = document.createElement("button");
    oneTimeBtn.type = "button";
    oneTimeBtn.className = "package-pill-btn";
    if (!selectedPackage) {
        oneTimeBtn.classList.add("selected");
    }
    oneTimeBtn.onclick = () => selectPackage(null);
    oneTimeBtn.innerText = "Tek Seferlik Temizlik";
    grid.appendChild(oneTimeBtn);
    
    // Eğer kategoride abonelik aktifse paketleri listele
    if (selectedCategory && selectedCategory.is_subscription_active === 1 && selectedCategory.packages) {
        selectedCategory.packages.forEach(pkg => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "package-pill-btn";
            if (selectedPackage && selectedPackage.id === pkg.id) {
                btn.classList.add("selected");
            }
            btn.onclick = () => selectPackage(pkg.id);
            
            // İndirim oranı
            const discountPct = Math.round((1 - (parseFloat(pkg.discounted_price) / parseFloat(pkg.normal_price))) * 100);
            btn.innerHTML = `${pkg.name} (-%${discountPct})`;
            grid.appendChild(btn);
        });
    }
}

/**
 * Paket seçildiğinde
 */
function selectPackage(id) {
    if (id === null) {
        selectedPackage = null;
        document.getElementById("input_package_id").value = "";
    } else {
        selectedPackage = selectedCategory.packages.find(p => p.id === id);
        document.getElementById("input_package_id").value = id;
        
        // Paket detaylarını otomatik ata
        if (selectedPackage.time_slot) {
            selectedTimeSlot = selectedPackage.time_slot;
            document.getElementById("input_booking_time_slot").value = selectedTimeSlot;
            document.querySelectorAll(".time-pill-btn").forEach(btn => {
                if (btn.dataset.slot === selectedTimeSlot) {
                    btn.classList.add("selected");
                } else {
                    btn.classList.remove("selected");
                }
            });
        }
        if (selectedPackage.person_count) {
            personCount = parseInt(selectedPackage.person_count);
            document.getElementById("input_person_count").value = personCount;
        }
    }
    renderPackages();
    updateLivePrice();
}

/**
 * Zaman dilimi seçildiğinde
 */
function selectTimeSlot(slot) {
    if (selectedPackage) {
        alert("Seçtiğiniz abonelik paketi için zaman dilimi (" + (selectedPackage.time_slot === '08-17' ? 'Tam Gün' : 'Yarım Gün') + ") kilitlidir. Değiştirmek için önce paketi 'Tek Seferlik' olarak seçin.");
        return;
    }
    
    selectedTimeSlot = slot;
    document.getElementById("input_booking_time_slot").value = slot;
    
    const slots = document.getElementById("timeSlotsList");
    clearFieldError(slots);
    
    document.querySelectorAll(".time-pill-btn").forEach(btn => {
        if (btn.dataset.slot === slot) {
            btn.classList.add("selected");
        } else {
            btn.classList.remove("selected");
        }
    });
    
    updateLivePrice();
}

/**
 * Tekli standart fiyatı hesapla (Aboneliksiz fiyat)
 */
function calculateFlatPrice() {
    if (!selectedCategory) return 0;
    if (selectedCategory.pricing_type === 'discovery') {
        return 0;
    }
    
    const isHalfDay = (selectedTimeSlot === '08-12' || selectedTimeSlot === '13-17');
    
    let maxPerson = 1;
    let basePrice = 0;
    let extraPersonPrice = 0;
    
    if (selectedCategory.pricing_type === 'subcategory' && selectedSubcategory) {
        maxPerson = parseInt(selectedSubcategory.max_person || 1);
        basePrice = isHalfDay ? parseFloat(selectedSubcategory.half_day_price || 0) : parseFloat(selectedSubcategory.price || 0);
        extraPersonPrice = isHalfDay ? parseFloat(selectedSubcategory.person_half_price || 0) : parseFloat(selectedSubcategory.person_full_price || 0);
    } else {
        maxPerson = parseInt(selectedCategory.max_person || 1);
        basePrice = isHalfDay ? parseFloat(selectedCategory.half_day_price || 0) : parseFloat(selectedCategory.price || 0);
        extraPersonPrice = isHalfDay ? parseFloat(selectedCategory.person_half_price || 0) : parseFloat(selectedCategory.person_full_price || 0);
    }
    
    const extraCount = Math.max(0, personCount - maxPerson);
    return basePrice + (extraCount * extraPersonPrice);
}

/**
 * Toplam fiyatı hesapla
 */
function calculateTotalPrice() {
    if (selectedPackage) {
        return parseFloat(selectedPackage.discounted_price);
    }
    return calculateFlatPrice();
}

/**
 * Canlı fiyat göstergesini güncelle
 */
function updateLivePrice() {
    const livePrice = document.getElementById("livePriceDisplay");
    const subtext = document.getElementById("priceDetailSubtext");
    if (!livePrice) return;
    
    if (!selectedCategory) {
        livePrice.innerText = "0 ₺";
        subtext.innerText = "Lütfen önce hizmet seçin.";
        return;
    }
    
    if (selectedCategory.pricing_type === 'discovery') {
        livePrice.innerHTML = '<span style="font-size: 1.4rem;">Keşif Sonrası</span>';
        subtext.innerText = "Yerinde inceleme sonucu fiyatlandırılır.";
        return;
    }
    
    const priceVal = calculateTotalPrice();
    livePrice.innerText = priceVal.toLocaleString('tr-TR') + ' ₺';
    
    if (selectedPackage) {
        subtext.innerText = `Aboneliğe Özel İndirimli Fiyat (${selectedPackage.name})`;
    } else {
        subtext.innerText = "Tek Seferlik Standart Temizlik Ücreti";
    }
}

// ==========================================
// ADIM GEÇİŞ YÖNETİMİ
// ==========================================

function updateProgress() {
    const steps = document.querySelectorAll(".progress-step");
    steps.forEach(step => {
        const stepNum = parseInt(step.dataset.step);
        if (stepNum === currentStep) {
            step.className = "progress-step active";
        } else if (stepNum < currentStep) {
            step.className = "progress-step completed";
        } else {
            step.className = "progress-step";
        }
    });
    
    // Progress bar genişliği
    const pct = ((currentStep - 1) / (steps.length - 1)) * 100;
    document.getElementById("progressBar").style.width = pct + "%";
    
    // Geri butonu görünürlüğü
    document.getElementById("prevBtn").style.visibility = currentStep === 1 ? "hidden" : "visible";
    
    // Devam et / Gönder butonu yönetimi
    const nextBtn = document.getElementById("nextBtn");
    if (currentStep === 5) {
        nextBtn.style.display = "none"; // Son adımda form submit butonu sağda göründüğü için bunu gizle
    } else {
        nextBtn.style.display = "inline-block";
        nextBtn.innerHTML = "Devam Et <i class='fa-solid fa-arrow-right'></i>";
    }
}

function nextStep() {
    if (currentStep === 1) {
        const grid = document.getElementById("categoryGrid");
        clearFieldError(grid);
        if (!selectedCategory) {
            showFieldError(grid, "Lütfen devam etmek için bir hizmet kategorisi seçin.");
            return;
        }
    }
    
    if (currentStep === 2) {
        const cal = document.querySelector(".custom-calendar");
        const slots = document.getElementById("timeSlotsList");
        clearFieldError(cal);
        clearFieldError(slots);
        
        let hasErr = false;
        if (!selectedDate) {
            showFieldError(cal, "Lütfen takvimden bir temizlik tarihi seçin.");
            hasErr = true;
        }
        if (!selectedTimeSlot) {
            showFieldError(slots, "Lütfen bir saat dilimi seçin.");
            hasErr = true;
        }
        if (hasErr) return;
    }
    
    if (currentStep === 3) {
        const nameInput = document.getElementById("c_name");
        const phoneInput = document.getElementById("c_phone");
        clearFieldError(nameInput);
        clearFieldError(phoneInput);
        
        const name = nameInput.value.trim();
        const phone = phoneInput.value.replace(/\D/g, "");
        
        let hasErr = false;
        if (!name) {
            showFieldError(nameInput, "Lütfen adınızı soyadınızı girin.");
            hasErr = true;
        }
        if (phone.length !== 10) {
            showFieldError(phoneInput, "Lütfen telefon numaranızı eksiksiz girin (10 hane olmalıdır).");
            hasErr = true;
        }
        if (hasErr) return;
    }
    
    if (currentStep === 4) {
        const addrInput = document.getElementById("c_address");
        clearFieldError(addrInput);
        
        const addr = addrInput.value.trim();
        if (!addr) {
            showFieldError(addrInput, "Lütfen temizlik adresini girin.");
            return;
        }
    }
    
    goToStep(currentStep + 1);
}

function prevStep() {
    goToStep(currentStep - 1);
}

function goToStep(step) {
    document.querySelectorAll(".wizard-step").forEach(s => {
        s.classList.remove("active");
    });
    
    currentStep = step;
    document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add("active");
    
    if (currentStep === 2) {
        renderCalendar();
    }
    
    if (currentStep === 5) {
        updateSummary();
    }
    
    updateProgress();
    
    // Sayfanın en üstüne yumuşak geçiş yap
    document.querySelector(".wizard-card").scrollIntoView({ behavior: 'smooth' });
}

function updateSummary() {
    document.getElementById("summary_category").innerText = selectedCategory ? selectedCategory.name : '-';
    
    if (selectedDate) {
        const dObj = new Date(selectedDate);
        document.getElementById("summary_date").innerText = `${dObj.getDate()} ${monthsTr[dObj.getMonth()]} ${dObj.getFullYear()}`;
    } else {
        document.getElementById("summary_date").innerText = '-';
    }
    
    let slotText = '-';
    if (selectedTimeSlot === '08-17') slotText = 'Tam Gün (8-17)';
    else if (selectedTimeSlot === '08-12') slotText = 'Yarım Gün (8-12)';
    else if (selectedTimeSlot === '13-17') slotText = 'Yarım Gün (13-17)';
    document.getElementById("summary_time_slot").innerText = slotText;
    
    document.getElementById("summary_customer_name").innerText = document.getElementById("c_name").value || '-';
    document.getElementById("summary_customer_phone").innerText = "+90 " + document.getElementById("c_phone").value || '-';
}

// ==========================================
// TAKVİM İŞLEMLERİ
// ==========================================

function renderCalendar() {
    const grid = document.getElementById("calendarGrid");
    if (!grid) return;
    grid.innerHTML = "";
    
    document.getElementById("calendarMonthTitle").innerText = `${monthsTr[displayMonth]} ${displayYear}`;
    
    // Ayın ilk gününün haftanın hangi günü olduğunu bul
    const firstDay = new Date(displayYear, displayMonth, 1);
    let startDayIndex = firstDay.getDay(); // 0=Sunday, 1=Monday...
    startDayIndex = startDayIndex === 0 ? 6 : startDayIndex - 1;
    
    // Ayın kaç gün çektiğini bul
    const totalDays = new Date(displayYear, displayMonth + 1, 0).getDate();
    
    // Boş günleri koy (ayın ilk gününe kadar olan boşluklar)
    for (let i = 0; i < startDayIndex; i++) {
        const empty = document.createElement("div");
        grid.appendChild(empty);
    }
    
    // Günleri oluştur
    const today = new Date();
    today.setHours(0,0,0,0);
    
    for (let dayNum = 1; dayNum <= totalDays; dayNum++) {
        const dayCell = document.createElement("button");
        dayCell.type = "button";
        dayCell.className = "calendar-day-btn";
        dayCell.innerText = dayNum;
        
        const cellDate = new Date(displayYear, displayMonth, dayNum);
        cellDate.setHours(0,0,0,0);
        
        // Pazar günlerini veya geçmiş günleri devre dışı bırak
        const isSunday = cellDate.getDay() === 0;
        const isPast = cellDate < today;
        
        const yyyy = displayYear;
        const mm = String(displayMonth + 1).padStart(2, '0');
        const dd = String(dayNum).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;
        
        if (isSunday || isPast) {
            dayCell.classList.add("disabled");
            dayCell.disabled = true;
        } else {
            if (selectedDate === dateStr) {
                dayCell.classList.add("selected");
            }
            if (cellDate.getTime() === today.getTime()) {
                dayCell.classList.add("today");
            }
            dayCell.onclick = () => selectDate(dateStr, dayCell);
        }
        
        grid.appendChild(dayCell);
    }
}

function prevMonth() {
    const today = new Date();
    if (displayYear === today.getFullYear() && displayMonth === today.getMonth()) {
        return;
    }
    displayMonth--;
    if (displayMonth < 0) {
        displayMonth = 11;
        displayYear--;
    }
    renderCalendar();
}

function nextMonth() {
    displayMonth++;
    if (displayMonth > 11) {
        displayMonth = 0;
        displayYear++;
    }
    renderCalendar();
}

/**
 * Takvimden tarih seçildiğinde
 */
function selectDate(dateStr, cellElement) {
    selectedDate = dateStr;
    document.getElementById("input_booking_date").value = dateStr;
    
    const cal = document.querySelector(".custom-calendar");
    clearFieldError(cal);
    
    // Seçim stillerini güncelle
    document.querySelectorAll(".calendar-day-btn").forEach(c => c.classList.remove("selected"));
    cellElement.classList.add("selected");
    
    // Müsaitlik durumunu AJAX ile sorgula
    fetchAvailability(dateStr);
    
    // Tarih seçilince mobil ekranda saat dilimi bölümüne odaklan/kaydır (tam sığacak şekilde)
    if (window.innerWidth <= 768) {
        setTimeout(() => {
            const target = document.getElementById("timeSlotsTitle");
            if (target) {
                const yOffset = -90; // sticky header ve boşluk payı
                const y = target.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }
        }, 150);
    }
}

/**
 * AJAX ile doluluk kontrolü
 */
function fetchAvailability(dateStr) {
    const catId = selectedCategory ? selectedCategory.id : 0;
    
    document.querySelectorAll(".time-pill-btn").forEach(btn => {
        btn.classList.remove("disabled");
        btn.disabled = false;
        
        const badge = btn.querySelector(".slot-badge-info");
        if (badge) {
            badge.innerText = "Sorgulanıyor...";
            badge.style.backgroundColor = "rgba(0,0,0,0.05)";
            badge.style.color = "var(--text-muted)";
        }
    });
    
    fetch(`ajax/check_availability.php?date=${dateStr}&category_id=${catId}&person_count=${personCount}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Object.keys(data.availability).forEach(slotKey => {
                    const slotInfo = data.availability[slotKey];
                    const btn = document.querySelector(`.time-pill-btn[data-slot="${slotKey}"]`);
                    if (btn) {
                        const badge = btn.querySelector(".slot-badge-info");
                        if (slotInfo.available) {
                            btn.classList.remove("disabled");
                            btn.disabled = false;
                            if (badge) {
                                badge.innerText = "Müsait";
                                badge.style.backgroundColor = "#e6fdf5";
                                badge.style.color = "var(--success)";
                            }
                        } else {
                            btn.classList.add("disabled");
                            btn.disabled = true;
                            if (badge) {
                                badge.innerText = "Dolu";
                                badge.style.backgroundColor = "#fef2f2";
                                badge.style.color = "var(--danger)";
                            }
                            if (selectedTimeSlot === slotKey) {
                                selectedTimeSlot = null;
                                document.getElementById("input_booking_time_slot").value = "";
                                btn.classList.remove("selected");
                            }
                        }
                    }
                });
            }
        });
}

// ==========================================
// GEOLOCATION (KONUM PAYLAŞIMI)
// ==========================================

function getGeolocation() {
    const locInput = document.getElementById("c_location");
    const locBtn = document.getElementById("getLocationBtn");
    
    if (!navigator.geolocation) {
        alert("Tarayıcınız konum servislerini desteklemiyor.");
        return;
    }
    
    locBtn.disabled = true;
    locBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Alınıyor...';
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
            
            locInput.value = mapsUrl;
            locBtn.disabled = false;
            locBtn.innerHTML = '<i class="fa-solid fa-circle-check" style="color: var(--success);"></i> Konum Alındı';
        },
        (error) => {
            locBtn.disabled = false;
            locBtn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Konumumu Bul';
            
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    alert("Konum izni verilmedi. Lütfen tarayıcı izinlerinden konuma izin verin.");
                    break;
                case error.POSITION_UNAVAILABLE:
                    alert("Konum bilgisi şu an alınamıyor.");
                    break;
                case error.TIMEOUT:
                    alert("Konum isteği zaman aşımına uğradı.");
                    break;
                default:
                    alert("Bilinmeyen bir konum hatası oluştu.");
            }
        },
        { enableHighAccuracy: true, timeout: 8000 }
    );
}

// ==========================================
// GÖNDERİM İŞLEMLERİ
// ==========================================

function submitWizardForm() {
    const submitBtn = document.getElementById("submitOfferBtn");
    const errorAlert = document.getElementById("bookingErrorAlert");
    
    // Tüm hataları temizle
    document.querySelectorAll(".field-error-text").forEach(el => el.remove());
    
    // Validasyonlar
    let hasErr = false;
    
    if (!selectedCategory) {
        const grid = document.getElementById("categoryGrid");
        showFieldError(grid, "Lütfen devam etmek için bir hizmet kategorisi seçin.");
        hasErr = true;
        goToStep(1);
    }
    if (!selectedDate) {
        const cal = document.querySelector(".custom-calendar");
        showFieldError(cal, "Lütfen takvimden bir temizlik tarihi seçin.");
        hasErr = true;
        if (!hasErr) goToStep(2);
    }
    if (!selectedTimeSlot) {
        const slots = document.getElementById("timeSlotsList");
        showFieldError(slots, "Lütfen bir saat dilimi seçin.");
        hasErr = true;
        if (!hasErr) goToStep(2);
    }
    
    const nameInput = document.getElementById("c_name");
    const phoneInput = document.getElementById("c_phone");
    const name = nameInput.value.trim();
    const phone = phoneInput.value.replace(/\D/g, "");
    
    if (!name) {
        showFieldError(nameInput, "Lütfen adınızı soyadınızı girin.");
        hasErr = true;
        if (!hasErr) goToStep(3);
    }
    if (phone.length !== 10) {
        showFieldError(phoneInput, "Lütfen telefon numaranızı eksiksiz girin (10 hane olmalıdır).");
        hasErr = true;
        if (!hasErr) goToStep(3);
    }
    
    const addrInput = document.getElementById("c_address");
    const address = addrInput.value.trim();
    if (!address) {
        showFieldError(addrInput, "Lütfen temizlik adresini girin.");
        hasErr = true;
        if (!hasErr) goToStep(4);
    }
    
    if (hasErr) return;
    
    submitBtn.disabled = true;
    submitBtn.innerText = "Gönderiliyor...";
    errorAlert.style.display = "none";
    
    const formData = new FormData(document.getElementById("wizardForm"));
    
    fetch('ajax/create_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerText = "Talep Gönder";
        
        if (data.success) {
            // Başarı modalını göster
            const modal = document.getElementById("successModal");
            modal.classList.add("active");
            
            // 4 Saniye sonra ana sayfaya yönlendir
            let redirectTimer = setTimeout(() => {
                window.location.href = "index.php";
            }, 4000);
            
            // Tamam butonuna tıklandığında anında yönlendir
            document.getElementById("btnSuccessOk").onclick = () => {
                clearTimeout(redirectTimer);
                window.location.href = "index.php";
            };
        } else {
            errorAlert.style.display = "flex";
            document.getElementById("bookingErrorMessage").innerText = data.message;
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerText = "Talep Gönder";
        console.error("Submission Error: ", err);
        errorAlert.style.display = "flex";
        document.getElementById("bookingErrorMessage").innerText = "Sistemsel bir hata oluştu. Lütfen tekrar deneyin.";
    });
}

// ==========================================
// HATA GÖSTERİM METODLARI
// ==========================================

function showFieldError(inputElement, message) {
    if (!inputElement) return;
    clearFieldError(inputElement);
    
    const err = document.createElement("div");
    err.className = "field-error-text";
    err.style.color = "var(--danger)";
    err.style.fontSize = "0.75rem";
    err.style.fontWeight = "700";
    err.style.marginTop = "6px";
    err.style.display = "flex";
    err.style.alignItems = "center";
    err.style.gap = "4px";
    err.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> ${message}`;
    
    if (inputElement.id === "c_phone" && inputElement.closest(".phone-input-group")) {
        inputElement.closest(".phone-input-group").parentNode.appendChild(err);
    } else {
        inputElement.parentNode.appendChild(err);
    }
}

function clearFieldError(inputElement) {
    if (!inputElement) return;
    
    let errNode = null;
    if (inputElement.id === "c_phone" && inputElement.closest(".phone-input-group")) {
        errNode = inputElement.closest(".phone-input-group").parentNode.querySelector(".field-error-text");
    } else {
        errNode = inputElement.parentNode.querySelector(".field-error-text");
    }
    
    if (errNode) {
        errNode.remove();
    }
}

function setupFieldClearListeners() {
    const name = document.getElementById("c_name");
    if (name) {
        name.addEventListener("input", () => clearFieldError(name));
    }
    const phone = document.getElementById("c_phone");
    if (phone) {
        phone.addEventListener("input", () => clearFieldError(phone));
    }
    const address = document.getElementById("c_address");
    if (address) {
        address.addEventListener("input", () => clearFieldError(address));
    }
}
