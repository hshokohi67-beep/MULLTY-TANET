<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$cafe_name = get_option( 'lcm_cafe_name', 'کافه' );
$cafe_logo = get_option( 'lcm_cafe_logo', '' );
$table_count = intval( get_option('lcm_table_count', 5) );
// حداقل تاریخ: امروز
$today = current_time('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>رزرو میز — <?php echo esc_html($cafe_name); ?></title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #0b090a; color: #f5f3f4; min-height: 100vh; }

        .res-container { max-width: 520px; margin: 0 auto; padding: 24px 16px 60px; }

        .res-header { text-align: center; padding: 32px 0 24px; }
        .res-header img { width: 72px; height: 72px; border-radius: 20px; object-fit: cover; margin-bottom: 14px; }
        .res-header .cafe-name { font-size: 1.3rem; font-weight: 900; color: #2ec4b6; }
        .res-header p { font-size: 0.8rem; color: #888; margin-top: 4px; }

        .res-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 24px; }

        .res-field { margin-bottom: 18px; }
        .res-field label { display: block; font-size: 0.78rem; color: #aaa; margin-bottom: 7px; }
        .res-field input, .res-field select, .res-field textarea {
            width: 100%; padding: 13px 14px;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            background: rgba(255,255,255,0.04);
            color: #f5f3f4;
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .res-field input:focus, .res-field select:focus, .res-field textarea:focus {
            border-color: #2ec4b6;
        }
        .res-field input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
        .res-field textarea { resize: vertical; min-height: 80px; }
        .res-field select option { background: #1a1a1a; }

        .res-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .res-guests { display: flex; align-items: center; gap: 12px; }
        .res-guests button {
            width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06); color: #fff; font-size: 1.2rem; cursor: pointer;
            transition: background 0.15s;
        }
        .res-guests button:hover { background: rgba(255,255,255,0.12); }
        .res-guests .count { font-size: 1.3rem; font-weight: 900; min-width: 30px; text-align: center; }

        .res-btn {
            width: 100%; padding: 15px; border: none; border-radius: 14px;
            background: linear-gradient(135deg, #2ec4b6, #139a8c);
            color: #fff; font-weight: 900; font-size: 1rem;
            cursor: pointer; margin-top: 8px;
            box-shadow: 0 8px 24px rgba(46,196,182,0.3);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .res-btn:active { transform: scale(0.97); box-shadow: none; }
        .res-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .res-success {
            display: none; text-align: center; padding: 40px 20px;
            background: rgba(46,196,182,0.08); border: 1px solid rgba(46,196,182,0.3);
            border-radius: 20px; margin-top: 20px;
        }
        .res-success .icon { font-size: 3rem; margin-bottom: 16px; }
        .res-success h3 { font-size: 1.2rem; color: #2ec4b6; margin-bottom: 8px; }
        .res-success p { font-size: 0.85rem; color: #aaa; }

        .res-error { color: #e5383b; font-size: 0.8rem; margin-top: 12px; text-align: center; display: none; }

        .back-to-menu { display: block; text-align: center; margin-top: 20px; color: #888; font-size: 0.8rem; text-decoration: none; }
        .back-to-menu:hover { color: #2ec4b6; }
    </style>
</head>
<body>

<div class="res-container">
    <div class="res-header">
        <?php if ($cafe_logo): ?>
            <img src="<?php echo esc_url($cafe_logo); ?>" alt="<?php echo esc_attr($cafe_name); ?>">
        <?php endif; ?>
        <div class="cafe-name"><?php echo esc_html($cafe_name); ?></div>
        <p>🗓️ رزرو آنلاین میز</p>
    </div>

    <div class="res-card" id="resForm">
        <div class="res-field">
            <label>👤 نام و نام‌خانوادگی *</label>
            <input type="text" id="resName" placeholder="نام شما" required>
        </div>

        <div class="res-field">
            <label>📞 شماره موبایل *</label>
            <input type="tel" id="resPhone" placeholder="09xxxxxxxxx" required maxlength="11">
        </div>

        <div class="res-grid-2">
            <div class="res-field">
                <label>📅 تاریخ *</label>
                <input type="date" id="resDate" min="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($today); ?>" required>
            </div>
            <div class="res-field">
                <label>🕐 ساعت *</label>
                <input type="time" id="resTime" min="08:00" max="22:00" value="19:00" required>
            </div>
        </div>

        <div class="res-field">
            <label>👥 تعداد نفرات</label>
            <div class="res-guests">
                <button type="button" onclick="changeGuests(-1)">−</button>
                <span class="count" id="guestsCount">2</span>
                <span style="font-size:0.85rem; color:#888;">نفر</span>
                <button type="button" onclick="changeGuests(+1)">+</button>
            </div>
            <input type="hidden" id="resGuests" value="2">
        </div>

        <div class="res-field">
            <label>📝 یادداشت (اختیاری)</label>
            <textarea id="resNote" placeholder="مثلاً: ترجیح میز کنار پنجره، مناسبت خاص، حساسیت غذایی..."></textarea>
        </div>

        <div class="res-error" id="resError"></div>

        <button class="res-btn" id="resSubmitBtn" onclick="submitReservation()">
            🗓️ ثبت رزرو
        </button>
    </div>

    <div class="res-success" id="resSuccess">
        <div class="icon">✅</div>
        <h3>رزرو شما ثبت شد!</h3>
        <p id="resSuccessMsg">به زودی با شما تماس می‌گیریم.</p>
    </div>

    <a href="<?php echo esc_url( home_url('/live-menu/') ); ?>" class="back-to-menu">← بازگشت به منوی آنلاین</a>
</div>

<script>
var guestsVal = 2;

function changeGuests(delta) {
    guestsVal = Math.max(1, Math.min(20, guestsVal + delta));
    document.getElementById('guestsCount').innerText = guestsVal;
    document.getElementById('resGuests').value = guestsVal;
}

function submitReservation() {
    var name  = document.getElementById('resName').value.trim();
    var phone = document.getElementById('resPhone').value.trim();
    var date  = document.getElementById('resDate').value;
    var time  = document.getElementById('resTime').value;
    var note  = document.getElementById('resNote').value.trim();
    var errEl = document.getElementById('resError');
    var btn   = document.getElementById('resSubmitBtn');

    errEl.style.display = 'none';

    if (!name || !phone || !date || !time) {
        errEl.innerText = '❌ لطفاً همه‌ی فیلدهای الزامی را پر کنید.';
        errEl.style.display = 'block';
        return;
    }
    if (!/^09\d{9}$/.test(phone)) {
        errEl.innerText = '❌ شماره موبایل را به‌صورت 09xxxxxxxxx وارد کنید.';
        errEl.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerText = '⏳ در حال ثبت...';

    var formData = new FormData();
    formData.append('action', 'lcm_submit_reservation');
    formData.append('name', name);
    formData.append('phone', phone);
    formData.append('guests', guestsVal);
    formData.append('date', date);
    formData.append('time', time);
    formData.append('note', note);

    fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', { method: 'POST', body: formData })
    .then(function(r){ return r.json(); })
    .then(function(res){
        btn.disabled = false;
        btn.innerText = '🗓️ ثبت رزرو';
        if (res.success) {
            document.getElementById('resForm').style.display = 'none';
            document.getElementById('resSuccessMsg').innerText = res.data.message;
            document.getElementById('resSuccess').style.display = 'block';
        } else {
            errEl.innerText = '❌ ' + (res.data && res.data.message ? res.data.message : 'خطا در ثبت');
            errEl.style.display = 'block';
        }
    })
    .catch(function(){
        btn.disabled = false; btn.innerText = '🗓️ ثبت رزرو';
        errEl.innerText = '❌ خطا در ارتباط با سرور. دوباره تلاش کنید.';
        errEl.style.display = 'block';
    });
}
</script>

<?php wp_footer(); ?>
</body>
</html>
<?php exit;
