<?php
/**
 * نمایشگر زنده‌ی آشپزخانه (KDS) — صفحه‌ای کاملاً مستقل، بدون چارچوب وردپرس،
 * برای نصب روی تبلت/مانیتور آشپزخانه. دسترسی با پین‌کد محافظت می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>🍳 نمایشگر آشپزخانه</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: #0b090a;
            color: #f5f3f4;
            direction: rtl;
        }

        /* ===== صفحه‌ی ورود پین ===== */
        #lcmKdsPinScreen {
            height: 100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap: 20px;
        }
        #lcmKdsPinScreen h1 { font-size: 1.6rem; }
        #lcmKdsPinDots { display:flex; gap: 14px; margin: 10px 0; }
        .lcm-pin-dot { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #2ec4b6; background: transparent; }
        .lcm-pin-dot.filled { background: #2ec4b6; }
        #lcmKdsPinPad { display:grid; grid-template-columns: repeat(3, 90px); gap: 14px; }
        .lcm-pin-btn { height: 90px; border-radius: 20px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #fff; font-size: 1.8rem; font-weight:900; cursor:pointer; }
        .lcm-pin-btn:active { background: rgba(46,196,182,0.3); }
        #lcmKdsPinError { color: #e5383b; font-weight:700; min-height: 24px; }

        /* ===== صفحه‌ی شروع نوبت کاری (فعال‌سازی صدا) ===== */
        #lcmKdsStartScreen { height:100vh; display:none; flex-direction:column; align-items:center; justify-content:center; gap:20px; }
        #lcmKdsStartBtn { padding: 30px 60px; font-size: 1.8rem; font-weight:900; border-radius: 24px; border:none; background: linear-gradient(135deg, #2ec4b6, #139a8c); color:#fff; cursor:pointer; box-shadow: 0 10px 40px rgba(46,196,182,0.4); }

        /* ===== خود نمایشگر ===== */
        #lcmKdsBoard { display:none; height:100vh; flex-direction:column; }
        #lcmKdsHeader { display:flex; justify-content:space-between; align-items:center; padding: 14px 24px; background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.08); }
        #lcmKdsHeader h1 { font-size: 1.2rem; color: #2ec4b6; }
        #lcmKdsClock { font-size: 1.1rem; color: #888; font-weight:700; direction:ltr; }
        #lcmKdsGrid { flex:1; overflow-y:auto; padding: 20px; display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; align-content:start; }
        #lcmWaiterCallsBar { display:flex; flex-direction:column; gap:8px; padding: 0 20px; }
        .lcm-waiter-call-alert { background: linear-gradient(135deg, #ffb703, #fb8500); color:#000; border-radius:14px; padding:12px 18px; margin-top:14px; display:flex; justify-content:space-between; align-items:center; font-weight:900; animation: lcmWaiterPulse 1s infinite; }
        @keyframes lcmWaiterPulse { 0%,100% { box-shadow: 0 0 0 rgba(255,183,3,0); } 50% { box-shadow: 0 0 25px rgba(255,183,3,0.7); } }
        .lcm-waiter-ack-btn { background:#000; color:#fff; border:none; padding:8px 16px; border-radius:10px; font-weight:800; cursor:pointer; font-size:0.8rem; }

        .lcm-kds-card { background: rgba(255,255,255,0.05); border: 3px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 18px; transition: border-color 0.4s, background 0.4s; }
        .lcm-kds-card.preparing { border-color: #ffb703; }
        .lcm-kds-card.ready-fade { border-color: #43e97b; background: rgba(67,233,123,0.15); opacity: 0.6; }
        .lcm-kds-card.late { border-color: #e5383b; background: rgba(229,56,59,0.12); animation: lcmPulseLate 1.5s infinite; }
        .lcm-kds-card.preorder { border-color: #a78bfa; }
        .lcm-kds-preorder-badge { background: rgba(167,139,250,0.15); border: 2px dashed #a78bfa; color: #a78bfa; border-radius: 12px; padding: 8px 12px; font-size: 0.9rem; font-weight: 800; margin-bottom: 12px; text-align: center; }
        @keyframes lcmPulseLate { 0%,100% { box-shadow: 0 0 0 rgba(229,56,59,0); } 50% { box-shadow: 0 0 30px rgba(229,56,59,0.5); } }

        .lcm-kds-location { font-size: 1.8rem; font-weight: 900; color: #2ec4b6; }
        .lcm-kds-card.late .lcm-kds-location { color: #ff8080; }
        .lcm-kds-customer { font-size: 0.9rem; color: #aaa; margin-top: 2px; }
        .lcm-kds-tier-badge { display:inline-block; font-size:0.68rem; font-weight:800; background: rgba(46,196,182,0.2); color:#2ec4b6; padding:2px 8px; border-radius:8px; margin-right:6px; }
        .lcm-kds-timer { font-size: 1rem; font-weight:900; color: #888; direction: ltr; }
        .lcm-kds-card.late .lcm-kds-timer { color: #ff8080; }
        .lcm-kds-items { margin: 14px 0; font-size: 1.05rem; line-height: 1.9; font-weight: 700; }
        .lcm-kds-note { background: rgba(255,183,3,0.15); border: 2px dashed #ffb703; border-radius: 12px; padding: 10px 12px; font-size: 0.95rem; font-weight: 800; color: #ffb703; margin-bottom: 12px; }
        .lcm-kds-actions { display:flex; gap: 10px; margin-top: 10px; }
        .lcm-kds-btn { flex:1; padding: 14px; border-radius: 14px; border: none; font-weight: 900; font-size: 0.95rem; cursor: pointer; }
        .lcm-kds-btn-preparing { background: rgba(255,183,3,0.15); border: 2px solid #ffb703; color: #ffb703; }
        .lcm-kds-btn-preparing.active { background: #ffb703; color: #000; }
        .lcm-kds-btn-ready { background: rgba(67,233,123,0.15); border: 2px solid #43e97b; color: #43e97b; }
        .lcm-kds-btn-ready:active, .lcm-kds-btn-preparing:active { transform: scale(0.96); }

        #lcmKdsEmpty { grid-column: 1/-1; text-align:center; padding: 80px 20px; color: #555; font-size: 1.3rem; }
    </style>
</head>
<body>

    <!-- مرحله‌ی ۱: ورود پین -->
    <div id="lcmKdsPinScreen">
        <h1 style="display:flex; align-items:center; gap:10px; justify-content:center;"><i data-lucide="chef-hat" style="width:28px; height:28px;"></i> ورود به نمایشگر آشپزخانه</h1>
        <div id="lcmKdsPinDots"></div>
        <div id="lcmKdsPinError"></div>
        <div id="lcmKdsPinPad">
            <button class="lcm-pin-btn" data-d="1">۱</button>
            <button class="lcm-pin-btn" data-d="2">۲</button>
            <button class="lcm-pin-btn" data-d="3">۳</button>
            <button class="lcm-pin-btn" data-d="4">۴</button>
            <button class="lcm-pin-btn" data-d="5">۵</button>
            <button class="lcm-pin-btn" data-d="6">۶</button>
            <button class="lcm-pin-btn" data-d="7">۷</button>
            <button class="lcm-pin-btn" data-d="8">۸</button>
            <button class="lcm-pin-btn" data-d="9">۹</button>
            <button class="lcm-pin-btn" id="lcmKdsPinClear">پاک</button>
            <button class="lcm-pin-btn" data-d="0">۰</button>
            <button class="lcm-pin-btn" id="lcmKdsPinSubmit">ورود</button>
        </div>
    </div>

    <!-- مرحله‌ی ۲: فعال‌سازی صدا (مرورگرها بدون این کلیک، صدای خودکار را مسدود می‌کنند) -->
    <div id="lcmKdsStartScreen">
        <h1>👋 صبح بخیر!</h1>
        <button id="lcmKdsStartBtn">▶️ شروع نوبت کاری</button>
        <p style="color:#888; font-size:0.85rem;">با این دکمه، صدای اعلام سفارش جدید فعال می‌شود</p>
    </div>

    <!-- مرحله‌ی ۳: خود نمایشگر -->
    <div id="lcmKdsBoard">
        <div id="lcmKdsHeader">
            <h1 style="display:flex; align-items:center; gap:10px;"><i data-lucide="inbox" style="width:22px; height:22px;"></i> صف پخت و پز زنده آشپزخانه</h1>
            <div id="lcmKdsClock">--:--:--</div>
        </div>
        <div id="lcmWaiterCallsBar"></div>
        <div id="lcmKdsGrid">
            <div id="lcmKdsEmpty">در حال دریافت سفارش‌ها...</div>
        </div>
    </div>

<script>
(function() {
    const ajaxUrl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";
    try { if (typeof lucide !== 'undefined') { lucide.createIcons(); } } catch(e) { console.error('lucide init error:', e); }
    let enteredPin = '';
    let timerMinutes = 7;
    let recentlyReady = {}; // آیدی سفارش‌هایی که تازه «آماده» شده‌اند، برای نمایش چند ثانیه‌ای محو شدن
    let knownOrderIds = new Set();
    let firstLoad = true;

    // ===================== پین‌کد =====================
    function renderPinDots() {
        const dotsEl = document.getElementById('lcmKdsPinDots');
        dotsEl.innerHTML = '';
        const dotCount = Math.max(4, enteredPin.length);
        for (let i = 0; i < dotCount; i++) {
            const dot = document.createElement('div');
            dot.className = 'lcm-pin-dot' + (i < enteredPin.length ? ' filled' : '');
            dotsEl.appendChild(dot);
        }
    }
    renderPinDots();

    document.querySelectorAll('.lcm-pin-btn[data-d]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (enteredPin.length < 8) { enteredPin += btn.getAttribute('data-d'); renderPinDots(); }
        });
    });
    document.getElementById('lcmKdsPinClear').addEventListener('click', () => {
        enteredPin = ''; renderPinDots();
        document.getElementById('lcmKdsPinError').innerText = '';
    });
    document.getElementById('lcmKdsPinSubmit').addEventListener('click', () => {
        const errEl = document.getElementById('lcmKdsPinError');
        errEl.innerText = 'در حال بررسی...';

        let formData = new FormData();
        formData.append('action', 'lcm_kds_check_pin');
        formData.append('pin', enteredPin);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('lcmKdsPinScreen').style.display = 'none';
                    document.getElementById('lcmKdsStartScreen').style.display = 'flex';
                } else {
                    errEl.innerText = '❌ پین اشتباه است.';
                    enteredPin = ''; renderPinDots();
                }
            })
            .catch(() => { errEl.innerText = 'خطا در ارتباط با سرور.'; });
    });

    // ===================== فعال‌سازی صدا و شروع =====================
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str;
        return div.innerHTML;
    }

    function playDing() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
            gainNode.gain.setValueAtTime(0.25, audioCtx.currentTime);
            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.6);
            oscillator.stop(audioCtx.currentTime + 0.6);
        } catch (e) { /* مرورگر از صدا پشتیبانی نمی‌کند */ }
    }

    function playWaiterAlert() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            [660, 880, 660].forEach((freq, i) => {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.type = 'square';
                oscillator.frequency.setValueAtTime(freq, audioCtx.currentTime + i * 0.25);
                gainNode.gain.setValueAtTime(0.15, audioCtx.currentTime + i * 0.25);
                oscillator.start(audioCtx.currentTime + i * 0.25);
                gainNode.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + i * 0.25 + 0.2);
                oscillator.stop(audioCtx.currentTime + i * 0.25 + 0.2);
            });
        } catch (e) { /* مرورگر از صدا پشتیبانی نمی‌کند */ }
    }

    let knownWaiterCallTables = new Set();
    function fetchWaiterCalls() {
        let formData = new FormData();
        formData.append('action', 'lcm_get_waiter_calls');

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                const calls = res.data || [];
                const bar = document.getElementById('lcmWaiterCallsBar');
                if (!bar) return;

                calls.forEach(call => {
                    if (!knownWaiterCallTables.has(call.table_id)) {
                        knownWaiterCallTables.add(call.table_id);
                        playWaiterAlert();
                    }
                });
                knownWaiterCallTables = new Set(calls.map(c => c.table_id));

                bar.innerHTML = calls.map(call => `
                    <div class="lcm-waiter-call-alert">
                        <span>🔔 میز ${call.table_id} کمک می‌خواهد</span>
                        <button class="lcm-waiter-ack-btn" data-table-id="${call.table_id}">✅ تایید شد</button>
                    </div>
                `).join('');
            })
            .catch(() => {});
    }

    function lcmAckWaiterCall(tableId, btnEl) {
        btnEl.disabled = true;
        let formData = new FormData();
        formData.append('action', 'lcm_ack_waiter_call');
        formData.append('table_id', tableId);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(() => fetchWaiterCalls())
            .catch(() => { btnEl.disabled = false; });
    }

    // 🔒 رفع باگ واقعی: قبلاً دکمه‌ی «تایید شد» با onclick درون‌خطی به تابعی اشاره می‌کرد که
    // داخل یک IIFE (محدوده‌ی خصوصی اسکریپت) تعریف شده بود. onclick درون‌خطی همیشه در
    // محدوده‌ی سراسری (global) اجرا می‌شود و به توابع داخلِ این closure دسترسی ندارد — یعنی
    // کلیک روی دکمه بی‌صدا با خطای «تابع تعریف‌نشده» در کنسول مرورگر شکست می‌خورد.
    // با delegation روی همین container (که همیشه در صفحه ثابت است) این مشکل کامل حل می‌شود.
    document.getElementById('lcmWaiterCallsBar').addEventListener('click', function(evt) {
        const btn = evt.target.closest('.lcm-waiter-ack-btn');
        if (btn) { lcmAckWaiterCall(parseInt(btn.getAttribute('data-table-id')), btn); }
    });

    document.getElementById('lcmKdsStartBtn').addEventListener('click', () => {
        playDing();
        document.getElementById('lcmKdsStartScreen').style.display = 'none';
        document.getElementById('lcmKdsBoard').style.display = 'flex';
        fetchOrders();
        fetchWaiterCalls();
        setInterval(fetchOrders, 4000);
        setInterval(fetchWaiterCalls, 5000);
        setInterval(updateClockAndTimers, 1000);
    });

    // ===================== ساعت زنده =====================
    function updateClockAndTimers() {
        const now = new Date();
        document.getElementById('lcmKdsClock').innerText = now.toLocaleTimeString('fa-IR');

        document.querySelectorAll('.lcm-kds-card[data-created-ts]').forEach(card => {
            const isPreOrder = card.classList.contains('preorder');
            const createdTs = parseInt(card.getAttribute('data-created-ts'));
            const elapsedSec = Math.max(0, Math.floor((Date.now() - createdTs) / 1000));
            const timerEl = card.querySelector('.lcm-kds-timer');

            if (isPreOrder) {
                // برای پیش‌سفارش، زمان‌شمار معمولی معنی ندارد (ساعت درخواستی از قبل نشان داده شده)
                if (timerEl) { timerEl.innerText = ''; }
                return;
            }

            const mm = String(Math.floor(elapsedSec / 60)).padStart(2, '0');
            const ss = String(elapsedSec % 60).padStart(2, '0');
            if (timerEl) { timerEl.innerText = mm + ':' + ss; }

            const isLate = elapsedSec > (timerMinutes * 60);
            if (isLate && !card.classList.contains('ready-fade')) {
                card.classList.add('late');
            }
        });
    }

    // ===================== واکشی زنده‌ی سفارش‌ها =====================
    function fetchOrders() {
        let formData = new FormData();
        formData.append('action', 'lcm_kds_get_orders');

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                timerMinutes = res.data.timer_minutes || 7;
                renderOrders(res.data.orders || []);
            })
            .catch(() => { /* شبکه موقتاً قطع بود؛ در تلاش بعدی دوباره امتحان می‌شود */ });
    }

    function renderOrders(orders) {
        const grid = document.getElementById('lcmKdsGrid');

        // صدا برای سفارش‌های واقعاً جدید (که در چرخه‌ی قبلی نبودند)
        orders.forEach(o => {
            if (!knownOrderIds.has(o.id)) {
                knownOrderIds.add(o.id);
                if (!firstLoad) { playDing(); }
            }
        });
        firstLoad = false;

        // سفارش‌هایی که تازه «آماده» اعلام شده‌اند را چند ثانیه نگه دار تا محو شوند
        const visibleOrders = [...orders];
        Object.keys(recentlyReady).forEach(id => {
            if (!orders.find(o => String(o.id) === String(id))) {
                visibleOrders.push(recentlyReady[id]);
            }
        });

        if (visibleOrders.length === 0) {
            grid.innerHTML = '<div id="lcmKdsEmpty">✅ صف خالی است — همه‌چیز آماده و تحویل داده شده!</div>';
            return;
        }

        grid.innerHTML = '';
        visibleOrders.sort((a, b) => a.created_ts - b.created_ts);

        visibleOrders.forEach(order => {
            const isFadingOut = !!recentlyReady[order.id];
            const isPreOrder = !!order.requested_time;
            const card = document.createElement('div');
            card.className = 'lcm-kds-card' + (order.kds_status === 'preparing' ? ' preparing' : '') + (isFadingOut ? ' ready-fade' : '') + (isPreOrder ? ' preorder' : '');
            card.setAttribute('data-created-ts', order.created_ts);

            const itemsHtml = order.items.map(i => `<div>• ${i}</div>`).join('');
            const noteHtml = order.note ? `<div class="lcm-kds-note">📝 ${escapeHtml(order.note)}</div>` : '';
            const preOrderHtml = isPreOrder ? `<div class="lcm-kds-preorder-badge"><i data-lucide="clock" style="width:16px; height:16px; vertical-align:-3px;"></i> پیش‌سفارش — تحویل ساعت ${escapeHtml(order.requested_time)}</div>` : '';

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="lcm-kds-location">${order.location_label}</div>
                        ${order.customer_name ? `<div class="lcm-kds-customer">${order.customer_tier ? `<span class="lcm-kds-tier-badge">${escapeHtml(order.customer_tier)}</span>` : ''}👤 ${escapeHtml(order.customer_name)}</div>` : ''}
                    </div>
                    <div class="lcm-kds-timer">00:00</div>
                </div>
                ${preOrderHtml}
                <div class="lcm-kds-items">${itemsHtml}</div>
                ${noteHtml}
                ${isFadingOut ? '<div style="text-align:center; color:#43e97b; font-weight:900;">✅ آماده شد!</div>' : `
                <div class="lcm-kds-actions">
                    <button class="lcm-kds-btn lcm-kds-btn-preparing ${order.kds_status === 'preparing' ? 'active' : ''}" data-order-id="${order.id}" data-action="preparing">🔥 در حال تهیه</button>
                    <button class="lcm-kds-btn lcm-kds-btn-ready" data-order-id="${order.id}" data-action="ready">✅ آماده شد</button>
                </div>`}
            `;
            grid.appendChild(card);
        });

        grid.querySelectorAll('.lcm-kds-btn').forEach(btn => {
            btn.addEventListener('click', () => handleStatusClick(btn));
        });

        try { if (typeof lucide !== 'undefined') { lucide.createIcons(); } } catch(e) {}
        updateClockAndTimers();
    }

    function handleStatusClick(btn) {
        const orderId = btn.getAttribute('data-order-id');
        const action = btn.getAttribute('data-action');
        const card = btn.closest('.lcm-kds-card');

        btn.disabled = true;

        let formData = new FormData();
        formData.append('action', 'lcm_kds_update_status');
        formData.append('order_id', orderId);
        formData.append('kds_status', action);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    if (action === 'ready') {
                        // چند ثانیه با رنگ سبز نگه دار، بعد کامل حذفش کن
                        recentlyReady[orderId] = {
                            id: orderId,
                            items: Array.from(card.querySelectorAll('.lcm-kds-items div')).map(d => d.innerText.replace('• ', '')),
                            location_label: card.querySelector('.lcm-kds-location').innerText,
                            customer_name: card.querySelector('.lcm-kds-customer') ? card.querySelector('.lcm-kds-customer').innerText.replace('👤 ', '') : '',
                            created_ts: parseInt(card.getAttribute('data-created-ts')),
                            kds_status: 'ready',
                        };
                        setTimeout(() => { delete recentlyReady[orderId]; fetchOrders(); }, 5000);
                        fetchOrders();
                    } else {
                        fetchOrders();
                    }
                } else {
                    btn.disabled = false;
                    alert('خطا: ' + (res.data && res.data.message ? res.data.message : 'ثبت نشد'));
                }
            })
            .catch(() => {
                btn.disabled = false;
                alert('خطا در ارتباط با سرور.');
            });
    }
})();
</script>
</body>
</html>
<?php exit; // این صفحه کاملاً مستقل است؛ هدر/فوتر قالب سایت نباید بارگذاری شود
