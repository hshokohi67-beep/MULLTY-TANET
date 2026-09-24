<?php
ob_start();
error_reporting(0);
@ini_set('display_errors', 0);

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! session_id() ) { session_start(); }

// خواندن تنظیمات حیاتی از دیتابیس وردپرس
$theme = get_option('lcm_menu_theme', 'dark');
$max_calories = get_option('lcm_max_calories', '500');

$cafe_name = get_option('lcm_cafe_name', 'کافه لایو منو');
$cafe_logo = get_option('lcm_cafe_logo', '');

$session_table_id = isset($_SESSION['lcm_table_id']) ? intval($_SESSION['lcm_table_id']) : (isset($_COOKIE['lcm_table_id']) ? intval($_COOKIE['lcm_table_id']) : 0);

// لود کردن دسته‌بندی‌های فعال ووکامرس
$categories = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'exclude'    => array(get_option('default_product_cat'))
) );

$current_cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : ( !empty($categories) ? $categories[0]->term_id : 0 );

// لود کردن محصولات مربوط به دسته‌بندی فعلی
$cat_products = array();
if($current_cat_id > 0) {
    $cat_products = get_posts(array(
        'post_type' => 'product',
        'numberposts' => 30,
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $current_cat_id))
    ));
}

// گرفتن اسلاگ دسته‌بندی فعلی برای پاس دادن به جاوااسکریپت کش‌بک
$current_cat_obj = get_term($current_cat_id, 'product_cat');
$cat_slug_for_js = ($current_cat_obj && !is_wp_error($current_cat_obj)) ? $current_cat_obj->slug : 'all';

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="<?php echo esc_url( home_url('/lcm-manifest.json') ); ?>">
    <meta name="theme-color" content="#2ec4b6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($cafe_name); ?>">
    <?php if ( ! empty($cafe_logo) ) : ?>
        <link rel="apple-touch-icon" href="<?php echo esc_url($cafe_logo); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    
    <style>
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-color); }

       .payment-method {
    transition: all 0.2s ease;
}
.payment-method.active {
    transform: scale(1.01);
    box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
} 
        :root {
            --bg-color: <?php echo $theme == 'dark' ? '#0b090a' : '#fcfbf9'; ?>;
            --surface-color: <?php echo $theme == 'dark' ? 'rgba(22, 26, 29, 0.85)' : 'rgba(255, 255, 255, 0.9)'; ?>;
            --text-color: <?php echo $theme == 'dark' ? '#f5f3f4' : '#1c1917'; ?>;
            --accent-color: #2ec4b6; 
            --border-color: <?php echo $theme == 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(46,196,182,0.2)'; ?>;
            --ios-glass: <?php echo $theme == 'dark' ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.03)'; ?>;
            --ios-glass-active: rgba(46, 196, 182, 0.15);
        }
        
        /* 📱 ساختار مدرن و غیرکشیده پنل کاربری بر پایه iOS Card */
        .lcm-drawer { 
            position: fixed; 
            bottom: -100%; 
            left: 50%; 
            transform: translateX(-50%);
            width: 100vw; 
            max-width: 480px; 
            height: 85vh; 
            background: var(--surface-color); 
            backdrop-filter: blur(30px); 
            -webkit-backdrop-filter: blur(30px); 
            border-radius: 30px 30px 0 0; 
            z-index: 1000001; 
            transition: bottom 0.4s cubic-bezier(0.25, 1, 0.5, 1); 
            padding: 25px; 
            overflow-y: auto; 
            border: 1px solid var(--border-color); 
            border-bottom: none;
            box-shadow: 0 -15px 40px rgba(0,0,0,0.3);
        }
        .lcm-drawer.open { bottom: 0; }
        .drawer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        
        .profile-card { 
            background: var(--ios-glass); 
            padding: 15px; 
            border-radius: 20px; 
            margin-bottom: 15px; 
            border: 1px solid var(--border-color); 
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            align-items: center;
        }
        .profile-title-area { text-align: right; }
        .profile-status-area { text-align: left; }
        
        .loyalty-badge {
            background: linear-gradient(135deg, #ffb703 0%, #fb8500 100%);
            color: #000;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 900;
            display: inline-block;
            box-shadow: 0 4px 10px rgba(251,133,0,0.25);
        }

        /* استایل بنرهای اطلاع‌رسانی لایو وفاداری */
        /* استایل ویجت کیک تولد */
        .birthday-widget {
            background: var(--ios-glass);
            border: 1px solid var(--border-color);
            padding: 12px 15px;
            border-radius: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .birthday-icon {
            font-size: 1.5rem;
        }
        .birthday-text-area {
            flex: 1;
            font-size: 0.75rem;
            font-weight: bold;
            color: var(--text-color);
        }
        
        .wallet-box { background: linear-gradient(135deg, #2ec4b6 0%, #139a8c 100%); padding: 15px; border-radius: 20px; color: white; text-align: center; margin-bottom: 15px; box-shadow: 0 8px 20px rgba(46,196,182,0.2); }
        .btn-charge { width: 100%; padding: 12px; background: #ffb703; border: none; border-radius: 12px; font-weight: bold; color: #000; cursor: pointer; transition: transform 0.2s; }
        .btn-charge:active { transform: scale(0.97); }

        .order-history-item { padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Vazirmatn', Tahoma, sans-serif; }
        html, body { width: 100vw; height: 100vh; overflow: hidden; background-color: var(--bg-color); color: var(--text-color); position: fixed; top: 0; left: 0; }
        
        .fullscreen-menu { width: 100vw; height: 100vh; background: <?php echo $theme == 'dark' ? 'linear-gradient(135deg, #0b090a 0%, #161a1d 100%)' : 'linear-gradient(135deg, #fcfbf9 0%, #f3efe6 100%)'; ?>; display: flex; flex-direction: column; justify-content: space-between; position: relative; }
        .header-navigation { position: relative; z-index: 1000; background: rgba(0,0,0,0.02); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border-bottom: 1px solid var(--border-color); }
        
        .brand-identity-bar { display: flex; align-items: center; justify-content: center; padding: 15px 20px 5px 20px; gap: 15px; position: relative; }
        .brand-identity-left { display: flex; align-items: center; flex-direction: column; gap: 10px; text-align: center; }
        .brand-logo-img { max-height: 48px; max-width: 80px; object-fit: contain; border-radius: 8px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3)); }
        .brand-title-box { text-align: center; }
        .brand-title-box h1 { font-size: 1.4rem; margin-bottom: 2px; letter-spacing: -0.5px; }
        .brand-title-box p { font-size: 0.7rem; margin: 0; color: #888; }
        .account-icon-btn { width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; border: 1px solid var(--border-color); background: var(--ios-glass); color: var(--text-color); font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .lcm-waiter-call-btn { position: fixed; bottom: 95px; left: 15px; z-index: 999997; background: #ffb703; color: #000; border: none; border-radius: 50px; padding: 12px 18px; font-weight: 900; font-size: 0.8rem; display:flex; align-items:center; gap:6px; box-shadow: 0 8px 20px rgba(255,183,3,0.4); cursor: pointer; }
        .lcm-waiter-call-btn:active { transform: scale(0.95); }
        .lcm-waiter-call-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .account-icon-btn:hover { background: var(--ios-glass-active); border-color: var(--accent-color); }

        /* نوار جستجو — به‌صورت overlay از بالا می‌آید، هیچ‌چیزی را جابه‌جا نمی‌کند */
        .lcm-search-overlay {
            position: fixed; top: 0; left: 0; right: 0; z-index: 999998;
            background: var(--bg-color); border-bottom: 1px solid var(--border-color);
            transform: translateY(-100%); transition: transform 0.3s cubic-bezier(0.16,1,0.3,1);
            padding: 14px 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .lcm-search-overlay.open { transform: translateY(0); }
        .lcm-search-input-row { display: flex; align-items: center; gap: 10px; }
        .lcm-search-input-row input {
            flex: 1; padding: 12px 16px; border-radius: 14px; border: 1px solid var(--border-color);
            background: var(--ios-glass); color: var(--text-color); font-family: inherit; font-size: 0.9rem; outline: none;
        }
        .lcm-search-input-row input:focus { border-color: var(--accent-color); }
        .lcm-search-close-btn { width: 40px; height: 40px; border-radius: 50%; border: none; background: var(--ios-glass); color: var(--text-color); display:flex; align-items:center; justify-content:center; cursor: pointer; flex-shrink: 0; }
        .lcm-search-results { max-height: 60vh; overflow-y: auto; margin-top: 10px; }
        .lcm-search-result-item {
            display: flex; align-items: center; gap: 12px; padding: 10px 6px;
            border-bottom: 1px solid var(--border-color); cursor: pointer;
        }
        .lcm-search-result-item img { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
        .lcm-search-result-item .info { flex: 1; min-width: 0; }
        .lcm-search-result-item .info .title { font-size: 0.85rem; font-weight: 700; color: var(--text-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lcm-search-result-item .info .snippet { font-size: 0.72rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lcm-search-result-item .price { font-size: 0.8rem; font-weight: 800; color: var(--accent-color); flex-shrink: 0; }
        .lcm-search-empty { text-align: center; padding: 30px 10px; color: #888; font-size: 0.85rem; }
        .lcm-search-icon-btn { width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; border: 1px solid var(--border-color); background: var(--ios-glass); color: var(--text-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .lcm-search-icon-btn:hover { background: var(--ios-glass-active); border-color: var(--accent-color); }
        .lcm-search-highlight { animation: lcmSearchPulse 1.6s ease; }
        @keyframes lcmSearchPulse { 0%,100% { box-shadow: none; } 30% { box-shadow: 0 0 0 4px var(--accent-color); } }

        /* پنل فیلتر — همون سبک overlay از بالا */
        .lcm-filter-overlay {
            position: fixed; top: 0; left: 0; right: 0; z-index: 999998;
            background: var(--bg-color); border-bottom: 1px solid var(--border-color);
            transform: translateY(-100%); transition: transform 0.3s cubic-bezier(0.16,1,0.3,1);
            padding: 18px 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .lcm-filter-overlay.open { transform: translateY(0); }
        .lcm-filter-row { margin-bottom: 16px; }
        .lcm-filter-row label { display:block; font-size:0.78rem; color:#888; margin-bottom:8px; font-weight:700; }
        .lcm-filter-price-inputs { display:flex; align-items:center; gap:8px; }
        .lcm-filter-price-inputs input { flex:1; padding:10px; border-radius:10px; border:1px solid var(--border-color); background:var(--ios-glass); color:var(--text-color); font-family:inherit; font-size:0.85rem; }
        .lcm-filter-tags-row { display:flex; flex-wrap:wrap; gap:8px; }
        .lcm-filter-tag-chip { padding:7px 12px; border-radius:20px; border:1px solid var(--border-color); background:var(--ios-glass); color:var(--text-color); font-size:0.78rem; cursor:pointer; user-select:none; transition: all 0.15s; }
        .lcm-filter-tag-chip.active { background:var(--accent-color); color:#fff; border-color:var(--accent-color); }
        .lcm-filter-actions { display:flex; gap:10px; margin-top:4px; }
        .lcm-filter-actions button { flex:1; padding:12px; border-radius:12px; border:none; font-weight:800; font-size:0.85rem; cursor:pointer; }
        .lcm-filter-apply-btn { background:var(--accent-color); color:#fff; }
        .lcm-filter-reset-btn { background:var(--ios-glass); color:var(--text-color); border:1px solid var(--border-color) !important; }
        .lcm-filter-close-btn { width: 40px; height: 40px; border-radius: 50%; border: none; background: var(--ios-glass); color: var(--text-color); display:flex; align-items:center; justify-content:center; cursor: pointer; position:absolute; left:16px; top:18px; }
        .lcm-filter-badge-dot { position:absolute; top:6px; right:6px; width:9px; height:9px; border-radius:50%; background:#e5383b; display:none; }
        
        .category-tab-bar { display: flex; gap: 16px; overflow-x: auto; white-space: nowrap; flex-wrap: nowrap; padding: 30px 15px 15px 15px; align-items: center; scrollbar-width: none; -ms-overflow-style: none; cursor: grab; user-select: none; }
        .category-tab-bar::-webkit-scrollbar { display: none; }
        .category-tab-bar:active { cursor: grabbing; }

        .cat-tab { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 22px 16px 10px 16px; background: var(--ios-glass); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 18px; text-decoration: none; font-size: 0.75rem; font-weight: bold; flex-shrink: 0; position: relative; margin-top: 15px; min-width: 85px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .cat-tab.active { background: linear-gradient(135deg, var(--accent-color) 0%, #139a8c 100%); color: #fff; border-color: var(--accent-color); font-weight: 900; box-shadow: 0 8px 20px rgba(46, 196, 182, 0.3); transform: scale(1.02); }
        .cat-tab:hover { color: var(--accent-color); background: var(--ios-glass); border-color: var(--accent-color); transform: translateY(-2px); }
        .cat-tab:active { transform: scale(0.96); }

        .cat-img { width: 50px !important; height: 50px !important; min-width: 50px !important; min-height: 50px !important; border-radius: 50% !important; object-fit: cover !important; flex-shrink: 0 !important; position: absolute; top: -25px; left: 50%; transform: translateX(-50%); background: #161a1d; border: 2px solid var(--border-color) !important; transition: all 0.3s ease; }
        .cat-tab.active .cat-img { border-color: #fff !important; transform: translateX(-50%) scale(1.1); box-shadow: 0 4px 10px rgba(0,0,0,0.4); }

        .menu-grid-scroll { flex: 1; overflow-y: auto; padding: 15px 15px 90px 15px; display: grid !important; grid-template-columns: 1fr !important; gap: 15px; }
        @media (min-width: 480px) { .menu-grid-scroll { grid-template-columns: repeat(2, 1fr) !important; } }
        @media (min-width: 768px) { .menu-grid-scroll { grid-template-columns: repeat(3, 1fr) !important; gap: 18px; } }
        @media (min-width: 1024px) { .menu-grid-scroll { grid-template-columns: repeat(4, 1fr) !important; gap: 20px; } }
        
        .product-grid-card { background: var(--surface-color); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: 24px; padding: 14px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); display: flex !important; flex-direction: column; justify-content: flex-start; position: relative; overflow: hidden; height: auto !important; min-height: 420px; max-height: 560px; width: 100% !important; transition: all 0.3s; }
        
        .out-of-stock-card { opacity: 0.55; pointer-events: none; filter: grayscale(100%); }
        .out-of-stock-overlay { position: absolute; top: 35%; left: 50%; transform: translate(-50%, -50%) rotate(-10deg); background: var(--accent-color); color: #fff; padding: 8px 15px; font-size: 1rem; font-weight: 900; border-radius: 8px; z-index: 100; border: 2px dashed rgba(255,255,255,0.6); white-space: nowrap; box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        .low-stock-badge { position: absolute; top: 15px; right: 15px; background: #ffb703; color: #000; padding: 4px 10px; border-radius: 8px; font-size: 0.65rem; font-weight: bold; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.2); animation: pulseWarning 2s infinite; }
        @keyframes pulseWarning { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }

        .card-header-info { position: relative; z-index: 10; }
        .card-header-info h2 { font-size: 0.95rem; font-weight: bold; color: var(--text-color); margin-bottom: 2px; }
        .card-header-info p { font-size: 0.7rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .calorie-badge-row { display: flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: bold; color: #ffb703; margin-bottom: 4px; }

        .product-visualizer { width: 100%; height: 240px; display: flex; justify-content: center; align-items: center; position: relative; margin: 8px 0; overflow: hidden; z-index: 1; }
        .canvas-container { width: 100%; max-width: 200px; height: 100%; display: flex; justify-content: center; align-items: center; position: relative; overflow: visible; margin: 0; z-index: 1; }
        .dynamic-aura { position: absolute; width: 190px; height: 85px; border-radius: 50%; filter: blur(25px); z-index: 1; opacity: 0.65; pointer-events: none; }
        .aura-hot { background: rgba(229, 56, 59, 0.35); }
        .aura-cold { background: rgba(0, 180, 216, 0.3); }
        
        .ambient-effect-container { position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%); width: 90px; height: 75px; display: flex; justify-content: space-around; z-index: 20; pointer-events: none; overflow: hidden; background: transparent; }
        .glass-container-box { position: relative; width: 100%; height: 240px; z-index: 5; display: flex; justify-content: center; align-items: center; pointer-events: none; border: none; background: transparent; }
        .glass-image { width: auto; height: 100%; max-height: 240px; object-fit: contain; z-index: 6; pointer-events: none; }
        
        .ingredient-layer-img { position: absolute; width: 28px; height: 28px; object-fit: contain; z-index: 20; animation: dropIntoGlass 0.4s ease-out forwards; pointer-events: none; }
        @keyframes dropIntoGlass { 0% { transform: translateY(-70px) scale(0.4); opacity: 0; } 100% { transform: translateY(0) scale(1); } }

        .particle { width: 16px; height: 22px; border-radius: 50%; filter: blur(4px); animation: lcmParticleAnim 1.4s infinite linear; position: absolute; pointer-events: none; }
        .particle:nth-child(1) { left: 25%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 50%; animation-delay: 0.4s; }
        .particle:nth-child(3) { left: 75%; animation-delay: 0.8s; }
        .part-hot { background: rgba(255,255,255,0.55); animation-name: riseSteam; margin-top: -35px; }
        .part-cold { background: rgba(0, 180, 216, 0.45); animation-name: fallCold; margin-top: 45px; }
        @keyframes riseSteam { 0% { transform: translateY(85px) scale(0.5); opacity: 0; } 30% { opacity: 0.6; } 100% { transform: translateY(0px) scale(2.5); opacity: 0; } }
        @keyframes fallCold { 0% { transform: translateY(0px) scale(1.2); opacity: 0; } 30% { opacity: 0.5; } 100% { transform: translateY(85px) scale(0.7); opacity: 0; } }

        .size-selector { display: flex; gap: 4px; width: 100%; margin-bottom: 5px; position: relative; z-index: 30; }
        .size-btn { flex: 1; background: var(--ios-glass); border: 1px solid var(--border-color); color: var(--text-color); padding: 6px 4px; border-radius: 8px; cursor: pointer; font-size: 0.62rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 3px; position: relative; }
        .size-btn.active { background: var(--accent-color); color: #fff; border-color: var(--accent-color); font-weight: bold; }
        .size-badge { background: #ffb703; color: #000; font-size: 0.55rem; font-weight: 900; padding: 1px 5px; border-radius: 6px; position: absolute; top: -6px; right: -2px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }

        .ingredients-grid { display: flex; gap: 8px; overflow-x: auto; padding: 4px 2px 10px 2px; margin-bottom: 5px; position: relative; z-index: 30; width: 100%; scrollbar-width: thin; scrollbar-color: var(--accent-color) transparent; }
        .ingredients-grid::-webkit-scrollbar { height: 4px; display: block; }
        .ingredients-grid::-webkit-scrollbar-track { background: var(--ios-glass); border-radius: 10px; }
        .ingredients-grid::-webkit-scrollbar-thumb { background: var(--accent-color); border-radius: 10px; }

        .ingredient-card { min-width: 65px; flex-shrink: 0; background: var(--ios-glass); border: 1px solid var(--border-color); border-radius: 12px; padding: 6px 4px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .ingredient-card.selected { border-color: var(--accent-color); background: var(--ios-glass-active); transform: scale(0.95); }
        .ingredient-thumb-img { width: 18px; height: 18px; object-fit: contain; margin-bottom: 2px; }
        .ingredient-name { font-size: 0.55rem; display: block; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .card-footer-action { border-top: 1px solid var(--border-color); padding-top: 8px; display: flex; flex-direction: column; gap: 6px; position: relative; z-index: 30; margin-top: auto; }
        .price-row { display: flex; justify-content: space-between; align-items: center; }
        .live-price { font-size: 1.05rem; font-weight: bold; color: var(--accent-color); }
        .btn-add-item { background: var(--ios-glass-active); border: 1px solid var(--accent-color); color: var(--text-color); padding: 8px; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 0.8rem; width: 100%; text-align: center; transition: all 0.2s; }
        
        /* سبد خرید شناور — جایگزین نوار پایین */
        .cart-fab {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(120px);
            z-index: 999999; display: flex; align-items: center; gap: 10px;
            background: var(--accent-color); color: #fff; border: none; border-radius: 50px;
            padding: 0; height: 54px; box-shadow: 0 8px 32px rgba(46,196,182,0.45);
            transition: transform 0.35s cubic-bezier(0.175,0.885,0.32,1.1), box-shadow 0.2s;
            cursor: pointer; min-width: 180px;
        }
        .cart-fab.visible { transform: translateX(-50%) translateY(0); }
        .cart-fab:active { box-shadow: 0 4px 12px rgba(46,196,182,0.3); transform: translateX(-50%) scale(0.97); }
        .cart-fab-icon {
            width: 54px; height: 54px; display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.15); border-radius: 50px 0 0 50px; flex-shrink: 0; position: relative;
        }
        .cart-fab-badge {
            position: absolute; top: 6px; right: 6px; background: #ffb703; color: #000;
            font-size: 0.6rem; font-weight: 900; width: 16px; height: 16px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; line-height: 1;
        }
        .cart-fab-info { flex: 1; text-align: center; padding: 0 4px; }
        .cart-fab-total { font-size: 1rem; font-weight: 900; }
        .cart-fab-label { font-size: 0.6rem; opacity: 0.85; }
        .cart-fab-checkout {
            height: 54px; padding: 0 16px; background: rgba(0,0,0,0.2); border: none; color: #fff;
            font-weight: 900; font-size: 0.75rem; cursor: pointer; border-radius: 0 50px 50px 0;
            border-right: none; white-space: nowrap;
        }

        /* انتخاب نوع سفارش — الان توی drawer سبد خریده */
        .cart-order-type-row { display: flex; gap: 8px; margin-bottom: 14px; }
        .cart-order-type-btn {
            flex: 1; padding: 10px; border-radius: 12px; border: 1px solid var(--border-color);
            background: var(--ios-glass); color: var(--text-color); font-weight: 700; font-size: 0.8rem;
            cursor: pointer; transition: all 0.2s;
        }
        .cart-order-type-btn.active { background: var(--accent-color); color: #fff; border-color: var(--accent-color); }
        .order-type-btn.active { background: var(--accent-color) !important; color: #fff !important; }

        .cart-drawer-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 999998; display: none; backdrop-filter: blur(5px); }
        .cart-drawer-sheet { position: fixed; bottom: -100%; left: 0; width: 100vw; max-height: 80vh; background: var(--surface-color); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border-top: 1px solid var(--border-color); border-radius: 30px 30px 0 0; z-index: 1000000; transition: bottom 0.3s cubic-bezier(0.25, 1, 0.5, 1); padding: 20px; display: flex; flex-direction: column; overflow-y: auto; }
        .cart-drawer-sheet.open { bottom: 0; }
        .drawer-handle { width: 40px; height: 5px; background: rgba(255,255,255,0.2); border-radius: 3px; margin: -10px auto 15px auto; }
        .drawer-items-list { flex: 1; overflow-y: auto; padding-bottom: 20px; }
        .drawer-item-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .drawer-item-title { font-weight: bold; font-size: 0.9rem; }
        .drawer-item-meta { font-size: 0.7rem; color: #888; margin-top: 2px; }
        .drawer-qty-control { display: flex; align-items: center; gap: 12px; background: var(--ios-glass); border: 1px solid var(--border-color); border-radius: 8px; padding: 2px 8px; }
        .drawer-action-btn { background: transparent; border: none; color: var(--accent-color); font-size: 1.1rem; font-weight: bold; cursor: pointer; width: 24px; }

        .lcm-toast {
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: rgba(229, 56, 59, 0.95);
            color: #fff;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.85rem;
            z-index: 10000002;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none;
            text-align: center;
            max-width: 90%;
        }
        .lcm-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .lcm-toast.success { background: rgba(76, 175, 80, 0.95); }
    </style>
    <!-- لود کردن فایل استایل باشگاه مشتریان شما -->
    <link rel="stylesheet" href="<?php echo plugins_url( 'lcm-customer-club/assets/css/public-style.css' ); ?>">
</head>
<body>

<div class="fullscreen-menu">
    <div class="header-navigation">
        <div class="brand-identity-bar">
            <div class="brand-identity-left" style="flex-direction:row; align-items:center; gap:12px;">
                <?php if(!empty($cafe_logo)): ?>
                    <img src="<?php echo esc_url($cafe_logo); ?>" class="brand-logo-img" alt="Logo">
                <?php endif; ?>
                <div class="brand-title-box">
                    <h1><?php echo esc_html($cafe_name); ?></h1>
                    <p><?php echo $session_table_id > 0 ? '📍 لایو فاکتور میز شماره ' . $session_table_id : '☕ منوی آنلاین و هوشمند کافه'; ?></p>
                </div>
            </div>
            <button class="lcm-search-icon-btn" onclick="lcmToggleFilter(true)" aria-label="فیلتر" style="position:absolute; right:65px; top:50%; transform:translateY(-50%);">
                <i data-lucide="sliders-horizontal" style="width:19px; height:19px;"></i>
                <span class="lcm-filter-badge-dot" id="lcmFilterBadgeDot"></span>
            </button>
            <button class="lcm-search-icon-btn" onclick="lcmToggleSearch(true)" aria-label="جستجو" style="position:absolute; right:15px; top:50%; transform:translateY(-50%);"><i data-lucide="search" style="width:19px; height:19px;"></i></button>
            <button class="account-icon-btn" onclick="lcmToggleAccountDrawer(true)" aria-label="حساب کاربری" style="position:absolute; left:15px; top:50%; transform:translateY(-50%);"><i data-lucide="user" style="width:20px; height:20px;"></i></button>
        </div>

        <div class="lcm-filter-overlay" id="lcmFilterOverlay">
            <button class="lcm-filter-close-btn" onclick="lcmToggleFilter(false)" aria-label="بستن"><i data-lucide="x" style="width:16px; height:16px;"></i></button>
            <div class="lcm-filter-row" style="margin-top:8px;">
                <label>💰 محدوده قیمت (تومان)</label>
                <div class="lcm-filter-price-inputs">
                    <input type="number" id="lcmFilterMinPrice" placeholder="حداقل" min="0">
                    <span style="color:#888;">تا</span>
                    <input type="number" id="lcmFilterMaxPrice" placeholder="حداکثر" min="0">
                </div>
            </div>
            <div class="lcm-filter-row">
                <label>⚡ حداکثر کالری</label>
                <input type="number" id="lcmFilterMaxCalory" placeholder="مثلاً: 400" min="0" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-color); background:var(--ios-glass); color:var(--text-color); font-family:inherit; font-size:0.85rem;">
            </div>
            <div class="lcm-filter-row" id="lcmFilterDietRow" style="display:none;">
                <label>🏷️ برچسب‌های رژیمی</label>
                <div class="lcm-filter-tags-row" id="lcmFilterTagsContainer"></div>
            </div>
            <div class="lcm-filter-actions">
                <button class="lcm-filter-reset-btn" onclick="lcmResetFilters()">پاک‌کردن فیلترها</button>
                <button class="lcm-filter-apply-btn" onclick="lcmApplyFilters()">اعمال فیلتر</button>
            </div>
        </div>

        <div class="lcm-search-overlay" id="lcmSearchOverlay">
            <div class="lcm-search-input-row">
                <input type="text" id="lcmSearchInput" placeholder="جستجوی نام یا توضیحات محصول..." oninput="lcmHandleSearchInput(this.value)">
                <button class="lcm-search-close-btn" onclick="lcmToggleSearch(false)" aria-label="بستن"><i data-lucide="x" style="width:18px; height:18px;"></i></button>
            </div>
            <div class="lcm-search-results" id="lcmSearchResults"></div>
        </div>

        <?php
        $lcm_cafe_is_open = function_exists('lcm_is_cafe_currently_open') ? lcm_is_cafe_currently_open() : true;
        if ( ! $lcm_cafe_is_open ) :
            $lcm_reopen_text = function_exists('lcm_get_next_reopen_text') ? lcm_get_next_reopen_text() : '';
        ?>
        <div style="margin: 0 15px 12px; background: linear-gradient(135deg, rgba(255,183,3,0.15), rgba(251,133,0,0.1)); border: 1px dashed #ffb703; border-radius: 16px; padding: 14px; display:flex; align-items:center; gap:12px;">
            <i data-lucide="moon-star" style="width:28px; height:28px; color:#ffb703; flex-shrink:0;"></i>
            <div style="font-size:0.8rem; line-height:1.8;">
                <strong style="color:#ffb703;">فعلاً بسته‌ایم 🌙</strong><br>
                ولی نگران نباش، ما همیشه حواسمون به تو و برنامه‌ی روزانه‌ته! سفارشت رو الان ثبت کن و بگو کِی می‌خوای تحویل بگیری — سر همون ساعت آماده‌ش می‌کنیم.
                <?php if ($lcm_reopen_text) : ?>
                    <br><span style="opacity:0.8;">(بازگشایی: <?php echo esc_html($lcm_reopen_text); ?>)</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $lcm_menu_lucky_item = function_exists('lcm_get_daily_lucky_item') ? lcm_get_daily_lucky_item() : null;
        if ( $lcm_menu_lucky_item ) :
        ?>
        <div style="margin: 0 15px 12px; background: linear-gradient(135deg, rgba(255,183,3,0.18), rgba(251,133,0,0.12)); border: 1px solid #ffb703; border-radius: 18px; padding: 14px; display:flex; align-items:center; gap:14px;">
            <?php if ( $lcm_menu_lucky_item['image'] ) : ?>
                <img src="<?php echo esc_url($lcm_menu_lucky_item['image']); ?>" style="width:56px; height:56px; border-radius:14px; object-fit:cover; flex-shrink:0;" loading="lazy">
            <?php else : ?>
                <i data-lucide="gift" style="width:32px; height:32px; color:#ffb703; flex-shrink:0;"></i>
            <?php endif; ?>
            <div style="flex:1;">
                <strong style="color:#ffb703; font-size:0.85rem;">🎁 آیتم شانسی امروز: <?php echo esc_html($lcm_menu_lucky_item['title']); ?></strong>
                <div style="font-size:0.8rem; margin-top:3px;">
                    <span style="text-decoration:line-through; opacity:0.6;"><?php echo number_format($lcm_menu_lucky_item['base_price']); ?></span>
                    <span style="color:#ffb703; font-weight:900;"> <?php echo number_format($lcm_menu_lucky_item['discounted_price']); ?> ت</span>
                    (<?php echo esc_html($lcm_menu_lucky_item['discount_percent']); ?>٪ تخفیف)
                </div>
                <div style="font-size:0.7rem; opacity:0.75; margin-top:3px;">⏳ فقط تا ساعت <span id="lcmLuckyCountdown"><?php echo esc_html(str_pad($lcm_menu_lucky_item['cutoff_hour'], 2, '0', STR_PAD_LEFT)); ?>:۰۰</span> امروز</div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const cutoffHour = <?php echo intval($lcm_menu_lucky_item['cutoff_hour']); ?>;
                const el = document.getElementById('lcmLuckyCountdown');
                if (!el) return;
                function updateLuckyCountdown() {
                    const now = new Date();
                    const cutoff = new Date();
                    cutoff.setHours(cutoffHour, 0, 0, 0);
                    const diffMs = cutoff - now;
                    if (diffMs <= 0) { el.innerText = 'به پایان رسید'; return; }
                    const h = String(Math.floor(diffMs / 3600000)).padStart(2, '0');
                    const m = String(Math.floor((diffMs % 3600000) / 60000)).padStart(2, '0');
                    el.innerText = h + ':' + m + ' مانده';
                }
                updateLuckyCountdown();
                setInterval(updateLuckyCountdown, 30000);
            });
        </script>
        <?php endif; ?>

        <div class="category-tab-bar" id="lcmCatBar">
            <?php if(!empty($categories)) : ?>
                <?php foreach($categories as $cat) : 
                    $thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
                    $cat_img_url = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : 'https://cdn-icons-png.flaticon.com/512/1047/1047503.png';
                    $cat_url = site_url('/live-menu/?cat_id=' . $cat->term_id . '&table_id=' . $session_table_id);
                    ?>
                    <a href="<?php echo esc_url($cat_url); ?>" data-cat-id="<?php echo esc_attr($cat->term_id); ?>" onclick="return lcmSwitchCategory(event, <?php echo intval($cat->term_id); ?>)" class="cat-tab <?php echo $current_cat_id == $cat->term_id ? 'active' : ''; ?>">
                        <img src="<?php echo esc_url($cat_img_url); ?>" class="cat-img">
                        <?php echo esc_html($cat->name); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="menu-grid-scroll" id="lcmMenuGridScroll">
        <?php include LCM_PLUGIN_DIR . 'public/menu-grid-partial.php'; ?>
    </div>

    <button id="lcmWaiterCallBtn" class="lcm-waiter-call-btn" style="display:none;" onclick="lcmCallWaiter()">
        <i data-lucide="bell-ring" style="width:16px; height:16px;"></i> صدا زدن گارسون
    </button>

    <!-- سبد خرید شناور -->
    <button class="cart-fab" id="stickyCartHub" onclick="lcmToggleCartDrawer(true)" aria-label="سبد خرید">
        <div class="cart-fab-icon">
            <i data-lucide="shopping-cart" style="width:20px; height:20px;"></i>
            <span class="cart-fab-badge" id="cartFabBadge">0</span>
        </div>
        <div class="cart-fab-info">
            <div class="cart-fab-total"><span id="hubTotalPrice">0</span> ت</div>
            <div class="cart-fab-label">مشاهده سبد خرید</div>
        </div>
        <button class="cart-fab-checkout" onclick="event.stopPropagation(); lcmSubmitFullTable()">ثبت سفارش ←</button>
    </button>
</div>

<div class="cart-drawer-overlay" id="cartOverlay" onclick="lcmToggleCartDrawer(false)"></div>
<div class="cart-drawer-sheet" id="cartDrawer">
    <div class="drawer-handle" onclick="lcmToggleCartDrawer(false)"></div>
    <h3 style="font-size: 1.1rem; font-weight: 900; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; display:flex; align-items:center; gap:8px;"><i data-lucide="shopping-cart" style="width:18px; height:18px;"></i> سبد خرید</h3>

    <!-- انتخاب نوع سفارش — از نوار پایین اینجا آمد -->
    <div class="cart-order-type-row">
        <button class="cart-order-type-btn active" id="btn-type-salon" onclick="lcmSetOrderType('salon')">📍 سالن</button>
        <button class="cart-order-type-btn" id="btn-type-takeaway" onclick="lcmSetOrderType('takeaway')">🛍️ بیرون‌بر</button>
    </div>

    <div class="drawer-items-list" id="drawerItemsContainer"></div>

    <!-- دکمه‌ی ثبت نهایی توی drawer -->
    <button class="btn-checkout-hub" id="drawerCheckoutBtn" onclick="lcmSubmitFullTable()" style="width:100%; margin-top:14px; padding:14px; border-radius:14px; font-size:0.9rem;">🛒 ثبت سفارش</button>
</div>

<div id="lcm-custom-toast" class="lcm-toast"></div>

<script>
    $lcmGridData = {};
    let $globalCartQueue = [];
    let currentOrderType = 'salon';
    const currentTableId = <?php echo $session_table_id; ?>;
    const LCM_NONCE = "<?php echo esc_js( wp_create_nonce( 'lcm_public_actions' ) ); ?>";
    const LCM_CAFE_IS_OPEN = <?php echo $lcm_cafe_is_open ? 'true' : 'false'; ?>;

    function lcmShowToast(message, isError = true) {
        const toast = document.getElementById('lcm-custom-toast');
        if (!toast) return;
        
        toast.innerText = message;
        if (isError) { toast.classList.remove('success'); } 
        else { toast.classList.add('success'); }
        
        toast.classList.add('show');
        setTimeout(() => { toast.classList.remove('show'); }, 3500);
    }

    document.addEventListener("DOMContentLoaded", function() {
        try {
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        } catch(e) { console.error('lucide init error:', e); }

        try {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/lcm-push-sw.js').catch(function(err) {
                    console.error('lcm service worker registration error:', err);
                });
            }
        } catch(e) { console.error('lcm PWA setup error:', e); }

        try {
            if (currentTableId > 0) {
                const waiterBtn = document.getElementById('lcmWaiterCallBtn');
                if (waiterBtn) { waiterBtn.style.display = 'flex'; }
            }
        } catch(e) { console.error('lcm waiter button error:', e); }

        try {
            const savedCart = localStorage.getItem('lcm_live_cart_hub');
            if (savedCart) {
                $globalCartQueue = JSON.parse(savedCart);
                if(!Array.isArray($globalCartQueue)) { $globalCartQueue = []; }
                lcmUpdateHubDisplay();
            }
        } catch(e) { $globalCartQueue = []; }

        try {
            lcmPullTableCartFromServer();
            if(currentTableId > 0) {
                let lcmPollTimer = setInterval(lcmPullTableCartFromServer, 7000);
                // وقتی تب کاربر در پس‌زمینه است (مثلاً رفته سراغ اپ دیگری)، سوال از سرور را متوقف کن
                // و وقتی برگشت، بلافاصله یک‌بار به‌روزرسانی کن و دوباره شروع کن. این بار سرور را
                // بدون از دست دادن هیچ قابلیتی، به‌طور محسوسی کم می‌کند.
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        clearInterval(lcmPollTimer);
                    } else {
                        lcmPullTableCartFromServer();
                        lcmPollTimer = setInterval(lcmPullTableCartFromServer, 7000);
                    }
                });
            }
        } catch(e) { console.error('lcm cart sync error:', e); }

        try {
            lcmInitProductCards();
        } catch(e) { console.error('lcm product grid init error:', e); }

        try {
            const slider = document.getElementById('lcmCatBar');
            if(slider) {
                let isDown = false; let startX; let scrollLeft;
                slider.addEventListener('mousedown', (e) => { isDown = true; startX = e.pageX - slider.offsetLeft; scrollLeft = slider.scrollLeft; });
                slider.addEventListener('mouseleave', () => { isDown = false; });
                slider.addEventListener('mouseup', () => { isDown = false; });
                slider.addEventListener('mousemove', (e) => { if(!isDown) return; e.preventDefault(); const x = e.pageX - slider.offsetLeft; const walk = (x - startX) * 1.5; slider.scrollLeft = scrollLeft - walk; });
            }
        } catch(e) { console.error('lcm cat slider error:', e); }
    });

    function lcmToggleCartDrawer(open) {
        const overlay = document.getElementById('cartOverlay');
        const drawer = document.getElementById('cartDrawer');
        if(overlay && drawer) {
            overlay.style.display = open ? 'block' : 'none';
            drawer.classList.toggle('open', open);
            if(open) { lcmRenderDrawerItems(); }
        }
    }

    function lcmSetOrderType(type) {
        currentOrderType = type;
        const btnSalon = document.getElementById('btn-type-salon');
        const btnTakeaway = document.getElementById('btn-type-takeaway');
        if(btnSalon) btnSalon.classList.toggle('active', type === 'salon');
        if(btnTakeaway) btnTakeaway.classList.toggle('active', type === 'takeaway');
        lcmPushTableCartToServer();
    }

    function lcmGridVarSize(pId, element) {
        if(!$lcmGridData[pId]) return;
        const card = document.getElementById(`card-${pId}`);
        if(card) { card.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('active')); }
        element.classList.add('active');
        
        $lcmGridData[pId].currentPrice = parseInt(element.getAttribute('data-var-price')) || 0;
        $lcmGridData[pId].sizeLabel = element.getAttribute('data-label');
        $lcmGridData[pId].varId = parseInt(element.getAttribute('data-var-id')) || 0;
        
        const gBox = document.getElementById(`glass-box-${pId}`);
        if(gBox) {
            let scale = 1;
            if(element.getAttribute('data-label') === 'کوچک') scale = 0.85;
            if(element.getAttribute('data-label') === 'بزرگ') scale = 1.12;
            gBox.style.transform = `scale(${scale})`;
        }

        lcmGridUpdateDisplay(pId);
    }

    function lcmGridToggleAddon(pId, element, addonId, price, imgUrl) {
        if(!$lcmGridData[pId]) return;
        element.classList.toggle('selected');
        const isSelected = element.classList.contains('selected');
        const title = element.getAttribute('data-addon-title') || 'افزودنی';
        
        if (isSelected) {
            $lcmGridData[pId].extraPrice += price;
            $lcmGridData[pId].selectedAddons.push(addonId);
            $lcmGridData[pId].selectedAddonTitles.push(title);
        } else {
            $lcmGridData[pId].extraPrice -= price;
            $lcmGridData[pId].selectedAddons = $lcmGridData[pId].selectedAddons.filter(id => id !== addonId);
            $lcmGridData[pId].selectedAddonTitles = $lcmGridData[pId].selectedAddonTitles.filter(t => t !== title);
        }
        lcmGridUpdateDisplay(pId);
    }

    // مقداردهی اولیه‌ی کارت‌های محصول (قیمت/تخفیف/سایز)؛ هم موقع بارگذاری اول صفحه
    // فراخوانی می‌شود، هم بعد از هر جابه‌جایی AJAX بین دسته‌بندی‌ها
    function lcmInitProductCards() {
        document.querySelectorAll('.product-grid-card').forEach(card => {
            const pId = card.id.replace('card-', '');
            const activeSizeBtn = card.querySelector('.size-btn.active');
            const cardDiscountPercent = parseFloat(card.getAttribute('data-discount-percent')) || 0;
            const cardDiscountColor = card.getAttribute('data-discount-color') || '#43e97b';

            let basePrice = activeSizeBtn ? parseInt(activeSizeBtn.getAttribute('data-var-price')) : (parseInt(card.getAttribute('data-base-price')) || 0);

            $lcmGridData[pId] = {
                title: card.getAttribute('data-title') || 'نوشیدنی',
                catSlug: card.getAttribute('data-cat-slug') || 'all',
                currentPrice: basePrice,
                sizeLabel: activeSizeBtn ? activeSizeBtn.getAttribute('data-label') : 'تک سایز',
                varId: activeSizeBtn ? parseInt(activeSizeBtn.getAttribute('data-var-id')) : 0,
                extraPrice: 0,
                selectedAddons: [],
                selectedAddonTitles: [],
                discountPercent: cardDiscountPercent,
                discountColor: cardDiscountColor
            };
            lcmGridUpdateDisplay(pId);
        });
    }

    let lcmIsSwitchingCategory = false;
    /**
     * جابه‌جایی بین تب‌های دسته‌بندی بدون رفرش کامل صفحه. اگر به هر دلیلی (قطعی
     * اینترنت، خطای سرور) درخواست AJAX شکست بخورد، به همان روش قدیمی (رفتن به
     * لینک واقعی صفحه) برمی‌گردد تا کاربر هیچ‌وقت گیر نکند.
     */
    function lcmSwitchCategory(evt, catId, updateHistory = true) {
        if (evt) { evt.preventDefault(); }
        if (lcmIsSwitchingCategory) { return false; }

        const grid = document.getElementById('lcmMenuGridScroll');
        const clickedTab = evt ? evt.currentTarget : null;
        if (!grid) { return true; } // فایل partial پیدا نشد؛ ایمن‌ترین کار، بازگشت به رفتار قدیمی است

        lcmIsSwitchingCategory = true;
        if (clickedTab) { clickedTab.style.opacity = '0.6'; }

        let formData = new FormData();
        formData.append('action', 'lcm_get_menu_grid');
        formData.append('cat_id', catId);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.html) {
                    grid.innerHTML = res.data.html;
                    lcmInitProductCards();
                    if (typeof lcmResetFilters === 'function') { lcmResetFilters(); }

                    document.querySelectorAll('.cat-tab').forEach(tab => tab.classList.remove('active'));
                    if (clickedTab) { clickedTab.classList.add('active'); clickedTab.style.opacity = ''; }

                    // آدرس صفحه را فقط برای کلیک واقعی کاربر آپدیت کن؛ وقتی این تابع از
                    // دکمه‌ی بازگشت/پیشروی مرورگر صدا زده می‌شود (updateHistory=false)،
                    // مرورگر خودش آدرس را درست کرده و pushState دوباره اینجا، تاریخچه
                    // را خراب و ورودی‌های جلوتر را حذف می‌کرد — دقیقاً همان چیزی که باعث
                    // می‌شد بار دوم دکمه‌ی «بازگشت» رفتار عجیب نشان بدهد.
                    if (updateHistory) {
                        const newUrl = clickedTab ? clickedTab.getAttribute('href') : window.location.href;
                        history.pushState({ lcmCatId: catId }, '', newUrl);
                    }

                    grid.scrollTop = 0;
                } else if (clickedTab) {
                    window.location.href = clickedTab.getAttribute('href');
                }
            })
            .catch(() => {
                // شبکه مشکل داشت؛ به روش امن قدیمی (رفرش واقعی صفحه) برگرد
                if (clickedTab) { window.location.href = clickedTab.getAttribute('href'); }
            })
            .finally(() => {
                lcmIsSwitchingCategory = false;
            });

        return false;
    }

    // پشتیبانی از دکمه‌ی بازگشت/پیشروی مرورگر بعد از جابه‌جایی AJAX دسته‌بندی‌ها
    window.addEventListener('popstate', function(evt) {
        const urlParams = new URLSearchParams(window.location.search);
        const catId = parseInt(urlParams.get('cat_id')) || 0;
        if (catId > 0) {
            const matchingTab = document.querySelector(`.cat-tab[data-cat-id="${catId}"]`);
            const fakeEvt = matchingTab ? { preventDefault(){}, currentTarget: matchingTab } : null;
            lcmSwitchCategory(fakeEvt, catId, false);
        }
    });

    function lcmGridUpdateDisplay(pId) {
        const data = $lcmGridData[pId];
        if(!data) return;
        
        const priceContainer = document.getElementById(`price-text-${pId}`);
        if(!priceContainer) return;

        const rawFinalPrice = data.currentPrice + data.extraPrice;

        if(data.discountPercent > 0){
            const discountedPrice = Math.round(rawFinalPrice * ((100 - data.discountPercent) / 100));
            priceContainer.innerHTML = `
                <span class="lcm-discounted-price" style="color: ${data.discountColor}; font-weight: bold;">${discountedPrice.toLocaleString('fa-IR')}</span>
                <span class="lcm-original-price" style="text-decoration: line-through; color: #888; font-size: 0.75rem; margin-right: 8px;">${rawFinalPrice.toLocaleString('fa-IR')}</span>
                ت
            `;
        } else {
            priceContainer.innerHTML = `<span>${rawFinalPrice.toLocaleString('fa-IR')}</span> ت`;
        }
    }

    function lcmGridAddToCart(pId) {
        const data = $lcmGridData[pId];
        if(!data) return;
        
        let finalPrice = data.currentPrice + data.extraPrice;
        if(data.discountPercent > 0){
            finalPrice = Math.round(finalPrice * ((100 - data.discountPercent) / 100));
        }

        const comboKey = `${pId}-${data.sizeLabel}-${data.selectedAddons.sort().join(',')}`;

        $globalCartQueue.push({
            combo_key: comboKey,
            main_id: pId,
            var_id: data.varId,
            title: data.title,
            cat_slug: data.catSlug, 
            size_label: data.sizeLabel,
            addons: [...data.selectedAddons],
            addon_titles: [...data.selectedAddonTitles],
            price: finalPrice
        });

        localStorage.setItem('lcm_live_cart_hub', JSON.stringify($globalCartQueue));
        lcmPushTableCartToServer();
        lcmUpdateHubDisplay();

        const btn = document.getElementById(`btn-add-text-${pId}`);
        if(btn) {
            btn.innerText = '✅ اضافه شد'; btn.style.backgroundColor = '#4caf50';
            setTimeout(() => { btn.innerText = '➕ افزودن به میز'; btn.style.backgroundColor = 'var(--ios-glass-active)'; }, 1000);
        }
    }

    function lcmSyncCardCounters() {
        document.querySelectorAll('.size-badge').forEach(b => b.remove());
        let counts = {};
        $globalCartQueue.forEach(item => {
            let key = `size-btn-${item.main_id}-${item.size_label}`;
            counts[key] = (counts[key] || 0) + 1;
        });
        Object.keys(counts).forEach(btnId => {
            const btn = document.getElementById(btnId);
            if (btn) {
                const badge = document.createElement('span');
                badge.className = 'size-badge';
                badge.innerText = counts[btnId].toLocaleString('fa-IR');
                btn.appendChild(badge);
            }
        });
    }

    function lcmRenderDrawerItems() {
        const container = document.getElementById('drawerItemsContainer');
        if(!container) return;
        container.innerHTML = '';

        if($globalCartQueue.length === 0) {
            container.innerHTML = '<p style="text-align:center; padding:30px; color:#666;">سبد سفارشات میز خالی است ☕</p>';
            return;
        }

        let uniqueItems = {};
        $globalCartQueue.forEach(item => {
            if(!uniqueItems[item.combo_key]) { uniqueItems[item.combo_key] = { ...item, qty: 0 }; }
            uniqueItems[item.combo_key].qty++;
        });

        Object.keys(uniqueItems).forEach(key => {
            const item = uniqueItems[key];
            const metaText = `سایز ${item.size_label} ${item.addon_titles.length > 0 ? ' + ' + item.addon_titles.join('، ') : ''}`;
            
            const row = document.createElement('div');
            row.className = 'drawer-item-row';
            row.innerHTML = `
                <div>
                    <div class="drawer-item-title">${item.title}</div>
                    <div class="drawer-item-meta">${metaText}</div>
                    <div style="font-size:0.8rem; color:var(--accent-color); font-weight:bold; margin-top:4px;">${(item.price * item.qty).toLocaleString('fa-IR')} ت</div>
                </div>
                <div class="drawer-qty-control">
                    <button class="drawer-action-btn" onclick="lcmChangeDrawerQty('${item.combo_key}', -1)">-</button>
                    <span style="font-weight:bold; font-size:0.85rem;">${item.qty}</span>
                    <button class="drawer-action-btn" onclick="lcmChangeDrawerQty('${item.combo_key}', 1)">+</button>
                </div>
            `;
            container.appendChild(row);
        });
    }

    function lcmChangeDrawerQty(comboKey, change) {
        let index = $globalCartQueue.findIndex(item => item.combo_key === comboKey);
        if (change === 1 && index !== -1) {
            let copy = JSON.parse(JSON.stringify($globalCartQueue[index]));
            $globalCartQueue.push(copy);
        } else if (change === -1 && index !== -1) {
            $globalCartQueue.splice(index, 1);
        }
        localStorage.setItem('lcm_live_cart_hub', JSON.stringify($globalCartQueue));
        lcmPushTableCartToServer();
        lcmUpdateHubDisplay();
        lcmRenderDrawerItems();
    }

    function lcmPushTableCartToServer() {
        if(currentTableId <= 0) return;
        let formData = new FormData();
        formData.append('action', 'lcm_sync_table_cart');
        formData.append('table_id', currentTableId);
        formData.append('cart_data', JSON.stringify($globalCartQueue));
        formData.append('order_type', currentOrderType);
        formData.append('lcm_nonce', LCM_NONCE);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData });
    }

    function lcmPullTableCartFromServer() {
        if(currentTableId <= 0) return;
        let formData = new FormData();
        formData.append('action', 'lcm_sync_table_cart');
        formData.append('table_id', currentTableId);
        formData.append('lcm_nonce', LCM_NONCE);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(resData => {
            if(resData.success && resData.data) {
                let serverCart = resData.data;
                if(JSON.stringify(serverCart) !== JSON.stringify($globalCartQueue)) {
                    $globalCartQueue = serverCart;
                    localStorage.setItem('lcm_live_cart_hub', JSON.stringify($globalCartQueue));
                    lcmUpdateHubDisplay();
                    if(document.getElementById('cartDrawer').classList.contains('open')) { lcmRenderDrawerItems(); }
                }
            }
        });
    }

    function lcmUpdateHubDisplay() {
        let totalHubPrice = 0;
        $globalCartQueue.forEach(item => totalHubPrice += item.price);
        
        const priceDisplay = document.getElementById('hubTotalPrice');
        if(priceDisplay) priceDisplay.innerText = totalHubPrice.toLocaleString('fa-IR');

        // بج تعداد آیتم‌ها روی دکمه‌ی شناور
        const badge = document.getElementById('cartFabBadge');
        if(badge) badge.innerText = $globalCartQueue.length;

        const hub = document.getElementById('stickyCartHub');
        if(hub) {
            if($globalCartQueue.length > 0) { hub.classList.add('visible'); } 
            else { hub.classList.remove('visible'); lcmToggleCartDrawer(false); }
        }
        lcmSyncCardCounters();
    }

    function lcmSubmitFullTable() {
        if($globalCartQueue.length === 0) return;
        // 🔒 ورود اجباری
        const userPhone = localStorage.getItem('lcm_user_phone');
        if (!userPhone) {
            lcmShowToast('برای ثبت سفارش ابتدا وارد حساب کاربری شوید 🔐', true);
            lcmToggleCartDrawer(false);
            setTimeout(function() {
                const popupBtn = document.querySelector('.lcm-open-popup-btn, [onclick*="lcmOpenPopup"]');
                if (popupBtn) { popupBtn.click(); }
                else if (typeof lcmToggleAccountDrawer === 'function') { lcmToggleAccountDrawer(true); }
            }, 800);
            return;
        }
        if (typeof showPaymentModal === 'function') {
            lcmToggleCartDrawer(false);
            showPaymentModal();
        } else {
            lcmDirectSubmit();
        }
    }

    function lcmDirectSubmit() {
        if($globalCartQueue.length === 0) return;
        const btn = document.querySelector('.btn-checkout-hub');
        if(btn) { btn.innerText = '⏳ ارسال...'; btn.disabled = true; }

        let mainItem = $globalCartQueue[0];
        let allAddons = [...mainItem.addons];
        for(let i = 1; i < $globalCartQueue.length; i++) {
            let targetId = $globalCartQueue[i].var_id > 0 ? $globalCartQueue[i].var_id : $globalCartQueue[i].main_id;
            allAddons.push(targetId);
            $globalCartQueue[i].addons.forEach(aId => allAddons.push(aId));
        }
        let firstTargetId = mainItem.var_id > 0 ? mainItem.var_id : mainItem.main_id;
        let formData = new FormData();
        formData.append('action', 'lcm_add_custom_product_to_cart');
        formData.append('main_id', firstTargetId);
        formData.append('addons', allAddons.join(','));
        formData.append('order_type', currentOrderType);
        formData.append('table_id', currentTableId);
        formData.append('payment_method', 'salon');
        formData.append('user_phone', localStorage.getItem('lcm_user_phone') || '');
        formData.append('lcm_nonce', LCM_NONCE);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(resData => {
            if(resData.success) {
                lcmToggleCartDrawer(false);
                localStorage.removeItem('lcm_live_cart_hub');
                let resetData = new FormData();
                resetData.append('action', 'lcm_sync_table_cart');
                resetData.append('table_id', currentTableId);
                resetData.append('cart_data', '[]');
                resetData.append('lcm_nonce', LCM_NONCE);
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: resetData });
                if(resData.data && resData.data.redirect_url) {
                    window.location.href = resData.data.redirect_url;
                } else { window.location.reload(); }
            } else {
                lcmShowToast(resData.data && resData.data.message ? resData.data.message : 'خطایی رخ داد.', true);
                if(btn) { btn.innerText = '🛒 ثبت سفارش'; btn.disabled = false; }
            }
        });
    }
</script>

<?php if (file_exists(WP_PLUGIN_DIR . '/lcm-customer-club/public/views/view-popup.php')) {
    require_once WP_PLUGIN_DIR . '/lcm-customer-club/public/views/view-popup.php';
} ?>

<script>
    var lcmData = {
        ajax_url: "<?php echo admin_url( 'admin-ajax.php' ); ?>"
    };
</script>
<script src="<?php echo includes_url('/js/jquery/jquery.min.js'); ?>"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<!-- لود کردن فایل اسکریپت باشگاه مشتریان شما -->
<script src="<?php echo plugins_url( 'lcm-customer-club/assets/js/public-script.js' ); ?>"></script>

<!-- ==================== مودال پرداخت شیشه‌ای + منطق کامل ==================== -->
<div id="lcm-payment-modal" onclick="if (event.target.id === 'lcm-payment-modal') closePaymentModal()" 
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99999; align-items:center; justify-content:center; backdrop-filter: blur(8px);">
  
  <div onclick="event.stopImmediatePropagation()" 
       style="background: rgba(30, 41, 59, 0.75); 
              backdrop-filter: blur(24px); 
              -webkit-backdrop-filter: blur(24px);
              border: 1px solid rgba(255,255,255,0.15);
              border-radius: 24px; 
              width: 94%; 
              max-width: 420px; 
              max-height: 88vh;
              overflow-y: auto;
              padding: 28px 24px; 
              margin: 16px;
              color: white;
              box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:22px;">
      <h3 style="font-size:1.35rem; font-weight:900; margin:0;">💳 انتخاب روش پرداخت</h3>
      <span onclick="closePaymentModal()" style="font-size:30px; cursor:pointer; opacity:0.6;">&times;</span>
    </div>

    <div style="display:flex; flex-direction:column; gap:12px;">

      <!-- آنلاین -->
      <div onclick="selectPaymentMethod(this, 'online')" class="payment-method active" data-method="online"
           style="border:2px solid #e5383b; background:rgba(229,56,59,0.12); border-radius:18px; padding:16px; cursor:pointer;">
        <div style="display:flex; align-items:center; gap:14px;">
          <div style="font-size:28px;">🏦</div>
          <div style="flex:1;">
            <div style="font-weight:900; font-size:1.1rem;">پرداخت آنلاین</div>
            <div style="font-size:0.8rem; opacity:0.75; margin-top:3px;">با درگاه بانکی</div>
          </div>
          <div style="background:#e5383b; color:white; font-size:0.7rem; padding:3px 12px; border-radius:999px; font-weight:700;">پیشنهادی</div>
        </div>
      </div>

      <!-- کیف پول -->
      <div onclick="selectPaymentMethod(this, 'wallet')" class="payment-method" data-method="wallet"
           style="border:2px solid #f59e0b; background:rgba(245,158,11,0.1); border-radius:18px; padding:16px; cursor:pointer;">
        <div style="display:flex; align-items:center; gap:14px;">
          <div style="font-size:28px;">👛</div>
          <div style="flex:1;">
            <div style="font-weight:900; font-size:1.1rem;">کیف پول باشگاه</div>
            <div id="modal-wallet-balance" style="font-size:0.85rem; color:#fbbf24; margin-top:4px; font-weight:600;">
              برای مشاهده موجودی کلیک کنید
            </div>
          </div>
        </div>
      </div>

      <!-- اگر موجودی کیف پول کمتر از جمع سفارش باشد، این بخش برای انتخاب نحوه‌ی تسویه‌ی مابقی نمایش داده می‌شود -->
      <div id="lcm-wallet-remaining-box" style="display:none; background:rgba(255,255,255,0.04); border:1px dashed rgba(245,158,11,0.5); border-radius:14px; padding:12px;">
        <div style="font-size:0.8rem; color:#fbbf24; font-weight:700; margin-bottom:8px;" id="lcm-wallet-remaining-text"></div>
        <div style="display:flex; gap:8px;">
          <button type="button" onclick="selectRemainingMethod('salon', this)" data-remaining="salon"
                  style="flex:1; padding:10px; border-radius:10px; border:2px solid #0ea5e9; background:rgba(14,165,233,0.15); color:#fff; font-weight:700; cursor:pointer;">
            🪑 مابقی نقدی در سالن
          </button>
          <button type="button" onclick="selectRemainingMethod('online', this)" data-remaining="online"
                  style="flex:1; padding:10px; border-radius:10px; border:2px solid #64748b; background:rgba(255,255,255,0.03); color:#fff; font-weight:700; cursor:pointer;">
            🏦 مابقی آنلاین
          </button>
        </div>
      </div>

      <!-- پرداخت در سالن -->
      <div onclick="selectPaymentMethod(this, 'salon')" class="payment-method" data-method="salon"
           style="border:2px solid #0ea5e9; background:rgba(14,165,233,0.1); border-radius:18px; padding:16px; cursor:pointer;">
        <div style="display:flex; align-items:center; gap:14px;">
          <div style="font-size:28px;">🪑</div>
          <div style="flex:1;">
            <div style="font-weight:900; font-size:1.1rem;">پرداخت در سالن (نقدی)</div>
            <div style="font-size:0.8rem; opacity:0.75; margin-top:3px;">هنگام تحویل</div>
          </div>
        </div>
      </div>

    </div>

    <div id="lcmPreOrderTimeBox" style="margin-top:18px; display:none; background: rgba(255,183,3,0.1); border: 1px dashed #ffb703; border-radius:14px; padding:14px;">
      <label style="font-size:0.85rem; color:#ffb703; font-weight:800; display:block; margin-bottom:8px;"><i data-lucide="clock" style="width:16px; height:16px; vertical-align:-3px;"></i> چه ساعتی می‌خواید تحویل بگیرید؟</label>
      <input type="time" id="lcmPreOrderTimeInput" style="width:100%; padding:12px; border-radius:12px; border:1px solid #ffb703; background:rgba(255,255,255,0.05); color:#fff; font-size:0.95rem;">
      <p style="font-size:0.7rem; color:#aaa; margin-top:6px; margin-bottom:0;">چون الان کافه بسته‌ست، این قسمت رو پر کنید تا بدونیم سفارشتون رو برای چه ساعتی آماده کنیم 🙏</p>
    </div>

    <div style="margin-top:18px;">
      <label style="font-size:0.8rem; color:#aaa; display:block; margin-bottom:6px;">📝 یادداشتی برای آشپز دارید؟ (اختیاری)</label>
      <textarea id="lcmOrderNoteInput" maxlength="200" rows="2" placeholder="مثلاً: بدون شکر، تند نباشه، ..." style="width:100%; padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.05); color:#fff; resize:vertical; font-family:inherit; font-size:0.85rem;"></textarea>
    </div>

    <div style="margin-top:26px; display:flex; gap:10px;">
      <button onclick="closePaymentModal()" 
              style="flex:1; padding:14px; border-radius:14px; border:1px solid rgba(255,255,255,0.2); background:transparent; color:white; font-weight:700;">
        انصراف
      </button>
      <button onclick="confirmAndPlaceOrder()" 
              style="flex:2; padding:14px; border-radius:14px; border:none; background:#e5383b; color:white; font-weight:700; box-shadow: 0 6px 18px rgba(229,56,59,0.4);">
        تایید و ثبت سفارش
      </button>
    </div>

  </div>
</div>

<!-- 📱 ساختار اصلاح شده پنل کاربری بر پایه iOS Card و جمع‌و‌جور برای جلوگیری از کشیدگی -->
<div id="lcm-user-account-drawer" class="lcm-drawer">
    <div class="drawer-content">
        <div class="drawer-header">
            <h3 style="display:flex; align-items:center; gap:8px;"><i data-lucide="user-circle" style="width:20px; height:20px;"></i> حساب کاربری لایو منو</h3>
            <span onclick="lcmToggleAccountDrawer(false)" style="font-size: 2rem; cursor:pointer;">&times;</span>
        </div>

        <!-- تب‌های داخل پنل کاربری: حساب من / امتیاز و نشان‌ها (برای جلوگیری از شلوغی) -->
        <div class="lcm-drawer-tabs" style="display:flex; gap:8px; margin-bottom:16px; background: var(--ios-glass); padding:5px; border-radius:14px;">
            <button onclick="lcmSwitchDrawerTab('account')" id="lcm-drawer-tab-btn-account" class="lcm-drawer-tab-btn active" style="flex:1; padding:10px; border-radius:10px; border:none; background:var(--accent-color); color:#fff; font-weight:800; font-size:0.8rem; cursor:pointer;"><i data-lucide="home" style="width:14px; height:14px; vertical-align:-2px;"></i> حساب من</button>
            <button onclick="lcmSwitchDrawerTab('rewards')" id="lcm-drawer-tab-btn-rewards" class="lcm-drawer-tab-btn" style="flex:1; padding:10px; border-radius:10px; border:none; background:transparent; color:var(--text-color); font-weight:800; font-size:0.8rem; cursor:pointer;"><i data-lucide="trophy" style="width:14px; height:14px; vertical-align:-2px;"></i> امتیاز و نشان‌ها</button>
        </div>

        <div id="lcm-drawer-tab-account">

        <div class="lcm-personal-settings-card" style="background: var(--ios-glass); border: 1px solid var(--border-color); border-radius: 20px; padding: 16px; margin-bottom: 15px;">
            <h4 style="margin:0 0 12px; font-size: 0.9rem; color: var(--text-color); display:flex; align-items:center; gap:6px;"><i data-lucide="user-circle" style="width:16px; height:16px; vertical-align:-3px;"></i> اطلاعات کاربری شما</h4>

            <span id="panel-user-name" style="display:none;"></span>
            <div style="margin-bottom: 12px;">
                <p id="panel-user-phone" style="font-size: 0.75rem; color: #888; margin: 0;">...</p>
            </div>

            <!-- روزشمار تولد اختصاصی مشتری کافه -->
            <div class="birthday-widget" style="margin-bottom: 12px;">
                <span class="birthday-icon"><i data-lucide="cake" style="width:20px; height:20px;"></i></span>
                <div class="birthday-text-area" id="lcmBirthdayCountdown">در حال محاسبه روزشمار تولد شما...</div>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <input type="text" id="panel-edit-name" placeholder="نام مشتری" style="padding:10px; border-radius:10px; border:1px solid var(--border-color); background: rgba(255,255,255,0.03); color: var(--text-color); font-size: 0.75rem;">
                    <input type="text" id="panel-edit-city" placeholder="شهر" style="padding:10px; border-radius:10px; border:1px solid var(--border-color); background: rgba(255,255,255,0.03); color: var(--text-color); font-size: 0.75rem;">
                </div>
                <label style="font-size:0.7rem; color:#888;">📍 آدرس ارسال سفارش</label>
                <textarea id="panel-edit-address" placeholder="آدرس دقیق برای ارسال سفارش‌های بیرون‌بر" rows="2" style="padding:10px; border-radius:10px; border:1px solid var(--border-color); background: rgba(255,255,255,0.03); color: var(--text-color); resize:vertical; font-family:inherit; font-size: 0.75rem;"></textarea>
                <button id="panel-save-address-btn" onclick="lcmSaveProfileInfo()" style="padding:10px; border-radius:10px; border:none; background: var(--accent-color); color:#fff; font-weight:bold; cursor:pointer; font-size: 0.75rem;">💾 ذخیره مشخصات</button>
                <p id="panel-save-status" style="font-size:0.75rem; color:#4caf50; display:none; text-align:center;">✅ اطلاعات با موفقیت به‌روزرسانی شد.</p>
            </div>
        </div>

        <!-- نوار تجربه خرید (گیمیفیکیشن)، متصل به همان سیستم گروه‌های تخفیف/سطح وفاداری واقعی -->
        <div class="xp-card" id="lcmXpCard" style="background: var(--ios-glass); padding: 15px; border-radius: 20px; border: 1px solid var(--border-color); margin-bottom: 15px; display: none;">
            <div style="display: flex; justify-content: space-between; font-size: 0.72rem; margin-bottom: 6px;">
                <span id="lcmXpCurrentLevel" style="font-weight: 900; color: var(--accent-color);">در حال محاسبه...</span>
                <span id="lcmXpNextLevel" style="font-weight: 900; color: #ffb703;"></span>
            </div>
            <div style="background: rgba(255,255,255,0.08); width: 100%; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 6px; position: relative;">
                <div id="lcmXpProgressFill" style="background: linear-gradient(90deg, var(--accent-color) 0%, #ffb703 100%); width: 0%; height: 100%; transition: width 1.2s cubic-bezier(0.25, 1, 0.5, 1); border-radius: 5px;"></div>
            </div>
            <p id="lcmXpDesc" style="font-size: 0.65rem; color: #888; text-align: center; margin: 0; font-weight: bold;">در حال بارگذاری سطح وفاداری...</p>
        </div>

        <!-- این بخش کیف پول است که از قبل در کدهای شما وجود دارد -->
        <div class="wallet-box">
            <p style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 5px;">میزان اعتبار واقعی کیف پول شما</p>
            <h2 id="panel-wallet-balance" style="font-size: 1.5rem; font-weight: 900;">۰ تومان</h2>
        </div>

        <!-- نوتیفیکیشن تخفیف گروه مشتری، فقط وقتی گروه او تخفیف فعال داشته باشد نمایش داده می‌شود -->
        <div id="lcm-panel-discount-notice" style="display:none; background: rgba(67, 233, 123, 0.12); border: 1px solid #43e97b; border-radius: 16px; padding: 12px; margin-bottom: 15px; text-align:center;">
            <span style="font-size: 0.85rem; font-weight: 900; color: #1c9c76;" id="lcm-panel-discount-text"></span>
        </div>

        <!-- تاریخچه‌ی تراکنش‌های کیف پول -->
        <div class="wallet-ledger-box" style="margin-top: 10px; margin-bottom: 15px; background: var(--ios-glass); border: 1px solid var(--border-color); border-radius: 16px; padding: 12px;">
            <h4 style="font-size: 0.8rem; margin: 0 0 8px; color: var(--text-color);"><i data-lucide="bar-chart-3" style="width:14px; height:14px; vertical-align:-2px;"></i> تاریخچه‌ی تراکنش‌های کیف پول</h4>
            <div id="panel-wallet-ledger"><p style="text-align:center; color:#888; font-size:0.75rem;">در حال بارگذاری...</p></div>
        </div>

        <!-- کد معرفی برای دعوت از دوستان -->
        <div class="referral-box" style="margin-bottom: 15px; background: linear-gradient(135deg, #ffb703 0%, #fb8500 100%); border-radius: 16px; padding: 14px; text-align:center; display:none;" id="lcm-referral-box">
            <p style="font-size: 0.75rem; color:#000; margin:0 0 6px; font-weight:700;"><i data-lucide="gift" style="width:14px; height:14px; vertical-align:-2px;"></i> دوستانتان را دعوت کنید و هدیه بگیرید</p>
            <div style="display:flex; align-items:center; justify-content:center; gap:8px;">
                <span id="panel-referral-code" style="background:#fff; padding:6px 16px; border-radius:10px; font-weight:900; letter-spacing:1px; color:#000;">----</span>
                <button onclick="lcmCopyReferralCode()" style="border:none; background:#000; color:#fff; padding:6px 12px; border-radius:10px; font-weight:700; cursor:pointer; font-size:0.75rem;">📋 کپی</button>
            </div>
        </div>

        <button class="btn-charge" onclick="lcmChargeWalletOnline()">💳 شارژ آنلاین و سریع</button>

        <div class="order-history" style="margin-top: 15px;">
            <h4 style="font-size: 0.85rem; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;"><i data-lucide="scroll-text" style="width:14px; height:14px; vertical-align:-2px;"></i> تاریخچه آخرین سفارشات شما</h4>
            <div id="panel-orders-list" style="max-height: 150px; overflow-y: auto;"></div>
        </div>

        </div><!-- پایان تب حساب من -->

        <div id="lcm-drawer-tab-rewards" style="display:none;">

            <!-- چالش هفتگی -->
            <div id="lcm-challenge-box" style="display:none; background: linear-gradient(135deg, rgba(46,196,182,0.15), rgba(19,154,140,0.1)); border: 1px solid var(--accent-color); border-radius: 18px; padding: 16px; margin-bottom: 15px;">
                <h4 style="margin:0 0 8px; font-size:0.85rem; color: var(--accent-color);"><i data-lucide="target" style="width:14px; height:14px; vertical-align:-2px;"></i> چالش این هفته</h4>
                <p id="lcm-challenge-label" style="font-size:0.8rem; margin-bottom:10px;">...</p>
                <div style="background: rgba(255,255,255,0.08); width: 100%; height: 10px; border-radius: 5px; overflow: hidden;">
                    <div id="lcm-challenge-progress-fill" style="background: var(--accent-color); width: 0%; height: 100%; transition: width 0.8s ease;"></div>
                </div>
                <p id="lcm-challenge-status" style="font-size:0.7rem; color:#888; margin-top:6px; text-align:center;">...</p>
            </div>

            <!-- امتیاز وفاداری و تبدیل به کیف پول -->
            <div style="background: linear-gradient(135deg, #ffb703, #fb8500); border-radius: 18px; padding: 16px; margin-bottom: 15px; text-align:center;">
                <p style="font-size:0.75rem; color:#000; font-weight:700; margin:0 0 4px;">💎 امتیاز وفاداری شما</p>
                <h2 id="lcm-points-balance" style="font-size:1.8rem; font-weight:900; color:#000; margin:0 0 10px;">۰</h2>
                <button onclick="lcmRedeemPoints()" style="width:100%; padding:10px; border-radius:12px; border:none; background:#000; color:#fff; font-weight:800; cursor:pointer; font-size:0.8rem;">🔄 تبدیل امتیاز به اعتبار کیف پول</button>
            </div>

            <!-- نشان‌های افتخار -->
            <div style="background: var(--ios-glass); border: 1px solid var(--border-color); border-radius: 18px; padding: 16px; margin-bottom: 15px;">
                <h4 style="margin:0 0 10px; font-size:0.85rem;"><i data-lucide="award" style="width:14px; height:14px; vertical-align:-2px;"></i> نشان‌های افتخار شما</h4>
                <div id="lcm-badges-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                    <p style="grid-column: 1/-1; text-align:center; color:#888; font-size:0.75rem;">در حال بارگذاری...</p>
                </div>
            </div>

            <!-- تحلیل شخصی: قهوه من در طول زمان -->
            <div style="background: var(--ios-glass); border: 1px solid var(--border-color); border-radius: 18px; padding: 16px;">
                <h4 style="margin:0 0 10px; font-size:0.85rem;"><i data-lucide="line-chart" style="width:14px; height:14px; vertical-align:-2px;"></i> من در ۳۰ روز اخیر</h4>
                <div id="lcm-analytics-summary" style="font-size:0.8rem; line-height:2; color: var(--text-color);">در حال محاسبه...</div>
                <div id="lcm-analytics-chart" style="display:flex; align-items:flex-end; gap:6px; height:80px; margin-top:12px;"></div>
                <p style="font-size:0.65rem; color:#888; margin-top:8px; text-align:center;">تعداد سفارش در هر هفته‌ی اخیر</p>
            </div>

        </div><!-- پایان تب امتیاز و نشان‌ها -->

        <!-- کلید خروج ایمن مشتری -->
        <div style="margin-top: 20px; text-align: center;">
           <a href="#" onclick="lcmQuickLogout(); return false;" style="color: #ef4444; font-weight: bold; cursor: pointer; display: block; text-align: center; padding: 10px; margin-top: 15px; border-top: 1px solid #eee;">خروج از حساب کاربری</a>
        </div>
    </div>
</div>

<script type="text/javascript">
    function lcmLogoutUser() {
        localStorage.removeItem('lcm_user_phone');
        window.location.href = "<?php echo wp_logout_url(get_permalink()); ?>";
    }

    let lcmActiveFilters = { minPrice: null, maxPrice: null, maxCalory: null, tags: [] };

    /* بستن سرچ و فیلتر با کلیک بیرون */
    document.addEventListener('click', function(e) {
        const so = document.getElementById('lcmSearchOverlay');
        if (so && so.classList.contains('open') && !so.contains(e.target) && !e.target.closest('.lcm-search-icon-btn')) {
            lcmToggleSearch(false);
        }
        const fo = document.getElementById('lcmFilterOverlay');
        if (fo && fo.classList.contains('open') && !fo.contains(e.target) && !e.target.closest('.lcm-search-icon-btn')) {
            lcmToggleFilter(false);
        }
    });

    function lcmToggleFilter(show) {
        const overlay = document.getElementById('lcmFilterOverlay');
        if (show) {
            document.getElementById('lcmSearchOverlay').classList.remove('open');
            lcmPopulateFilterTagChips();
            overlay.classList.add('open');
        } else {
            overlay.classList.remove('open');
        }
    }

    function lcmPopulateFilterTagChips() {
        const allTags = new Set();
        document.querySelectorAll('.product-grid-card[data-diet-tags]').forEach(card => {
            const tags = card.getAttribute('data-diet-tags');
            if (tags) { tags.split(',').forEach(t => t.trim() && allTags.add(t.trim())); }
        });

        const container = document.getElementById('lcmFilterTagsContainer');
        const row = document.getElementById('lcmFilterDietRow');
        if (allTags.size === 0) { row.style.display = 'none'; return; }

        row.style.display = 'block';
        container.innerHTML = Array.from(allTags).map(tag => `
            <div class="lcm-filter-tag-chip ${lcmActiveFilters.tags.includes(tag) ? 'active' : ''}" onclick="lcmToggleFilterTagChip(this, '${tag}')">${tag}</div>
        `).join('');
    }

    function lcmToggleFilterTagChip(el, tag) {
        el.classList.toggle('active');
        if (lcmActiveFilters.tags.includes(tag)) {
            lcmActiveFilters.tags = lcmActiveFilters.tags.filter(t => t !== tag);
        } else {
            lcmActiveFilters.tags.push(tag);
        }
    }

    function lcmApplyFilters() {
        lcmActiveFilters.minPrice = document.getElementById('lcmFilterMinPrice').value ? parseFloat(document.getElementById('lcmFilterMinPrice').value) : null;
        lcmActiveFilters.maxPrice = document.getElementById('lcmFilterMaxPrice').value ? parseFloat(document.getElementById('lcmFilterMaxPrice').value) : null;
        lcmActiveFilters.maxCalory = document.getElementById('lcmFilterMaxCalory').value ? parseFloat(document.getElementById('lcmFilterMaxCalory').value) : null;

        let visibleCount = 0;
        document.querySelectorAll('.product-grid-card').forEach(card => {
            const price = parseFloat(card.getAttribute('data-base-price')) || 0;
            const calory = parseFloat(card.getAttribute('data-calory')) || 0;
            const cardTags = (card.getAttribute('data-diet-tags') || '').split(',').map(t => t.trim()).filter(Boolean);

            let visible = true;
            if (lcmActiveFilters.minPrice !== null && price < lcmActiveFilters.minPrice) { visible = false; }
            if (lcmActiveFilters.maxPrice !== null && price > lcmActiveFilters.maxPrice) { visible = false; }
            if (lcmActiveFilters.maxCalory !== null && calory > lcmActiveFilters.maxCalory) { visible = false; }
            if (lcmActiveFilters.tags.length > 0 && !lcmActiveFilters.tags.every(t => cardTags.includes(t))) { visible = false; }

            card.style.display = visible ? '' : 'none';
            if (visible) { visibleCount++; }
        });

        const hasActiveFilters = lcmActiveFilters.minPrice !== null || lcmActiveFilters.maxPrice !== null || lcmActiveFilters.maxCalory !== null || lcmActiveFilters.tags.length > 0;
        document.getElementById('lcmFilterBadgeDot').style.display = hasActiveFilters ? 'block' : 'none';

        lcmToggleFilter(false);
        if (visibleCount === 0) { lcmShowToast('هیچ آیتمی با این فیلترها پیدا نشد 😕', true); }
    }

    function lcmResetFilters() {
        lcmActiveFilters = { minPrice: null, maxPrice: null, maxCalory: null, tags: [] };
        document.getElementById('lcmFilterMinPrice').value = '';
        document.getElementById('lcmFilterMaxPrice').value = '';
        document.getElementById('lcmFilterMaxCalory').value = '';
        document.querySelectorAll('.product-grid-card').forEach(card => { card.style.display = ''; });
        document.getElementById('lcmFilterBadgeDot').style.display = 'none';
        lcmPopulateFilterTagChips();
    }

    /* =====================================================
       بستن سرچ و فیلتر با کلیک بیرون از پنل
    ===================================================== */
    document.addEventListener('click', function(e) {
        // سرچ
        const searchOverlay = document.getElementById('lcmSearchOverlay');
        const searchBtn = document.querySelector('.lcm-search-icon-btn[onclick*="Search"]');
        if (searchOverlay && searchOverlay.classList.contains('open')) {
            if (!searchOverlay.contains(e.target) && e.target !== searchBtn && !searchBtn?.contains(e.target)) {
                lcmToggleSearch(false);
            }
        }
        // فیلتر
        const filterOverlay = document.getElementById('lcmFilterOverlay');
        const filterBtn = document.querySelector('.lcm-search-icon-btn[onclick*="Filter"]');
        if (filterOverlay && filterOverlay.classList.contains('open')) {
            if (!filterOverlay.contains(e.target) && e.target !== filterBtn && !filterBtn?.contains(e.target)) {
                lcmToggleFilter(false);
            }
        }
    });

    function lcmToggleSearch(show) {
        const overlay = document.getElementById('lcmSearchOverlay');
        const input = document.getElementById('lcmSearchInput');
        if (show) {
            document.getElementById('lcmFilterOverlay').classList.remove('open');
            overlay.classList.add('open');
            setTimeout(() => input.focus(), 250);
        } else {
            overlay.classList.remove('open');
            input.value = '';
            document.getElementById('lcmSearchResults').innerHTML = '';
        }
    }

    let lcmSearchTimer = null;
    function lcmHandleSearchInput(query) {
        clearTimeout(lcmSearchTimer);
        const resultsEl = document.getElementById('lcmSearchResults');
        if (query.trim().length < 2) { resultsEl.innerHTML = ''; return; }

        lcmSearchTimer = setTimeout(() => {
            let formData = new FormData();
            formData.append('action', 'lcm_search_products');
            formData.append('query', query.trim());

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.data.length) {
                    resultsEl.innerHTML = '<div class="lcm-search-empty">😕 محصولی پیدا نشد</div>';
                    return;
                }
                resultsEl.innerHTML = res.data.map(item => `
                    <div class="lcm-search-result-item" onclick="lcmGoToSearchResult(${item.id}, ${item.cat_id})">
                        <img src="${item.image || ''}" alt="" loading="lazy" onerror="this.style.display='none'">
                        <div class="info">
                            <div class="title">${item.title}</div>
                            <div class="snippet">${item.snippet}</div>
                        </div>
                        <div class="price">${item.price} ت</div>
                    </div>
                `).join('');
            })
            .catch(() => {
                resultsEl.innerHTML = '<div class="lcm-search-empty">خطا در جستجو، دوباره امتحان کنید.</div>';
            });
        }, 350);
    }

    function lcmGoToSearchResult(productId, catId) {
        lcmToggleSearch(false);
        const existingCard = document.getElementById('card-' + productId);
        if (existingCard) {
            existingCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            existingCard.classList.add('lcm-search-highlight');
            setTimeout(() => existingCard.classList.remove('lcm-search-highlight'), 1700);
            return;
        }

        // آیتم در دسته‌بندی دیگری است؛ اول به همان دسته سوییچ کن، بعد اسکرول کن
        const targetTab = document.querySelector('.cat-tab[data-cat-id="' + catId + '"]');
        lcmSwitchCategory(targetTab ? { preventDefault(){}, currentTarget: targetTab } : null, catId, true);

        setTimeout(() => {
            const card = document.getElementById('card-' + productId);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('lcm-search-highlight');
                setTimeout(() => card.classList.remove('lcm-search-highlight'), 1700);
            }
        }, 700);
    }

    function lcmCallWaiter() {
        const btn = document.getElementById('lcmWaiterCallBtn');
        if (!btn || btn.disabled) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '⏳ در حال ارسال...';

        let formData = new FormData();
        formData.append('action', 'lcm_call_waiter');
        formData.append('table_id', currentTableId);
        formData.append('lcm_nonce', LCM_NONCE);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                lcmShowToast(res.data.message);
                btn.innerHTML = '✅ گارسون خبر شد';
                setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 15000);
            } else {
                lcmShowToast('خطا: ' + (res.data && res.data.message ? res.data.message : 'ارسال نشد'), true);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        })
        .catch(() => {
            lcmShowToast('خطا در ارتباط با سرور.', true);
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    function lcmSwitchDrawerTab(tab) {
        const accountTab = document.getElementById('lcm-drawer-tab-account');
        const rewardsTab = document.getElementById('lcm-drawer-tab-rewards');
        const accountBtn = document.getElementById('lcm-drawer-tab-btn-account');
        const rewardsBtn = document.getElementById('lcm-drawer-tab-btn-rewards');

        if (tab === 'rewards') {
            accountTab.style.display = 'none';
            rewardsTab.style.display = 'block';
            rewardsBtn.style.background = 'var(--accent-color)'; rewardsBtn.style.color = '#fff';
            accountBtn.style.background = 'transparent'; accountBtn.style.color = 'var(--text-color)';
        } else {
            accountTab.style.display = 'block';
            rewardsTab.style.display = 'none';
            accountBtn.style.background = 'var(--accent-color)'; accountBtn.style.color = '#fff';
            rewardsBtn.style.background = 'transparent'; rewardsBtn.style.color = 'var(--text-color)';
        }
    }

    function lcmRedeemPoints() {
        const phone = localStorage.getItem('lcm_user_phone');
        if (!phone) return;

        let formData = new FormData();
        formData.append('action', 'lcm_redeem_points_to_wallet');
        formData.append('phone', phone);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                lcmShowToast(res.data.message);
                if (document.getElementById('lcm-points-balance')) document.getElementById('lcm-points-balance').innerText = Number(res.data.new_points).toLocaleString('fa-IR');
                if (document.getElementById('panel-wallet-balance')) document.getElementById('panel-wallet-balance').innerText = Number(res.data.new_wallet).toLocaleString('fa-IR') + ' تومان';
            } else {
                lcmShowToast('خطا: ' + (res.data && res.data.message ? res.data.message : 'ثبت نشد'), true);
            }
        })
        .catch(() => lcmShowToast('خطا در ارتباط با سرور.', true));
    }

    function lcmCopyReferralCode() {
        const code = document.getElementById('panel-referral-code').innerText;

        function fallbackCopy() {
            try {
                const tempInput = document.createElement('textarea');
                tempInput.value = code;
                tempInput.style.position = 'fixed';
                tempInput.style.opacity = '0';
                document.body.appendChild(tempInput);
                tempInput.focus();
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                lcmShowToast('کد معرف کپی شد ✅');
            } catch (e) {
                lcmShowToast('کپی نشد؛ کد شما: ' + code, true);
            }
        }

        // navigator.clipboard فقط روی HTTPS (یا localhost) در دسترس است؛ قبلاً روی سایت‌های
        // بدون HTTPS این تابع اصلاً وجود نداشت و کلیک روی دکمه هیچ اتفاقی نمی‌افتاد.
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(() => {
                lcmShowToast('کد معرف کپی شد ✅');
            }).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
    }

    function lcmReorderFromHistory(btnEl) {
        const idsStr = btnEl.getAttribute('data-product-ids');
        if (!idsStr) return;
        const ids = idsStr.split(',').map(id => parseInt(id)).filter(id => id > 0);
        if (!ids.length) return;

        const missingIds = [];
        ids.forEach(pId => {
            const card = document.getElementById('card-' + pId);
            if (card) {
                // اگر همان محصول در همین صفحه‌ی منو باشد، از قیمت/تخفیف واقعی و فعلی‌اش استفاده کن
                lcmGridAddToCart(String(pId));
            } else {
                missingIds.push(pId);
            }
        });

        if (missingIds.length > 0) {
            // آیتم‌هایی که توی دسته‌بندی فعلی صفحه نیستند؛ قیمت/عنوان فعلی‌شان را جداگانه می‌گیریم
            let formData = new FormData();
            formData.append('action', 'lcm_get_products_basic_info');
            formData.append('ids', missingIds.join(','));

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success && Array.isArray(res.data)) {
                    res.data.forEach(p => {
                        $globalCartQueue.push({
                            combo_key: `${p.id}-reorder-${Date.now()}`,
                            main_id: p.id,
                            var_id: 0,
                            title: p.title,
                            cat_slug: p.cat_slug,
                            size_label: 'تک سایز',
                            addons: [],
                            addon_titles: [],
                            price: p.price
                        });
                    });
                    localStorage.setItem('lcm_live_cart_hub', JSON.stringify($globalCartQueue));
                    lcmPushTableCartToServer();
                    lcmUpdateHubDisplay();
                }
                lcmToggleAccountDrawer(false);
                lcmToggleCartDrawer(true);
            });
        }

        lcmShowToast('آیتم‌های موجود به سبد اضافه شدند 🛒');
        if (missingIds.length === 0) {
            lcmToggleAccountDrawer(false);
            lcmToggleCartDrawer(true);
        }
    }

    function lcmRateOrder(starEl, stars) {
        const row = starEl.closest('.lcm-rate-row');
        const orderId = row.getAttribute('data-order-id');
        const phone = localStorage.getItem('lcm_user_phone');
        const stars_els = row.querySelectorAll('span');

        // پررنگ کردن آنی ستاره‌ها برای بازخورد بصری فوری
        stars_els.forEach((star, idx) => {
            star.style.opacity = (idx < stars) ? '1' : '0.35';
        });

        let formData = new FormData();
        formData.append('action', 'lcm_rate_order');
        formData.append('order_id', orderId);
        formData.append('stars', stars);
        formData.append('phone', phone);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                // امتیاز همچنان تا ۲۴ ساعت قابل تغییر می‌ماند؛ فقط یک تاییدیه‌ی کوتاه نشان بده
                lcmShowToast(res.data.message || 'ثبت شد، ممنون! 🙏');
            } else {
                lcmShowToast('خطا: ' + (res.data && res.data.message ? res.data.message : 'ثبت نشد'), true);
            }
        })
        .catch(() => lcmShowToast('خطا در ارتباط با سرور.', true));
    }

    function lcmToggleItemLike(btnEl) {
        const orderId = btnEl.getAttribute('data-order-id');
        const productId = btnEl.getAttribute('data-product-id');
        const phone = localStorage.getItem('lcm_user_phone');
        const originalText = btnEl.innerText;

        btnEl.disabled = true;

        let formData = new FormData();
        formData.append('action', 'lcm_toggle_item_like');
        formData.append('order_id', orderId);
        formData.append('product_id', productId);
        formData.append('phone', phone);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            btnEl.disabled = false;
            if (res.success) {
                const label = originalText.replace(/^(❤️|🤍)\s*/, '');
                if (res.data.liked) {
                    btnEl.innerText = '❤️ ' + label;
                    btnEl.style.borderColor = '#e5383b';
                    btnEl.style.background = 'rgba(229,56,59,0.12)';
                    btnEl.style.color = '#e5383b';
                } else {
                    btnEl.innerText = '🤍 ' + label;
                    btnEl.style.borderColor = 'var(--border-color)';
                    btnEl.style.background = 'transparent';
                    btnEl.style.color = 'var(--text-color)';
                }
            } else {
                lcmShowToast('خطا: ' + (res.data && res.data.message ? res.data.message : 'ثبت نشد'), true);
            }
        })
        .catch(() => {
            btnEl.disabled = false;
            lcmShowToast('خطا در ارتباط با سرور.', true);
        });
    }

    // ⚠️ این دکمه فعلاً به هیچ درگاه پرداخت واقعی وصل نیست. اتصال به یک درگاه واقعی
    // (زرین‌پال/آیدی‌پی و ...) نیازمند یک قدم جدا و هماهنگی با شماره پذیرندگی شماست.
    function lcmChargeWalletOnline() {
        lcmShowToast('این بخش هنوز به درگاه پرداخت وصل نشده — به‌زودی فعال می‌شود.', true);
    }

    function lcmSaveProfileInfo() {
        const btn = document.getElementById('panel-save-address-btn');
        const statusEl = document.getElementById('panel-save-status');
        btn.innerText = '⏳ در حال ذخیره...'; btn.disabled = true;

        let formData = new FormData();
        formData.append('action', 'lcm_update_user_profile');
        formData.append('user_phone', localStorage.getItem('lcm_user_phone'));
        formData.append('name', document.getElementById('panel-edit-name').value);
        formData.append('city', document.getElementById('panel-edit-city').value);
        formData.append('address', document.getElementById('panel-edit-address').value);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            btn.innerText = '💾 ذخیره مشخصات'; btn.disabled = false;
            if (res.success) {
                document.getElementById('panel-user-name').innerText = res.data.name;
                statusEl.style.display = 'block';
                setTimeout(() => { statusEl.style.display = 'none'; }, 2500);
            } else {
                lcmShowToast('خطا: ' + (res.data && res.data.message ? res.data.message : 'ذخیره‌سازی ناموفق بود.'), true);
            }
        })
        .catch(() => {
            btn.innerText = '💾 ذخیره مشخصات'; btn.disabled = false;
            lcmShowToast('خطا در ارتباط با سرور.', true);
        });
    }
</script>
<script type="text/javascript">
// تعریف توابع به صورت سراسری (Global) روی شیء window برای دسترسی در رویدادهای onclick

// تابع باز و بسته کردن پنل کاربری
window.lcmToggleAccountDrawer = function(open) {
    const userPhoneLocal = localStorage.getItem('lcm_user_phone');

    if (!userPhoneLocal) {
        if (open) {
            const glassPopup = document.getElementById('lcm-glass-popup');
            if (glassPopup) {
                if (typeof jQuery !== "undefined") {
                    jQuery('#lcm-glass-popup').fadeIn(300);
                } else {
                    glassPopup.style.display = 'flex';
                }
            } else {
                if(typeof lcmShowToast === 'function') lcmShowToast("لطفاً وارد حساب کاربری شوید.", true);
            }
        }
        return;
    }

    const drawer = document.getElementById('lcm-user-account-drawer');
    if (!drawer) return;
    
    drawer.classList.toggle('open', open);
    
    if(open) {
        window.setPanelLoadingState(); // فراخوانی تابع Loading

        let formData = new FormData();
        formData.append('action', 'lcm_get_user_panel_data');
        formData.append('phone', userPhoneLocal); 
        
        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if(!res.ok) throw new Error("Network response was not OK");
            return res.json();
        })
        .then(res => {
            if (res.success && res.data) {
                if(document.getElementById('panel-user-name')) document.getElementById('panel-user-name').innerText = res.data.name;
                if(document.getElementById('panel-user-phone')) document.getElementById('panel-user-phone').innerText = res.data.phone;
                if(document.getElementById('panel-wallet-balance')) document.getElementById('panel-wallet-balance').innerText = res.data.wallet + ' تومان';
                if(document.getElementById('panel-orders-list')) document.getElementById('panel-orders-list').innerHTML = res.data.orders;
                if(document.getElementById('panel-wallet-ledger') && res.data.wallet_ledger) document.getElementById('panel-wallet-ledger').innerHTML = res.data.wallet_ledger;

                if (res.data.referral_code) {
                    document.getElementById('panel-referral-code').innerText = res.data.referral_code;
                    document.getElementById('lcm-referral-box').style.display = 'block';
                }

                // امتیاز وفاداری
                if (document.getElementById('lcm-points-balance')) {
                    document.getElementById('lcm-points-balance').innerText = Number(res.data.points || 0).toLocaleString('fa-IR');
                }

                // نشان‌های افتخار
                const badgesGrid = document.getElementById('lcm-badges-grid');
                if (badgesGrid) {
                    if (res.data.badges && res.data.badges.length > 0) {
                        badgesGrid.innerHTML = res.data.badges.map(b => `
                            <div style="text-align:center; background:rgba(255,255,255,0.04); border-radius:12px; padding:10px 4px;" title="${b.desc}">
                                <div style="font-size:1.8rem;">${b.icon}</div>
                                <div style="font-size:0.65rem; margin-top:4px; font-weight:700;">${b.label}</div>
                            </div>
                        `).join('');
                    } else {
                        badgesGrid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; color:#888; font-size:0.75rem;">هنوز نشانی کسب نکرده‌اید؛ با خرید بیشتر، نشان‌های جذابی منتظرتان است! 🎁</p>';
                    }
                }

                // چالش هفتگی
                const challengeBox = document.getElementById('lcm-challenge-box');
                if (challengeBox && res.data.challenge) {
                    challengeBox.style.display = 'block';
                    document.getElementById('lcm-challenge-label').innerText = res.data.challenge.label;
                    const pct = Math.min(100, (res.data.challenge.progress / res.data.challenge.target) * 100);
                    document.getElementById('lcm-challenge-progress-fill').style.width = pct + '%';
                    if (res.data.challenge.progress >= res.data.challenge.target) {
                        document.getElementById('lcm-challenge-status').innerText = '✅ تبریک! جایزه‌ی این هفته را دریافت کردید 🎉';
                    } else {
                        document.getElementById('lcm-challenge-status').innerText = `${res.data.challenge.progress} از ${res.data.challenge.target} — کمی دیگر مانده!`;
                    }
                }

                // تحلیل شخصی ۳۰ روز اخیر
                if (res.data.analytics) {
                    const a = res.data.analytics;
                    const summaryEl = document.getElementById('lcm-analytics-summary');
                    if (summaryEl) {
                        summaryEl.innerHTML = `
                            🧾 ${a.order_count} سفارش ثبت کردید<br>
                            💰 ${Number(a.total_spent).toLocaleString('fa-IR')} تومان خرید کردید<br>
                            ${a.caffeine_mg > 0 ? '☕ حدود ' + Number(a.caffeine_mg).toLocaleString('fa-IR') + ' میلی‌گرم کافئین دریافت کردید (تقریبی)' : ''}
                        `;
                    }
                    const chartEl = document.getElementById('lcm-analytics-chart');
                    if (chartEl && a.weekly_counts) {
                        const maxVal = Math.max(1, ...a.weekly_counts);
                        chartEl.innerHTML = a.weekly_counts.map(v => `
                            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%;">
                                <div style="width:100%; background: var(--accent-color); border-radius:4px 4px 0 0; height:${(v/maxVal)*100}%; min-height:${v>0?'4px':'0'};"></div>
                                <span style="font-size:0.6rem; color:#888; margin-top:4px;">${v}</span>
                            </div>
                        `).join('');
                    }
                }

                const discountNotice = document.getElementById('lcm-panel-discount-notice');
                const discountText = document.getElementById('lcm-panel-discount-text');
                if (discountNotice && discountText) {
                    if (res.data.discount_group_percent > 0) {
                        discountText.innerText = '🎁 ' + res.data.discount_group_label + ' — ' + res.data.discount_group_percent + '٪ تخفیف برای شما فعال است';
                        discountNotice.style.display = 'block';
                    } else {
                        discountNotice.style.display = 'none';
                    }
                }

                if(document.getElementById('panel-edit-name')) document.getElementById('panel-edit-name').value = res.data.name || '';
                if(document.getElementById('panel-edit-city')) document.getElementById('panel-edit-city').value = res.data.saved_city || '';
                if(document.getElementById('panel-edit-address')) document.getElementById('panel-edit-address').value = res.data.saved_address || '';

                if(document.getElementById('lcmBirthdayCountdown')) {
                    document.getElementById('lcmBirthdayCountdown').innerHTML = res.data.birthday_countdown || "🎂 تاریخ تولدی ثبت نشده است.";
                }

                if (res.data.total_spent !== undefined && document.getElementById('lcmXpCard')) {
                    const xpCard = document.getElementById('lcmXpCard');

                    if (res.data.current_tier_label) {
                        xpCard.style.display = 'block';
                        setTimeout(() => {
                            if(document.getElementById('lcmXpProgressFill')) document.getElementById('lcmXpProgressFill').style.width = res.data.progress_percent + '%';
                        }, 100);

                        if(document.getElementById('lcmXpCurrentLevel')) document.getElementById('lcmXpCurrentLevel').innerText = res.data.current_tier_label;
                        if(document.getElementById('lcmXpNextLevel')) document.getElementById('lcmXpNextLevel').innerText = res.data.next_tier_label || '';

                        if(document.getElementById('lcmXpDesc')) {
                            if (res.data.remaining_to_next > 0 && res.data.next_tier_label) {
                                document.getElementById('lcmXpDesc').innerHTML = `فقط <strong>${Math.round(res.data.remaining_to_next).toLocaleString('fa-IR')} تومان</strong> خرید دیگر تا سطح «${res.data.next_tier_label}»!`;
                            } else {
                                document.getElementById('lcmXpDesc').innerHTML = "✨ تبریک! شما در بالاترین سطح تعریف‌شده هستید ✨";
                            }
                        }
                    } else {
                        // هنوز هیچ سطح وفاداری‌ای در تنظیمات ادمین تعریف نشده
                        xpCard.style.display = 'none';
                    }
                }
            } else {
                console.error("Server Error:", res);
                if(typeof lcmShowToast === 'function') lcmShowToast(res.data && res.data.message ? res.data.message : "لطفا مجدداً وارد حساب کاربری خود شوید.", true);
                if(document.getElementById('panel-user-name')) document.getElementById('panel-user-name').innerText = "خطا در اتصال";
            }
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            if(typeof lcmShowToast === 'function') lcmShowToast("خطا در برقراری ارتباط با سرور. لطفاً اینترنت خود را بررسی کنید.", true);
            if(document.getElementById('panel-user-name')) document.getElementById('panel-user-name').innerText = "آفلاین";
        });
    }
};

// تابع جدید برای خروج سریع (حل مشکل is not defined)
window.lcmQuickLogout = function() {
    // ۱. پاک کردن حافظه مرورگر
    localStorage.removeItem('lcm_user_phone');
    
    // ۲. ارسال درخواست خروج در پس‌زمینه به سرور
    let formData = new FormData();
    formData.append('action', 'lcm_quick_logout');
    
    // اگر تابع توست در قالب شما وجود دارد، پیام میدهد
    if(typeof lcmShowToast === 'function') {
        lcmShowToast("در حال خروج...", false);
    }

    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
        method: 'POST',
        body: formData
    }).then(() => {
        // ۳. رفرش کردن همان صفحه برای اعمال تغییرات و بسته شدن خودکار پنل
        window.location.reload();
    }).catch(err => {
        console.error("Logout Error:", err);
        // در صورت خطا هم رفرش میکنیم تا کاربر از حالت لاگین خارج شود
        window.location.reload();
    });
};

// تابعی برای ایجاد حالت "در حال بارگذاری" استخوانی (Skeleton Loading)
window.setPanelLoadingState = function() {
    if(document.getElementById('panel-user-name')) document.getElementById('panel-user-name').innerHTML = '<span style="opacity:0.4;">در حال دریافت...</span>';
    if(document.getElementById('panel-wallet-balance')) document.getElementById('panel-wallet-balance').innerHTML = '<span style="opacity:0.4;">در حال لود...</span>';
    if(document.getElementById('panel-orders-list')) document.getElementById('panel-orders-list').innerHTML = '<p style="text-align:center; color:#888;">کمی صبر کنید...</p>';
};

</script>
<script>
// ==================== توابع پرداخت (نسخه نهایی و امن) ====================

let selectedPaymentMethod = 'online';

function selectPaymentMethod(element, method) {
    // حذف استایل فعال از همه کارت‌ها
    document.querySelectorAll('#lcm-payment-modal .payment-method').forEach(el => {
        el.style.borderColor = '#64748b';
        el.style.background = 'rgba(255,255,255,0.03)';
    });

    // فعال کردن کارت انتخاب شده
    if (method === 'online') {
        element.style.borderColor = '#e5383b';
        element.style.background = 'rgba(229,56,59,0.12)';
    } 
    else if (method === 'wallet') {
        const phone = localStorage.getItem('lcm_user_phone');
        if (!phone) {
            alert('برای استفاده از کیف پول باشگاه باید ابتدا وارد حساب کاربری شوید.');
            closePaymentModal();
            return;
        }
        element.style.borderColor = '#f59e0b';
        element.style.background = 'rgba(245,158,11,0.12)';
        fetchWalletBalanceInModal();
    } 
    else if (method === 'salon') {
        element.style.borderColor = '#0ea5e9';
        element.style.background = 'rgba(14,165,233,0.12)';
    }

    selectedPaymentMethod = method;
}

function fetchWalletBalanceInModal() {
    const display = document.getElementById('modal-wallet-balance');
    const phone = localStorage.getItem('lcm_user_phone');

    if (!phone || !display) return;

    display.innerHTML = 'در حال دریافت...';

    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'lcm_get_live_wallet',
            phone: phone
        })
    })
    .then(res => res.json())
    .then(data => {
        const remainingBox = document.getElementById('lcm-wallet-remaining-box');
        if (data.success && data.data && data.data.wallet != null) {
            const walletBalance = Number(data.data.wallet);
            const bal = walletBalance.toLocaleString('fa-IR');
            display.innerHTML = `موجودی فعلی: <strong style="color:#10b981;">${bal}</strong> تومان`;

            const cartTotal = (typeof $globalCartQueue !== 'undefined') ? $globalCartQueue.reduce((s, i) => s + i.price, 0) : 0;

            if (remainingBox) {
                if (walletBalance < cartTotal) {
                    const remainingAmount = (cartTotal - walletBalance).toLocaleString('fa-IR');
                    document.getElementById('lcm-wallet-remaining-text').innerText = `موجودی کیف پول کافی نیست؛ ${remainingAmount} تومان باقی می‌ماند. نحوه‌ی تسویه‌ی مابقی را انتخاب کنید:`;
                    remainingBox.style.display = 'block';
                    selectRemainingMethod('salon', remainingBox.querySelector('[data-remaining="salon"]'));
                } else {
                    remainingBox.style.display = 'none';
                    selectedRemainingMethod = 'salon';
                }
            }
        } else {
            display.innerHTML = 'موجودی یافت نشد';
            display.style.color = '#e5383b';
            if (remainingBox) remainingBox.style.display = 'none';
        }
    })
    .catch(() => {
        display.innerHTML = 'خطا در دریافت موجودی';
        display.style.color = '#e5383b';
    });
}

let selectedRemainingMethod = 'salon';
function selectRemainingMethod(method, btnEl) {
    selectedRemainingMethod = method;
    document.querySelectorAll('#lcm-wallet-remaining-box button').forEach(b => {
        b.style.borderColor = '#64748b';
        b.style.background = 'rgba(255,255,255,0.03)';
    });
    if (btnEl) {
        btnEl.style.borderColor = method === 'online' ? '#e5383b' : '#0ea5e9';
        btnEl.style.background = method === 'online' ? 'rgba(229,56,59,0.15)' : 'rgba(14,165,233,0.15)';
    }
}

function showPaymentModal() {
    const modal = document.getElementById('lcm-payment-modal');
    if (!modal) return;

    modal.style.display = 'flex';
    selectedPaymentMethod = 'online';

    const preOrderBox = document.getElementById('lcmPreOrderTimeBox');
    if (preOrderBox) { preOrderBox.style.display = LCM_CAFE_IS_OPEN ? 'none' : 'block'; }

    const cards = document.querySelectorAll('#lcm-payment-modal .payment-method');
    cards.forEach(el => {
        el.style.borderColor = '#64748b';
        el.style.background = 'rgba(255,255,255,0.03)';
    });
    if (cards[0]) {
        cards[0].style.borderColor = '#e5383b';
        cards[0].style.background = 'rgba(229,56,59,0.12)';
    }
}

function closePaymentModal() {
    const modal = document.getElementById('lcm-payment-modal');
    if (modal) modal.style.display = 'none';
}

function confirmAndPlaceOrder() {
    if (!LCM_CAFE_IS_OPEN) {
        const timeInput = document.getElementById('lcmPreOrderTimeInput');
        if (!timeInput || !timeInput.value) {
            lcmShowToast('لطفاً ساعت تحویل سفارش رو مشخص کنید 🙏', true);
            return;
        }
    }
    closePaymentModal();
    placeOrderWithPayment(selectedPaymentMethod);
}

function placeOrderWithPayment(paymentMethod) {
    if (typeof $globalCartQueue === 'undefined' || $globalCartQueue.length === 0) return;

    const btn = document.querySelector('.btn-checkout-hub');
    if (btn) {
        btn.innerText = '⏳ در حال ثبت...';
        btn.disabled = true;
    }

    const phone = localStorage.getItem('lcm_user_phone') || '';

    // امنیت: اگر کیف پول انتخاب شده ولی لاگین نیست، جلوگیری کن
    if (paymentMethod === 'wallet' && !phone) {
        alert('برای پرداخت با کیف پول باید وارد حساب کاربری شوید.');
        if (btn) {
            btn.innerText = '🛒 ثبت سفارش';
            btn.disabled = false;
        }
        return;
    }

    let mainItem = $globalCartQueue[0];
    let allAddons = [...mainItem.addons];

    for (let i = 1; i < $globalCartQueue.length; i++) {
        let targetId = $globalCartQueue[i].var_id > 0 ? $globalCartQueue[i].var_id : $globalCartQueue[i].main_id;
        allAddons.push(targetId);
        $globalCartQueue[i].addons.forEach(aId => allAddons.push(aId));
    }

    let firstTargetId = mainItem.var_id > 0 ? mainItem.var_id : mainItem.main_id;

    let formData = new FormData();
    formData.append('action', 'lcm_add_custom_product_to_cart');
    formData.append('main_id', firstTargetId);
    formData.append('addons', allAddons.join(','));
    formData.append('order_type', currentOrderType);
    formData.append('table_id', currentTableId);
    formData.append('payment_method', paymentMethod);
    formData.append('user_phone', phone);
    formData.append('remaining_payment_method', selectedRemainingMethod);
    const orderNoteEl = document.getElementById('lcmOrderNoteInput');
    formData.append('order_note', orderNoteEl ? orderNoteEl.value.trim() : '');
    const preOrderTimeEl = document.getElementById('lcmPreOrderTimeInput');
    formData.append('requested_time', (!LCM_CAFE_IS_OPEN && preOrderTimeEl) ? preOrderTimeEl.value : '');
    formData.append('lcm_nonce', LCM_NONCE);

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return res.text().then(txt => { throw new Error('PHP Error: ' + txt.substring(0, 200)); });
        }
        return res.json();
    })
    .then(resData => {
        if (resData.success) {
            lcmToggleCartDrawer(false);
            localStorage.removeItem('lcm_live_cart_hub');

            let resetData = new FormData();
            resetData.append('action', 'lcm_sync_table_cart');
            resetData.append('table_id', currentTableId);
            resetData.append('cart_data', '[]');
            resetData.append('lcm_nonce', LCM_NONCE);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: resetData });

            if (resData.data && resData.data.redirect_url) {
                window.location.href = resData.data.redirect_url;
            } else {
                window.location.reload();
            }
        } else {
            lcmShowToast(resData.data?.message || 'خطا در ثبت سفارش', true);
            if (btn) {
                btn.innerText = '🛒 ثبت سفارش';
                btn.disabled = false;
            }
        }
    })
    .catch((err) => {
        console.error('Order error:', err);
        lcmShowToast('خطا در ارتباط با سرور — جزئیات در کنسول مرورگر (F12)', true);
        if (btn) {
            btn.innerText = '🛒 ثبت سفارش';
            btn.disabled = false;
        }
    });
}

// === override تابع ثبت سفارش ===
window.lcmSubmitFullTable = function() {
    if (typeof $globalCartQueue === 'undefined' || $globalCartQueue.length === 0) {
        if (typeof lcmShowToast === 'function') lcmShowToast('سبد خرید خالی است!', true);
        return;
    }
    // 🔒 ورود اجباری قبل از ثبت سفارش
    const userPhone = localStorage.getItem('lcm_user_phone');
    if (!userPhone) {
        if (typeof lcmShowToast === 'function') lcmShowToast('برای ثبت سفارش ابتدا وارد حساب کاربری شوید 🔐', true);
        // باز کردن پنل عضویت/ورود
        setTimeout(function() {
            const popupBtn = document.querySelector('.lcm-open-popup-btn, [onclick*="lcmOpenPopup"], [onclick*="lcm-popup"]');
            if (popupBtn) { popupBtn.click(); }
            else if (typeof lcmToggleAccountDrawer === 'function') { lcmToggleAccountDrawer(true); }
        }, 800);
        return;
    }
    showPaymentModal();
};
</script>

</body>
</html>