<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * پارشیال رندر گرید محصولات — هم توسط بارگذاری اول صفحه‌ی منو استفاده می‌شود
 * و هم توسط AJAX (هنگام جابه‌جایی بین تب‌های دسته‌بندی بدون رفرش کامل صفحه).
 * ورودی‌های لازم که باید از قبل تعریف شده باشند: $cat_products, $cat_slug_for_js
 */
?>
        <?php
        // ================== تشخیص گروه تخفیف بازدیدکننده (یک‌بار، قبل از حلقه) ==================
        // قبلاً این‌جا یا کوکی‌ای که هرگز ست نمی‌شد را می‌خواند، یا یک user_meta که هیچ‌جا
        // ذخیره نمی‌شد؛ یعنی تخفیف روی رندر اول صفحه عملاً همیشه غیرفعال بود.
        // حالا از روی کوکی شماره موبایل (که بعد از ورود موفق در باشگاه مشتریان ست می‌شود)
        // مستقیماً گروه واقعی مشتری از دیتابیس خوانده می‌شود.
        $lcm_viewer_group_slug = '';
        $lcm_viewer_phone = isset($_COOKIE['lcm_user_phone']) ? sanitize_text_field($_COOKIE['lcm_user_phone']) : '';
        if ( ! empty( $lcm_viewer_phone ) ) {
            global $wpdb;
            $lcm_club_table = $wpdb->prefix . 'lcm_club_members';
            $lcm_viewer_group_slug = $wpdb->get_var( $wpdb->prepare( "SELECT user_group FROM $lcm_club_table WHERE phone = %s", $lcm_viewer_phone ) );
        }
        if ( empty( $lcm_viewer_group_slug ) && isset( $_COOKIE['lcm_user_group'] ) ) {
            $lcm_viewer_group_slug = sanitize_text_field( $_COOKIE['lcm_user_group'] );
        }

        // استفاده‌ی مشترک برای پیشنهاد افزودنیِ پیش‌فرض؛ قبلاً این کوئری برای هر محصولی که
        // افزودنی مشخصی نداشت، از نو (تا ۲۰ محصول) اجرا می‌شد — الان فقط یک‌بار اجرا می‌شود.
        $lcm_fallback_upsell_pool = get_posts(array('post_type' => 'product', 'numberposts' => 20));

        // آیتم شانسی امروز (در صورت فعال بودن و قبل از ساعت قطع)
        $lcm_daily_lucky_item = function_exists('lcm_get_daily_lucky_item') ? lcm_get_daily_lucky_item() : null;

        // محصولات پرفروش — آرایه‌ای از product_id => تعداد سفارش
        // حداقل ۳ سفارش برای نمایش بج (کمتر از ۳ گمراه‌کننده است)
        $lcm_top_products = function_exists('lcm_get_top_products') ? lcm_get_top_products(20) : array();

        // تعداد پسندیدن‌های واقعی هر محصول (فقط وقتی حداقل ۳ نفر پسندیده باشند نمایش داده می‌شود
        // تا یک عدد خیلی کوچک/غیرقابل‌اتکا گمراه‌کننده نباشد)
        $lcm_like_counts = array();
        if ( ! empty( $cat_products ) ) {
            global $wpdb;
            $likes_table = $wpdb->prefix . 'lcm_liked_items';
            if ( $wpdb->get_var("SHOW TABLES LIKE '$likes_table'") === $likes_table ) {
                $ids_in_page = wp_list_pluck( $cat_products, 'ID' );
                $ids_placeholder = implode(',', array_fill(0, count($ids_in_page), '%d'));
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT product_id, COUNT(DISTINCT phone) as cnt FROM $likes_table WHERE product_id IN ($ids_placeholder) GROUP BY product_id",
                    $ids_in_page
                ) );
                foreach ( $rows as $row ) {
                    if ( intval($row->cnt) >= 3 ) {
                        $lcm_like_counts[ intval($row->product_id) ] = intval($row->cnt);
                    }
                }
            }
        }

        if(!empty($cat_products)) {
            foreach($cat_products as $post_item) {
                $p_id = $post_item->ID;
                $p_woo = wc_get_product($p_id);
                if(!$p_woo) continue;

                $p_title = $p_woo->get_name();
                $p_price = $p_woo->get_price();
                $p_desc = $p_woo->get_short_description();
                $p_calory = get_post_meta($p_id, '_lcm_product_calory', true) ? intval(get_post_meta($p_id, '_lcm_product_calory', true)) : 0;

                // برچسب‌های رژیمی/حساسیت غذایی — از تگ‌های خود ووکامرس خوانده می‌شود (نیازی به فیلد جدید نیست)
                // فقط تگ‌هایی که ادمین با این اسلاگ‌های شناخته‌شده تعریف کرده باشد نمایش داده می‌شوند
                $lcm_diet_tag_map = array(
                    'gluten-free'  => array('icon' => '🌾', 'label' => 'بدون گلوتن'),
                    'vegan'        => array('icon' => '🌱', 'label' => 'گیاهی'),
                    'dairy-free'   => array('icon' => '🥛', 'label' => 'بدون لبنیات'),
                    'sugar-free'   => array('icon' => '🍬', 'label' => 'بدون قند'),
                    'low-calorie'  => array('icon' => '🪶', 'label' => 'کم‌کالری'),
                    'spicy'        => array('icon' => '🌶️', 'label' => 'تند'),
                    'high-protein' => array('icon' => '💪', 'label' => 'پرپروتئین'),
                );
                $p_diet_tags = array();
                $p_wc_tags = wp_get_post_terms( $p_id, 'product_tag', array('fields' => 'slugs') );
                if ( ! is_wp_error($p_wc_tags) ) {
                    foreach ( $p_wc_tags as $tag_slug ) {
                        if ( isset($lcm_diet_tag_map[$tag_slug]) ) {
                            $p_diet_tags[] = $lcm_diet_tag_map[$tag_slug];
                        }
                    }
                }
                $p_diet_tags = array_slice( $p_diet_tags, 0, 3 ); // حداکثر ۳ برچسب تا شلوغ نشود
                $p_img = wp_get_attachment_url($p_woo->get_image_id());
                
                if (preg_match('/(سرد|موکتل|اسموتی|سالاد|نوشیدنی طبیعی|میکسبری)/ui', $current_cat_obj->name) || preg_match('/(آیس|بستنی|خیار)/ui', $p_title)) {
                    $p_hot = false;
                } else { $p_hot = true; }

                // گروه تخفیفی که (در صورت وجود) روی این محصول مشخص برای این بازدیدکننده اعمال می‌شود
                $lcm_active_discount_rule = null;
                if ( ! empty( $lcm_viewer_group_slug ) && function_exists( 'lcm_get_applicable_discount' ) ) {
                    $lcm_active_discount_rule = lcm_get_applicable_discount( $p_id, $lcm_viewer_group_slug );
                }
                $is_discount_applied = ( $lcm_active_discount_rule !== null );
                $discount_percent_for_card = $is_discount_applied ? floatval( $lcm_active_discount_rule['percent'] ) : 0;
                $discount_color_for_card   = $is_discount_applied ? $lcm_active_discount_rule['color'] : '';
                $discount_label_for_card   = $is_discount_applied ? $lcm_active_discount_rule['label'] : '';

                // آیتم شانسی امروز: اگر تخفیفش بیشتر از تخفیف گروهی باشد، همین جایگزین می‌شود
                // (هرگز روی هم جمع نمی‌شوند تا تخفیف غیرمنطقی/دوبرابر ایجاد نشود)
                if ( isset($lcm_daily_lucky_item) && $lcm_daily_lucky_item && intval($lcm_daily_lucky_item['id']) === intval($p_id) ) {
                    if ( floatval($lcm_daily_lucky_item['discount_percent']) > $discount_percent_for_card ) {
                        $is_discount_applied = true;
                        $discount_percent_for_card = floatval( $lcm_daily_lucky_item['discount_percent'] );
                        $discount_color_for_card = '#ffb703';
                        $discount_label_for_card = '🎁 آیتم شانسی امروز';
                    }
                }

                $display_price = $p_price;
                if ($is_discount_applied) {
                    $display_price = $p_price - (($p_price * $discount_percent_for_card) / 100);
                }

                $stock_status = $p_woo->get_stock_status(); 
                $manage_stock = $p_woo->get_manage_stock();
                $stock_qty = $p_woo->get_stock_quantity();
                
                $is_out_of_stock = ($stock_status === 'outofstock');

                $card_style = '';
                if ($is_discount_applied) {
                    $card_style = 'style="border: 2px solid ' . esc_attr($discount_color_for_card) . '; box-shadow: 0 0 25px ' . esc_attr($discount_color_for_card) . '40; transform: scale(1.02);"';
                }

                $variations_data = array();
                if ($p_woo->is_type('variable')) {
                    $available_variations = $p_woo->get_available_variations();
                    foreach ($available_variations as $var) {
                        $size_attr = reset($var['attributes']); 
                        $variations_data[] = array(
                            'id' => $var['variation_id'],
                            'label' => !empty($size_attr) ? $size_attr : 'نامشخص',
                            'price' => $var['display_price']
                        );
                    }
                }
                ?>
                
                <div class="product-grid-card <?php echo $is_out_of_stock ? 'out-of-stock-card' : ''; ?>" id="card-<?php echo $p_id; ?>" data-cat-slug="<?php echo esc_attr($cat_slug_for_js); ?>" data-base-price="<?php echo $p_price; ?>" data-calory="<?php echo intval($p_calory); ?>" data-diet-tags="<?php echo esc_attr( implode(',', array_column($p_diet_tags, 'label')) ); ?>" data-title="<?php echo esc_attr($p_title); ?>" data-discount-percent="<?php echo esc_attr($discount_percent_for_card); ?>" data-discount-color="<?php echo esc_attr($discount_color_for_card); ?>" <?php echo $card_style; ?>>
                    
                    <?php if($is_out_of_stock): ?>
                        <div class="out-of-stock-overlay">اتمام موجودی ❌</div>
                    <?php elseif($manage_stock && $stock_qty > 0 && $stock_qty <= 5): ?>
                        <div class="low-stock-badge">🔥 فقط <?php echo $stock_qty; ?> عدد باقی مانده</div>
                    <?php endif; ?>

                    <?php if ($is_discount_applied): ?>
                        <div class="lcm-my-discount-badge" style="position:relative; z-index:5; background:<?php echo esc_attr($discount_color_for_card); ?>; color:#0b090a; font-size:0.65rem; font-weight:900; padding:4px 10px; border-radius:10px; margin-bottom:8px; text-align:center; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
                            🎁 <?php echo esc_html($discount_label_for_card); ?> · <?php echo esc_html($discount_percent_for_card); ?>٪ تخفیف مخصوص شما
                        </div>
                    <?php endif; ?>

                    <?php
                    // بج پرفروش — فقط اگه حداقل ۳ سفارش داشته باشه و در ۱۰ تای اول باشه
                    if ( ! empty($lcm_top_products) && isset($lcm_top_products[$p_id]) ) :
                        $p_order_count = $lcm_top_products[$p_id];
                        $p_rank = array_search($p_id, array_keys($lcm_top_products)) + 1;
                        if ( $p_order_count >= 3 ) :
                            $badge_icon  = $p_rank === 1 ? '🥇' : ( $p_rank <= 3 ? '🥈' : '🔥' );
                            $badge_label = $p_rank === 1 ? 'پرفروش‌ترین منو' : ( $p_rank <= 3 ? 'محبوب مشتریان' : 'پرطرفدار' );
                    ?>
                        <div style="position:relative; z-index:5; background:linear-gradient(135deg,#ffb703,#fb8500); color:#0b090a; font-size:0.65rem; font-weight:900; padding:4px 10px; border-radius:10px; margin-bottom:8px; text-align:center; box-shadow:0 4px 10px rgba(255,183,3,0.35);">
                            <?php echo $badge_icon; ?> <?php echo esc_html($badge_label); ?> · <?php echo number_format_i18n($p_order_count); ?> بار سفارش داده شده
                        </div>
                    <?php endif; endif; ?>

                    <?php if ( isset($lcm_like_counts[$p_id]) ): ?>
                        <div style="position:relative; z-index:5; font-size:0.65rem; font-weight:700; color:#e5383b; margin-bottom:8px; text-align:center;">
                            ❤️ پسندیده شده توسط <?php echo esc_html( number_format_i18n($lcm_like_counts[$p_id]) ); ?> نفر
                        </div>
                    <?php endif; ?>

                    <div class="card-header-info">
                        <div class="calorie-badge-row" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span>⚡ <?php echo $p_calory; ?> کالری</span>
                            <?php foreach ( $p_diet_tags as $tag ) : ?>
                                <span title="<?php echo esc_attr($tag['label']); ?>" style="background:rgba(46,196,182,0.12); color:var(--accent-color); font-size:0.68rem; font-weight:700; padding:2px 7px; border-radius:8px; display:inline-flex; align-items:center; gap:3px; white-space:nowrap;">
                                    <?php echo $tag['icon']; ?> <?php echo esc_html($tag['label']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <h2><?php echo esc_html($p_title); ?></h2>
                        <p><?php echo wp_strip_all_tags($p_desc); ?></p>
                    </div>

                    <div class="product-visualizer">
                        <div class="canvas-container">
                            <div class="dynamic-aura <?php echo $p_hot ? 'aura-hot' : 'aura-cold'; ?>"></div>
                            <div class="ambient-effect-container">
                                <div class="particle <?php echo $p_hot ? 'part-hot' : 'part-cold'; ?>"></div>
                                <div class="particle <?php echo $p_hot ? 'part-hot' : 'part-cold'; ?>"></div>
                            </div>
                            <div class="glass-container-box" id="glass-box-<?php echo $p_id; ?>">
                                <img src="<?php echo esc_url($p_img); ?>" class="glass-image" loading="lazy" decoding="async">
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($variations_data)): ?>
                    <div>
                        <div class="size-selector">
                            <?php $is_first = true; foreach ($variations_data as $v_item): ?>
                                <button class="size-btn <?php echo $is_first ? 'active' : ''; ?>"
                                        id="size-btn-<?php echo $p_id; ?>-<?php echo esc_attr($v_item['label']); ?>"
                                        data-label="<?php echo esc_attr($v_item['label']); ?>"
                                        data-var-id="<?php echo intval($v_item['id']); ?>"
                                        data-var-price="<?php echo $v_item['price']; ?>"
                                        onclick="lcmGridVarSize(<?php echo $p_id; ?>, this)">
                                    <?php echo esc_html($v_item['label']); ?>
                                </button>
                            <?php $is_first = false; endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" class="size-btn active" id="size-btn-<?php echo $p_id; ?>-ساده" data-label="تک سایز" data-var-id="0" data-var-price="<?php echo $p_price; ?>">
                    <?php endif; ?>

                    <?php
                    $p_upsells = $p_woo->get_upsell_ids();
                    if (empty($p_upsells)) {
                        foreach($lcm_fallback_upsell_pool as $ap_post) {
                            $ap_id = $ap_post->ID; if ($ap_id == $p_id) continue;
                            if ($p_hot && preg_match('/(شکلات|سیروپ|اسپرسو|شیر|خامه)/ui', $ap_post->post_title)) { $p_upsells[] = $ap_id; }
                            elseif (!$p_hot && preg_match('/(یخ|بستنی|نعناع|لیمو|ژله)/ui', $ap_post->post_title)) { $p_upsells[] = $ap_id; }
                            if (count($p_upsells) >= 3) break;
                        }
                    }
                    if (!empty($p_upsells)): ?>
                    <div><div class="ingredients-grid">
                        <?php foreach($p_upsells as $up_id):
                            $addon = wc_get_product($up_id); if(!$addon) continue;
                            $addon_img = wp_get_attachment_url($addon->get_image_id()) ?: ''; ?>
                            <div class="ingredient-card" data-addon-title="<?php echo esc_attr($addon->get_name()); ?>"
                                 onclick="lcmGridToggleAddon(<?php echo intval($p_id); ?>, this, '<?php echo intval($up_id); ?>', <?php echo floatval($addon->get_price()); ?>, '<?php echo esc_url($addon_img); ?>')">
                                <span class="ingredient-name"><?php echo esc_html($addon->get_name()); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div></div>
                    <?php endif; ?>

                    <div class="card-footer-action">
                        <div class="price-row">
                            <span style="font-size: 0.65rem; color: #888;">قیمت واحد:</span>
                            <div class="live-price" id="price-text-<?php echo $p_id; ?>">
                                <?php if ($is_discount_applied) : ?>
                                    <span class="lcm-discounted-price" style="color: <?php echo esc_attr($discount_color_for_card); ?>; font-weight: bold;"><?php echo number_format($display_price); ?></span>
                                    <span class="lcm-original-price" style="text-decoration: line-through; color: #888; font-size: 0.75rem; margin-right: 8px;"><?php echo number_format($p_price); ?></span>
                                <?php else : ?>
                                    <span><?php echo number_format($p_price); ?></span>
                                <?php endif; ?>
                                ت
                            </div>
                        </div>
                        <button class="btn-add-item" id="btn-add-text-<?php echo $p_id; ?>" onclick="lcmGridAddToCart(<?php echo $p_id; ?>)" <?php echo $is_out_of_stock ? 'disabled style="opacity:0.5; background: #333; color: #888;"' : ''; ?>>
                            <?php echo $is_out_of_stock ? '❌ ناموجود' : '➕ افزودن به میز'; ?>
                        </button>
                    </div>
                </div>
                
                <?php
            }
        } else {
            echo '<p style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-color); opacity: 0.5;">محصولی در این دسته‌بندی وجود ندارد ☕</p>';
        }
        ?>
