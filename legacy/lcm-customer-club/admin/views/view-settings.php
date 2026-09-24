<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// 🔒 نانس برای تمام درخواست‌های AJAX این صفحه — قبلاً هیچ‌کدام از این endpointها
// (تغییر گروه مشتری، پیام مستقیم، کیف پول، پیامک انبوه) نانس نداشتند و فقط به
// بررسی دسترسی ادمین متکی بودند؛ یعنی یک سایت مخرب می‌توانست با ترغیب یک ادمین
// واردشده به بازدید از صفحه‌ای خاص، این درخواست‌ها را بدون اطلاع او ارسال کند.
$lcm_admin_nonce = wp_create_nonce( 'lcm_admin_actions' );
?>
<script>const LCM_ADMIN_NONCE = "<?php echo esc_js( $lcm_admin_nonce ); ?>";</script>
<?php

// تعیین تب فعال به صورت هوشمند
$active_tab = 'settings';
if ( isset($_GET['tab']) ) {
    $active_tab = sanitize_text_field($_GET['tab']);
} elseif ( isset($_GET['filter_group']) || isset($_GET['filter_month']) ) {
    $active_tab = 'leads';
}

global $lcm_admin_instance;

// دسته‌بندی‌ها و لیست محصولات ووکامرس، برای استفاده در تب کش‌بک و تب گروه‌های تخفیف
$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
if ( is_wp_error( $categories ) ) { $categories = array(); }

$all_products = array();
if ( class_exists( 'WooCommerce' ) ) {
    $all_products = get_posts( array(
        'post_type'      => 'product',
        'posts_per_page' => 300,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ) );
}
?>

<div class="lcm-admin-wrap">
    <div class="lcm-dashboard-header">
        <h1>⚙️ پیشخوان و مدیریت هوشمند باشگاه مشتریان</h1>
        <p>تنظیمات دیسکانت‌ها, لیدها, رادار روزشمار تولد, کمپین‌های پیامکی و شارژ لایو کیف پول</p>
    </div>

    <div class="lcm-tabs-navigation">
        <button class="lcm-tab-btn <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-settings')">🔧 تنظیمات عمومی</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'groups' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-groups')">🎯 گروه‌های تخفیف</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'gamification' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-gamification')">🎮 چالش و امتیاز</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'cashback' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-cashback')">🎁 موتور کش‌بک</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'leads' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-leads')">📊 فیلتر لیدها و روزشمار تولد</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'sms' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-sms')">🚀 کمپین پیامکی لایو</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'wallet' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-wallet')">💰 مدیریت کیف پول</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'satisfaction' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-satisfaction')">🌟 رضایت مشتریان</button>
        <button class="lcm-tab-btn <?php echo $active_tab === 'direct_msg' ? 'active' : ''; ?>" onclick="lcmSwitchTab(event, 'lcm-tab-direct-msg')">💬 پیام مستقیم</button>
    </div>

    <div id="lcm-tab-settings" class="lcm-tab-content <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'settings' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <form method="post" action="options.php">
                <?php settings_fields( 'lcm_general_settings_group' ); do_settings_sections( 'lcm_general_settings_group' ); ?>


                <h2 style="margin-top:25px;">🎁 برنامه‌ی دعوت از دوستان</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row" style="color: #ccc;">مبلغ پاداش معرفی (به هر دو طرف):</th>
                        <td><input type="number" min="0" name="lcm_referral_reward" value="<?php echo esc_attr( get_option('lcm_referral_reward', 0) ); ?>" class="regular-text lcm-input" /> تومان
                        <p style="font-size:0.75rem; color:#888;">اگر صفر باشد، برنامه‌ی معرفی غیرفعال است. با ثبت‌نام هر عضو جدید با کد معرف، همین مبلغ به کیف پول هر دو نفر (معرف و عضو تازه) اضافه می‌شود.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top:25px;">🔑 تنظیمات اتصال به پنل پیامکی Trez (مشترک بین کد تایید و پیام مستقیم/انبوه)</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row" style="color: #ccc;">نام کاربری سامانه پیامک:</th>
                        <td><input type="text" name="lcm_sms_api_key" value="<?php echo esc_attr( get_option('lcm_sms_api_key') ); ?>" class="regular-text lcm-input" style="width: 350px !important;" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" style="color: #ccc;">رمز عبور سامانه پیامک:</th>
                        <td><input type="text" name="lcm_sms_sender_num" value="<?php echo esc_attr( get_option('lcm_sms_sender_num') ); ?>" class="regular-text lcm-input" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" style="color: #ccc;">شماره اختصاصی (فقط برای پیام مستقیم/انبوه):</th>
                        <td>
                            <input type="text" name="lcm_sms_dedicated_number" value="<?php echo esc_attr( get_option('lcm_sms_dedicated_number') ); ?>" class="regular-text lcm-input" />
                            <p style="font-size:0.75rem; color:#888; margin-top:4px;">این شماره فقط برای «پیام مستقیم» و «پیامک انبوه» لازم است — کد تایید نیازی به آن ندارد.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" style="color: #ccc;">صفحات هدف رندر منو:</th>
                        <td>
                            <?php $all_pages = get_pages(); $selected_pages = get_option( 'lcm_target_pages', array() ); if ( ! is_array( $selected_pages ) ) { $selected_pages = array(); } ?>
                            <select name="lcm_target_pages[]" multiple="multiple" class="lcm-select-multiple">
                                <?php foreach ( $all_pages as $page ) : ?>
                                    <option value="<?php echo esc_attr( $page->ID ); ?>" <?php echo in_array( $page->ID, $selected_pages ) ? 'selected' : ''; ?>><?php echo esc_html( $page->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('💾 ذخیره تنظیمات عمومی', 'primary', 'submit', true, array('class' => 'lcm-btn-save')); ?>
            </form>
        </div>
    </div>

    <div id="lcm-tab-groups" class="lcm-tab-content <?php echo $active_tab === 'groups' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'groups' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <form method="post" action="options.php">
                <?php settings_fields( 'lcm_groups_settings_group' ); ?>

                <h2 style="color:#43e97b; margin-bottom: 5px;">🎯 گروه‌های تخفیف مشتریان (نامحدود)</h2>
                <p style="color:#ccc; font-size:0.85rem; margin-bottom:25px;">
                    هر تعداد گروه دلخواه بساز (مثلاً بدنساز، رژیمی، دانشجو، VIP و ...)، برای هرکدام یک رنگ و درصد تخفیف بگذار،
                    و مشخص کن این تخفیف فقط روی کدام دسته‌بندی‌ها یا کدام محصولات مشخص اعمال شود. این آیتم‌ها در منو با همان رنگ
                    مشخص می‌شوند و به مشتری اعلام می‌شود «این تخفیف مخصوص شماست».
                </p>

                <div id="lcm-groups-container">
                    <?php
                    $saved_groups = function_exists('lcm_get_discount_groups') ? lcm_get_discount_groups() : array();
                    foreach ( $saved_groups as $g_index => $grp ) :
                        $g_label = isset($grp['label']) ? $grp['label'] : '';
                        $g_slug  = isset($grp['slug']) ? $grp['slug'] : '';
                        $g_color = isset($grp['color']) ? $grp['color'] : '#2ec4b6';
                        $g_pct   = isset($grp['percent']) ? floatval($grp['percent']) : 0;
                        $g_cats  = isset($grp['categories']) && is_array($grp['categories']) ? $grp['categories'] : array();
                        $g_prods = isset($grp['products']) && is_array($grp['products']) ? array_map('intval', $grp['products']) : array();
                        $g_min_spend = isset($grp['min_spend']) ? floatval($grp['min_spend']) : 0;
                    ?>
                    <div class="lcm-group-card" style="background: rgba(22, 26, 29, 0.4); border: 1px solid rgba(255,255,255,0.08); border-right: 4px solid <?php echo esc_attr($g_color); ?>; border-radius: 12px; padding: 18px; margin-bottom: 16px;">
                        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:12px;">
                            <div style="flex:2; min-width:180px;">
                                <label style="font-size:0.75rem; color:#aaa;">🏷️ عنوان نمایشی گروه</label>
                                <input type="text" name="lcm_discount_groups[<?php echo $g_index; ?>][label]" value="<?php echo esc_attr($g_label); ?>" class="lcm-input" style="width:100%;" placeholder="مثلاً: 🏋️‍♂️ بدنساز">
                            </div>
                            <div style="flex:1; min-width:140px;">
                                <label style="font-size:0.75rem; color:#aaa;">شناسه (انگلیسی، بدون فاصله)</label>
                                <input type="text" name="lcm_discount_groups[<?php echo $g_index; ?>][slug]" value="<?php echo esc_attr($g_slug); ?>" class="lcm-input" style="width:100%;" placeholder="bodybuilder">
                            </div>
                            <div style="width:90px;">
                                <label style="font-size:0.75rem; color:#aaa;">رنگ</label>
                                <input type="color" name="lcm_discount_groups[<?php echo $g_index; ?>][color]" value="<?php echo esc_attr($g_color); ?>" style="width:100%; height:36px; border-radius:8px; border:none; cursor:pointer;">
                            </div>
                            <div style="width:110px;">
                                <label style="font-size:0.75rem; color:#aaa;">درصد تخفیف</label>
                                <input type="number" min="0" max="100" name="lcm_discount_groups[<?php echo $g_index; ?>][percent]" value="<?php echo esc_attr($g_pct); ?>" class="lcm-input" style="width:100%;">
                            </div>
                            <div style="width:150px;">
                                <label style="font-size:0.75rem; color:#aaa;">🏆 حداقل خرید برای ورود خودکار</label>
                                <input type="number" min="0" name="lcm_discount_groups[<?php echo $g_index; ?>][min_spend]" value="<?php echo esc_attr($g_min_spend); ?>" class="lcm-input" style="width:100%;" placeholder="0 = فقط دستی">
                            </div>
                            <div style="align-self:flex-end;">
                                <button type="button" class="button" style="background:#e5383b; color:#fff; border:none; border-radius:6px; padding:8px 12px; cursor:pointer;" onclick="jQuery(this).closest('.lcm-group-card').remove();">❌ حذف گروه</button>
                            </div>
                        </div>
                        <div style="display:flex; gap:14px; flex-wrap:wrap;">
                            <div style="flex:1; min-width:220px;">
                                <label style="font-size:0.75rem; color:#aaa;">📦 روی کدام دسته‌بندی‌ها اعمال شود؟</label>
                                <select name="lcm_discount_groups[<?php echo $g_index; ?>][categories][]" multiple class="lcm-select-multiple" style="width:100%; min-height:70px;">
                                    <?php foreach ( $categories as $cat ) : ?>
                                        <option value="<?php echo esc_attr($cat->slug); ?>" <?php echo in_array($cat->slug, $g_cats) ? 'selected' : ''; ?>><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="flex:1; min-width:220px;">
                                <label style="font-size:0.75rem; color:#aaa;">🍽️ یا فقط روی این محصولات مشخص</label>
                                <select name="lcm_discount_groups[<?php echo $g_index; ?>][products][]" multiple class="lcm-select-multiple" style="width:100%; min-height:70px;">
                                    <?php foreach ( $all_products as $prod ) : ?>
                                        <option value="<?php echo esc_attr($prod->ID); ?>" <?php echo in_array($prod->ID, $g_prods, true) ? 'selected' : ''; ?>><?php echo esc_html($prod->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 15px; display: flex; gap: 12px;">
                    <button type="button" class="button" style="background:#2ec4b6; color:#fff; font-weight: bold; height: 42px; border: none; padding: 0 20px; border-radius: 10px; cursor: pointer;" id="lcm-add-group-card">➕ گروه تخفیف جدید</button>
                    <?php submit_button('💾 ذخیره گروه‌های تخفیف', 'primary', 'submit', false, array('class' => 'lcm-btn-save', 'style' => 'background:#43e97b !important; color:#000 !important; margin:0 !important; height:42px; border-radius:10px;')); ?>
                </div>
            </form>
        </div>
    </div>

    <div id="lcm-tab-gamification" class="lcm-tab-content <?php echo $active_tab === 'gamification' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'gamification' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <form method="post" action="options.php">
                <?php settings_fields( 'lcm_gamification_settings_group' ); ?>

                <h2 style="color:#2ec4b6; margin-bottom: 5px;">🎯 چالش هفتگی</h2>
                <p style="color:#ccc; font-size:0.85rem; margin-bottom:20px;">
                    هر هفته (از شنبه) یک چالش تازه فعال می‌شود. مشتری با رسیدن به تعداد هدف، امتیاز جایزه می‌گیرد — فقط یک‌بار در هفته.
                </p>
                <?php $challenge = get_option('lcm_weekly_challenge', array()); if (!is_array($challenge)) { $challenge = array(); } ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row" style="color:#ccc;">عنوان چالش (برای نمایش به مشتری):</th>
                        <td><input type="text" name="lcm_weekly_challenge[label]" value="<?php echo esc_attr($challenge['label'] ?? ''); ?>" class="regular-text lcm-input" placeholder="مثلاً: این هفته ۳ بار از دسته دمی سفارش بده و ۵۰ امتیاز بگیر"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" style="color:#ccc;">دسته‌بندی هدف:</th>
                        <td>
                            <select name="lcm_weekly_challenge[category_slug]" class="lcm-input">
                                <option value="">— انتخاب کنید —</option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($challenge['category_slug'] ?? '', $cat->slug); ?>><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" style="color:#ccc;">تعداد سفارش هدف (در همین هفته):</th>
                        <td><input type="number" min="1" name="lcm_weekly_challenge[target_count]" value="<?php echo esc_attr($challenge['target_count'] ?? 3); ?>" class="small-text lcm-input"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" style="color:#ccc;">امتیاز جایزه:</th>
                        <td><input type="number" min="0" name="lcm_weekly_challenge[points_reward]" value="<?php echo esc_attr($challenge['points_reward'] ?? 50); ?>" class="small-text lcm-input"></td>
                    </tr>
                </table>

                <h2 style="color:#ffb703; margin-top:30px;">💎 نرخ تبدیل امتیاز به کیف پول</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row" style="color:#ccc;">هر چند امتیاز = ۱٬۰۰۰ تومان کیف پول؟</th>
                        <td><input type="number" min="1" name="lcm_points_conversion_rate" value="<?php echo esc_attr( get_option('lcm_points_conversion_rate', 10) ); ?>" class="small-text lcm-input"> امتیاز</td>
                    </tr>
                </table>

                <h2 style="color:#ff70a6; margin-top:30px;">🎂 تبریک و هدیه‌ی خودکار تولد</h2>
                <p style="color:#ccc; font-size:0.85rem; margin-bottom:15px;">
                    هر روز صبح (ساعت ۹) خودکار چک می‌شود کدام اعضا امروز تولدشان است، و در صورت فعال بودن، هدیه به کیف پولشان اضافه و برایشان پیامک/اعلان ارسال می‌شود.
                </p>
                <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="lcm_birthday_gift_enabled" value="1" <?php checked( get_option('lcm_birthday_gift_enabled', '0'), '1' ); ?>> فعال باشد</label>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row" style="color:#ccc;">مبلغ هدیه‌ی تولد (تومان):</th>
                        <td><input type="number" min="0" name="lcm_birthday_gift_amount" value="<?php echo esc_attr( get_option('lcm_birthday_gift_amount', 0) ); ?>" class="regular-text lcm-input"><p style="font-size:0.75rem; color:#888;">صفر یعنی فقط پیام تبریک ارسال شود، بدون هدیه‌ی نقدی.</p></td>
                    </tr>
                </table>

                <?php submit_button('💾 ذخیره تنظیمات چالش و امتیاز'); ?>
            </form>
        </div>
    </div>

    <div id="lcm-tab-cashback" class="lcm-tab-content <?php echo $active_tab === 'cashback' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'cashback' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <form method="post" action="options.php">
                <?php settings_fields( 'lcm_cashback_settings_group' ); do_settings_sections( 'lcm_cashback_settings_group' ); ?>
                
                <h2 style="color:#ffb703; margin-bottom: 5px;">🎯 مدیریت پویای قوانین کش‌بک چند دسته‌بندی</h2>
                <p style="color:#ccc; font-size:0.85rem; margin-bottom:25px;">تعیین کنید که مشتری با خرید از هر دسته‌بندی, چه مقدار شارژ هدیه در کیف پول باشگاه دریافت کند.</p>

                <table class="wp-list-table widefat fixed striped" style="background: rgba(22, 26, 29, 0.4); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; overflow: hidden; width: 100%;">
                    <thead>
                        <tr style="background: rgba(0,0,0,0.4);">
                            <th style="padding: 16px; color: #2ec4b6 !important; font-weight: bold;">📦 دسته‌بندی هدف ووکامرس</th>
                            <th style="padding: 16px; color: #2ec4b6 !important; font-weight: bold;">💰 حداقل خرید از این دسته (تومان)</th>
                            <th style="padding: 16px; color: #2ec4b6 !important; font-weight: bold;">🎁 مبلغ پاداش ولت (تومان)</th>
                            <th style="padding: 16px; color: #e5383b !important; font-weight: bold; width: 80px; text-align: center;">حذف</th>
                        </tr>
                    </thead>
                    <tbody id="lcm-rules-tbody">
                        <?php 
                        $saved_rules = get_option('lcm_cashback_multi_rules', array());
                        if ( ! is_array($saved_rules) ) { $saved_rules = array(); }

                        if ( ! empty($saved_rules) ) {
                            foreach ( $saved_rules as $index => $rule ) {
                                $min_spend = isset($rule['min_spend']) ? intval($rule['min_spend']) : 0;
                                $reward    = isset($rule['reward']) ? intval($rule['reward']) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <select name="lcm_cashback_multi_rules[<?php echo $index; ?>][category_slug]" class="lcm-input" style="width: 100%; max-width: 240px;">
                                            <?php foreach ( $categories as $cat ) : ?>
                                                <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($rule['category_slug'], $cat->slug); ?>><?php echo esc_html($cat->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="lcm_cashback_multi_rules[<?php echo $index; ?>][min_spend]" value="<?php echo esc_attr($min_spend); ?>" class="lcm-input lcm-numeric-only" style="width:160px !important; text-align: center;"> تومان
                                    </td>
                                    <td>
                                        <input type="text" name="lcm_cashback_multi_rules[<?php echo $index; ?>][reward]" value="<?php echo esc_attr($reward); ?>" class="lcm-input lcm-numeric-only" style="width:160px !important; text-align: center;"> تومان
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="button" style="background:#e5383b; color:#fff; border:none; border-radius:6px; padding:4px 8px; cursor:pointer;" onclick="jQuery(this).closest('tr').remove();">❌</button>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <div style="margin-top: 25px; display: flex; gap: 12px;">
                    <button type="button" class="button" style="background:#2ec4b6; color:#fff; font-weight: bold; height: 42px; border: none; padding: 0 20px; border-radius: 10px; cursor: pointer;" id="lcm-add-rule-row">➕ اضافه کردن قانون جدید</button>
                    <?php submit_button('💾 ذخیره قوانین کش‌بک', 'primary', 'submit', false, array('class' => 'lcm-btn-save', 'style' => 'background:#ffb703 !important; color:#000 !important; margin:0 !important; height:42px; border-radius:10px;')); ?>
                </div>
            </form>
        </div>
    </div>

    <div id="lcm-tab-leads" class="lcm-tab-content <?php echo $active_tab === 'leads' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'leads' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-stats-grid">
            <div class="lcm-stat-card total"><div class="stat-value"><?php echo number_format_i18n($total_members); ?></div><div class="stat-label">کل اعضا</div></div>
            <?php foreach ( $group_stats as $gs ) : ?>
                <div class="lcm-stat-card" style="border-right: 4px solid <?php echo esc_attr($gs['color']); ?>;">
                    <div class="stat-value"><?php echo number_format_i18n($gs['count']); ?></div>
                    <div class="stat-label"><?php echo esc_html($gs['label']); ?></div>
                </div>
            <?php endforeach; ?>
            <div class="lcm-stat-card"><div class="stat-value">⭐ <?php echo $ratings_count > 0 ? esc_html($avg_rating) : '—'; ?></div><div class="stat-label">میانگین رضایت (<?php echo number_format_i18n($ratings_count); ?> نظر)</div></div>
        </div>

        <div class="lcm-filter-box">
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                <div class="filter-inputs-row">
                    <div class="filter-group-field">
                        <label>🎯 سبک زندگی:</label>
                        <select name="filter_group">
                            <option value="all" <?php selected($filter_group, 'all'); ?>>همه گروه‌ها</option>
                            <?php foreach ( ( function_exists('lcm_get_discount_groups') ? lcm_get_discount_groups() : array() ) as $grp ) : ?>
                                <option value="<?php echo esc_attr($grp['slug']); ?>" <?php selected($filter_group, $grp['slug']); ?>><?php echo esc_html($grp['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group-field">
                        <label>📅 متولدین ماه:</label>
                        <select name="filter_month">
                            <option value="0" <?php selected($filter_month, 0); ?>>همه ماه‌ها</option>
                            <?php foreach ($months as $key => $m_name) { $m_num = $key + 1; echo '<option value="'.$m_num.'" '.selected($filter_month, $m_num, false).'>'.$m_name.'</option>'; } ?>
                        </select>
                    </div>
                    <div class="filter-actions-field">
                        <button type="submit" class="button lcm-btn-filter">🔍 اعمال فیلتر</button>
                        <button type="button" onclick="lcmExportToCSV()" class="button lcm-btn-export">📥 خروجی CSV</button>
                    </div>
                </div>
            </form>
        </div>

        <h2>📋 لیست اعضای باشگاه مشتریان (لیدها)</h2>
        <div class="lcm-table-container">
            <table class="wp-list-table widefat striped table-view-list" id="lcmClubTable" style="min-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>نام مشتری</th>
                        <th>📞 شماره موبایل</th>
                        <th>🎯 سبک زندگی</th>
                        <th>🎂 روزشمار تولد</th>
                        <th>⏱️ تاریخ عضویت</th>
                        <th>✏️ تغییر دستی گروه</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( isset($members) && ! empty( $members ) ) : ?>
                        <?php foreach ( $members as $member ) : 
                            $matched_grp = function_exists('lcm_get_discount_group_by_slug') ? lcm_get_discount_group_by_slug($member->user_group) : null;
                            $group_label = $matched_grp ? $matched_grp['label'] : 'علاقه‌مند / عادی';
                            
                            $days_left = $lcm_admin_instance->get_days_until_birthday(intval($member->birth_day), intval($member->birth_month));
                            $is_birthday_near = ($days_left <= 7);
                            $row_class = $is_birthday_near ? 'style="background: rgba(255, 183, 3, 0.08) !important; font-weight: bold;"' : '';
                            ?>
                            <tr <?php echo $row_class; ?>>
                                <td>`<?php echo esc_html( $member->id ); ?>`</td>
                                <td><?php echo esc_html( $member->name ); if($is_birthday_near) echo ' <span class="birthday-glow-badge">🎂 نزدیک</span>'; ?></td>
                                <td><code><?php echo esc_html( $member->phone ); ?></code></td>
                                <td><span class="lcm-badge-grp <?php echo esc_attr($member->user_group); ?>"><?php echo $group_label; ?></span></td>
                                <td>
                                    <?php if($days_left === 0 || $days_left === 365) echo '<span style="color:#43e97b; font-weight:900;">🎉 امروز تولد اوست!</span>';
                                          else echo number_format_i18n($days_left) . ' روز مانده'; ?>
                                </td>
                                <td><?php echo esc_html( $member->created_at ); ?></td>
                                <td>
                                    <div style="display:flex; gap:4px;">
                                        <select class="lcm-manual-group-select" data-member-id="<?php echo esc_attr($member->id); ?>" style="font-size:0.75rem; padding:3px;">
                                            <?php foreach ( ( function_exists('lcm_get_discount_groups') ? lcm_get_discount_groups() : array() ) as $grp ) : ?>
                                                <option value="<?php echo esc_attr($grp['slug']); ?>" <?php selected($member->user_group, $grp['slug']); ?>><?php echo esc_html($grp['label']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="button lcm-manual-group-save" data-member-id="<?php echo esc_attr($member->id); ?>" style="font-size:0.7rem; padding:2px 8px;">ثبت</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px;">هیچ عضوی یافت نشد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="lcm-tab-sms" class="lcm-tab-content <?php echo $active_tab === 'sms' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'sms' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <h2>🚀 ارسال پیامک هدفمند گروهی به اعضا</h2>
            <div style="max-width: 600px; display:flex; flex-direction:column; gap:15px;">
                <div class="filter-group-field">
                    <label>👥 انتخاب گروه کاربری هدف:</label>
                    <select id="smsTargetGroup">
                        <option value="all">📢 همه اعضا</option>
                        <?php foreach ( ( function_exists('lcm_get_discount_groups') ? lcm_get_discount_groups() : array() ) as $grp ) : ?>
                            <option value="<?php echo esc_attr($grp['slug']); ?>"><?php echo esc_html($grp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group-field">
                    <label>💬 متن پیامک کمپین:</label>
                    <textarea id="smsMessageText" rows="6" class="lcm-input" style="width:100% !important; height:auto !important; padding:12px;" placeholder="متن پیامک خود را بنویسید..."></textarea>
                </div>
                <button type="button" onclick="lcmSendBulkSMS(this)" class="lcm-btn-save" style="background:#ffb703 !important; color:#000 !important;">🚀 شلیک کمپین پیامکی</button>
                <div id="smsStatusResponse" style="margin-top:10px; font-weight:bold; font-size:0.85rem;"></div>
            </div>
        </div>
    </div>

    <div id="lcm-tab-wallet" class="lcm-tab-content <?php echo $active_tab === 'wallet' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'wallet' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <h2>💰 مدیریت مرکزی کیف پول دیجیتال مشتریان</h2>
            <p style="color:#aaa; font-size:0.8rem; margin-bottom:20px;">در این بخش می‌توانید اعتبار هر مشتری را به طور مستقیم شارژ یا کسر کنید.</p>
            
            <div class="lcm-table-container">
                <table class="wp-list-table widefat fixed striped table-view-list" id="lcmWalletTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>نام مشتری</th>
                            <th>📞 شماره موبایل</th>
                            <th>💰 موجودی کیف پول</th>
                            <th>⚙️ عملیات مدیریت دستی</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $members ) ) : ?>
                            <?php foreach ( $members as $member ) : 
                                $wallet_bal = isset($member->wallet_balance) ? intval($member->wallet_balance) : 0;
                                ?>
                                <tr id="lcm-user-row-<?php echo $member->id; ?>">
                                    <td>`<?php echo esc_html( $member->id ); ?>`</td>
                                    <td><strong><?php echo esc_html( $member->name ); ?></strong></td>
                                    <td><code><?php echo esc_html( $member->phone ); ?></code></td>
                                    <td>
                                        <div class="view-balance-mode">
                                            <strong style="color:#43e97b; font-size: 1rem;" class="current-bal-text"><?php echo number_format($wallet_bal); ?></strong> <span style="color:#888;">تومان</span>
                                        </div>
                                        <div class="edit-balance-mode" style="display:none;">
                                            <input type="number" class="lcm-input input-balance-edit" value="<?php echo $wallet_bal; ?>" style="width:120px !important; height:30px; font-weight:bold;" /> ت
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="lcm-btn-action btn-edit" onclick="lcmEnableWalletEdit(<?php echo $member->id; ?>)">✏️ ویرایش اعتبار</button>
                                        <button type="button" class="lcm-btn-action btn-save" onclick="lcmSaveWalletEdit(<?php echo $member->id; ?>, this)" style="display:none; background:#43e97b !important; color:#000;">💾 ثبت</button>
                                        <button type="button" class="lcm-btn-action btn-cancel" onclick="lcmCancelWalletEdit(<?php echo $member->id; ?>)" style="display:none; background:#e5383b !important;">❌ لغو</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px;">هیچ عضوی یافت نشد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="lcm-tab-satisfaction" class="lcm-tab-content <?php echo $active_tab === 'satisfaction' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'satisfaction' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <h2>🌟 رضایت مشتریان</h2>
            <p style="color:#aaa; font-size:0.8rem; margin-bottom:20px;">
                میانگین رضایت کلی: <strong style="color:#ffb703;">⭐ <?php echo $ratings_count > 0 ? esc_html($avg_rating) : '—'; ?></strong>
                (از <?php echo number_format_i18n($ratings_count); ?> نظر). امتیازها و پسندیدن‌ها تا ۲۴ ساعت بعد از سفارش توسط مشتری قابل ویرایش‌اند.
            </p>

            <h3 style="color:#2ec4b6; font-size:1rem;">⭐ امتیاز سفارش‌ها</h3>
            <div class="lcm-table-container" style="margin-bottom:30px;">
                <table class="wp-list-table widefat striped table-view-list">
                    <thead><tr><th>مشتری</th><th>📞 شماره</th><th>شماره سفارش</th><th>امتیاز</th><th>آخرین ثبت/ویرایش</th></tr></thead>
                    <tbody>
                        <?php if ( ! empty( $satisfaction_ratings ) ) : foreach ( $satisfaction_ratings as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( $r->member_name ?: '—' ); ?></td>
                                <td><code><?php echo esc_html( $r->phone ); ?></code></td>
                                <td>#<?php echo esc_html( $r->order_id ); ?></td>
                                <td style="color:#ffb703;"><?php echo str_repeat('⭐', intval($r->stars)); ?></td>
                                <td><?php echo esc_html( $r->updated_at ); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px;">هنوز نظری ثبت نشده.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="color:#e5383b; font-size:1rem;">❤️ آیتم‌های پسندیده‌شده</h3>
            <div class="lcm-table-container">
                <table class="wp-list-table widefat striped table-view-list">
                    <thead><tr><th>مشتری</th><th>📞 شماره</th><th>آیتم پسندیده‌شده</th><th>شماره سفارش</th><th>تاریخ</th></tr></thead>
                    <tbody>
                        <?php if ( ! empty( $satisfaction_likes ) ) : foreach ( $satisfaction_likes as $l ) : ?>
                            <tr>
                                <td><?php echo esc_html( $l->member_name ?: '—' ); ?></td>
                                <td><code><?php echo esc_html( $l->phone ); ?></code></td>
                                <td>❤️ <?php echo esc_html( get_the_title( $l->product_id ) ?: ('#' . $l->product_id) ); ?></td>
                                <td>#<?php echo esc_html( $l->order_id ); ?></td>
                                <td><?php echo esc_html( $l->created_at ); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px;">هنوز آیتمی پسندیده نشده.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="lcm-tab-direct-msg" class="lcm-tab-content <?php echo $active_tab === 'direct_msg' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'direct_msg' ? 'display:block;' : 'display:none;'; ?>">
        <div class="lcm-config-section">
            <h2>💬 پیام مستقیم به مشتری</h2>
            <p style="color:#aaa; font-size:0.85rem; margin-bottom:20px;">پیام شما از طریق اعلان مرورگر (Push) و/یا پیامک به مشتری ارسال می‌شود.</p>

            <div style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:24px; max-width:520px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:0.8rem; color:#aaa; margin-bottom:6px;">🔍 جستجوی مشتری (نام یا شماره)</label>
                    <div style="position:relative;">
                        <input type="text" id="lcmDmSearch" placeholder="نام یا شماره را تایپ کنید..." class="lcm-input" autocomplete="off"
                               style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.05); color:#fff; font-family:inherit; font-size:0.85rem;"
                               oninput="lcmDmSearchMembers(this.value)">
                        <div id="lcmDmSuggestions" style="display:none; position:absolute; top:100%; right:0; left:0; background:#1a1a1a; border:1px solid rgba(255,255,255,0.15); border-radius:0 0 10px 10px; z-index:9999; max-height:200px; overflow-y:auto;"></div>
                    </div>
                    <div id="lcmDmSelectedMember" style="display:none; margin-top:8px; background:rgba(46,196,182,0.1); border:1px solid #2ec4b6; border-radius:10px; padding:8px 12px; font-size:0.8rem; display:flex; align-items:center; justify-content:space-between;">
                        <span id="lcmDmSelectedLabel">—</span>
                        <button onclick="lcmDmClearSelection()" style="background:none; border:none; color:#aaa; cursor:pointer; font-size:1rem;">✕</button>
                    </div>
                    <input type="hidden" id="lcmDmPhone">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:0.8rem; color:#aaa; margin-bottom:6px;">✍️ متن پیام</label>
                    <textarea id="lcmDmMessage" rows="4" maxlength="250" placeholder="متن پیام را بنویسید..." class="lcm-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.05); color:#fff; font-family:inherit; font-size:0.85rem; resize:vertical;"></textarea>
                    <p id="lcmDmCharCount" style="font-size:0.7rem; color:#666; text-align:left; margin-top:4px;">۰ / ۲۵۰</p>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:0.8rem; color:#aaa; margin-bottom:8px;">📡 کانال ارسال</label>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem;"><input type="radio" name="lcm_dm_channel" value="both" checked> هر دو (Push + پیامک)</label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem;"><input type="radio" name="lcm_dm_channel" value="push"> فقط اعلان مرورگر</label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem;"><input type="radio" name="lcm_dm_channel" value="sms"> فقط پیامک</label>
                    </div>
                </div>
                <button id="lcmDmSendBtn" onclick="lcmSendDirectMessage()" style="width:100%; padding:12px; border-radius:12px; border:none; background:var(--accent-color,#2ec4b6); color:#fff; font-weight:800; font-size:0.9rem; cursor:pointer;">
                    📤 ارسال پیام
                </button>
                <div id="lcmDmResult" style="margin-top:12px; font-size:0.8rem; display:none;"></div>
            </div>
        </div>
    </div>

</div>

<script>
document.getElementById('lcmDmMessage').addEventListener('input', function() {
    document.getElementById('lcmDmCharCount').innerText = this.value.length + ' / ۲۵۰';
});

var lcmDmSearchTimer = null;
function lcmDmSearchMembers(query) {
    clearTimeout(lcmDmSearchTimer);
    var box = document.getElementById('lcmDmSuggestions');
    if (query.length < 2) { box.style.display = 'none'; return; }

    lcmDmSearchTimer = setTimeout(function() {
        var formData = new FormData();
        formData.append('action', 'lcm_search_members_for_dm');
        formData.append('nonce', LCM_ADMIN_NONCE);
        formData.append('query', query);

        fetch(ajaxurl, { method: 'POST', body: formData })
        .then(function(r){ return r.json(); })
        .then(function(res){
            box.innerHTML = '';
            if (!res.success || !res.data.length) {
                box.innerHTML = '<div style="padding:10px; color:#888; font-size:0.8rem;">مشتری‌ای پیدا نشد</div>';
                box.style.display = 'block';
                return;
            }
            res.data.forEach(function(m) {
                var item = document.createElement('div');
                item.style.cssText = 'padding:10px 12px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.8rem; display:flex; justify-content:space-between;';
                item.innerHTML = '<span>👤 ' + m.name + '</span><span style="color:#888;">' + m.phone + '</span>';
                item.addEventListener('mouseover', function(){ this.style.background='rgba(255,255,255,0.05)'; });
                item.addEventListener('mouseout',  function(){ this.style.background=''; });
                item.addEventListener('click', function(){
                    document.getElementById('lcmDmPhone').value = m.phone;
                    document.getElementById('lcmDmSearch').value = '';
                    document.getElementById('lcmDmSelectedLabel').innerText = '✅ ' + m.name + ' — ' + m.phone;
                    document.getElementById('lcmDmSelectedMember').style.display = 'flex';
                    box.style.display = 'none';
                });
                box.appendChild(item);
            });
            box.style.display = 'block';
        });
    }, 300);
}

function lcmDmClearSelection() {
    document.getElementById('lcmDmPhone').value = '';
    document.getElementById('lcmDmSearch').value = '';
    document.getElementById('lcmDmSelectedMember').style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#lcmDmSearch') && !e.target.closest('#lcmDmSuggestions')) {
        document.getElementById('lcmDmSuggestions').style.display = 'none';
    }
});

function lcmSendDirectMessage() {
    var phone   = document.getElementById('lcmDmPhone').value.trim();
    var message = document.getElementById('lcmDmMessage').value.trim();
    var channel = document.querySelector('input[name="lcm_dm_channel"]:checked').value;
    var resultEl = document.getElementById('lcmDmResult');
    var btn = document.getElementById('lcmDmSendBtn');

    if (!phone || !message) { resultEl.style.display='block'; resultEl.style.color='#ff8080'; resultEl.innerText='❌ شماره و متن پیام الزامی است.'; return; }

    btn.disabled = true; btn.innerText = '⏳ در حال ارسال...';
    resultEl.style.display = 'none';

    var formData = new FormData();
    formData.append('action', 'lcm_send_direct_message');
    formData.append('nonce', LCM_ADMIN_NONCE);
    formData.append('phone', phone);
    formData.append('message', message);
    formData.append('channel', channel);

    fetch(ajaxurl, { method: 'POST', body: formData })
    .then(function(r){ return r.json(); })
    .then(function(res){
        btn.disabled = false; btn.innerText = '📤 ارسال پیام';
        resultEl.style.display = 'block';
        if (res.success) {
            resultEl.style.color = '#43e97b';
            resultEl.innerHTML = res.data.results.join('<br>');
        } else {
            resultEl.style.color = '#ff8080';
            resultEl.innerText = '❌ ' + (res.data || 'خطا در ارسال');
        }
    })
    .catch(function(){
        btn.disabled = false; btn.innerText = '📤 ارسال پیام';
        resultEl.style.display = 'block'; resultEl.style.color = '#ff8080';
        resultEl.innerText = '❌ خطا در ارتباط با سرور';
    });
}
</script>

<style>
    .lcm-admin-wrap { background: #0b090a; color: #f5f3f4; padding: 25px; border-radius: 20px; margin: 20px 20px 0 0; min-height: 85vh; box-sizing: border-box; }
    .lcm-dashboard-header h1 { font-size: 1.7rem; color: #2ec4b6; font-weight: 900; margin-bottom: 5px; }
    .lcm-dashboard-header p { color: #888; font-size: 0.85rem; margin-top: 0; }
    .lcm-tabs-navigation { display: flex; gap: 10px; margin: 20px 0 5px 0; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px; }
    .lcm-tab-btn { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); color: #aaa; padding: 12px 24px; font-weight: bold; border-radius: 12px; cursor: pointer; transition: all 0.25s; }
    .lcm-tab-btn.active { background: #2ec4b6; color: #fff; border-color: #2ec4b6; }
    .lcm-tab-content { display: none; }
    .lcm-config-section { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 16px; margin-top: 20px; }
    .lcm-input { background: #161a1d !important; border: 1px solid rgba(255,255,255,0.1) !important; color: #fff !important; padding: 6px 12px !important; border-radius: 8px !important; }
    .lcm-select-multiple { background: #161a1d !important; border: 1px solid rgba(255,255,255,0.1) !important; color: #fff !important; border-radius: 12px !important; padding: 10px !important; min-width: 250px; height: 120px !important; }
    .lcm-btn-save { background: #2ec4b6 !important; color: #fff !important; border: none !important; padding: 12px 30px !important; font-weight: bold !important; border-radius: 10px !important; cursor: pointer; text-shadow:none !important; }
    .lcm-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 25px 0; }
    .lcm-stat-card { background: rgba(22, 26, 29, 0.6); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 16px; text-align: right; }
    .stat-value { font-size: 2rem; font-weight: 900; color: #fff; }
    .stat-label { font-size: 0.8rem; color: #888; }
    .lcm-stat-card.total { border-right: 4px solid #ffb703; }
    .lcm-stat-card.bodybuilder { border-right: 4px solid #2ec4b6; }
    .lcm-stat-card.diet { border-right: 4px solid #43e97b; }
    .lcm-stat-card.normal { border-right: 4px solid #af43e9; }
    .lcm-filter-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); padding: 20px; border-radius: 16px; margin: 25px 0; }
    .filter-inputs-row { display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; }
    .filter-group-field { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 200px; }
    .filter-group-field select { background: #161a1d !important; border: 1px solid rgba(255,255,255,0.1) !important; color: #fff !important; height: 42px !important; border-radius: 10px !important; }
    .filter-actions-field { display: flex; gap: 12px; }
    .lcm-btn-filter { background: #2ec4b6 !important; color: #fff !important; border: none !important; height: 42px !important; padding: 0 22px !important; border-radius: 10px !important; cursor: pointer; }
    .lcm-btn-export { background: #ffb703 !important; color: #000 !important; border: none !important; height: 42px !important; padding: 0 22px !important; border-radius: 10px !important; cursor: pointer; }
    .lcm-table-container { background: rgba(22, 26, 29, 0.4); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; overflow-x: auto; margin-top: 15px; }
    #lcmClubTable, #lcmWalletTable { background: transparent !important; color: #fff !important; width: 100%; border-collapse: collapse; }
    #lcmClubTable th, #lcmWalletTable th { background: rgba(0,0,0,0.4) !important; color: #2ec4b6 !important; padding: 16px !important; }
    #lcmWalletTable th { color: #ffb703 !important; }
    #lcmClubTable td, #lcmWalletTable td { padding: 15px !important; border-bottom: 1px solid rgba(255,255,255,0.04) !important; }
    .lcm-badge-grp { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; }
    .lcm-badge-grp.bodybuilder { background: rgba(46, 196, 182, 0.15); color: #2ec4b6; }
    .lcm-badge-grp.diet { background: rgba(67, 233, 123, 0.15); color: #43e97b; }
    .lcm-badge-grp.normal { background: rgba(175, 67, 233, 0.15); color: #af43e9; }
    .birthday-glow-badge { background: #ffb703; color: #000; font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; font-weight: 900; }
    .lcm-btn-action { border: none; padding: 5px 12px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.75rem; color: #fff; background: rgba(255,255,255,0.08); margin-left: 5px; }
    .btn-edit { background: rgba(255, 183, 3, 0.15); color: #ffb703; border: 1px solid #ffb703; }
    code { background: rgba(255,255,255,0.07) !important; color: #ffb703 !important; padding: 4px 8px !important; border-radius: 6px !important; }
</style>

<script>
function lcmSwitchTab(evt, tabId) {
    document.querySelectorAll('.lcm-tab-content').forEach(content => content.style.display = 'none');
    document.querySelectorAll('.lcm-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    evt.currentTarget.classList.add('active');
    
    const tabName = tabId.replace('lcm-tab-', '');
    const newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=lcm-club-settings&tab=' + tabName;
    window.history.pushState({path:newurl}, '', newurl);
}

jQuery(document).ready(function($) {
    let rowIndex = $('#lcm-rules-tbody tr').length;
    
    // 🌟 فیکس انقلابی: به محض تایپ، اعداد فارسی یا کاراکترهای منفی را اتوماتیک پاک و انگلیسی می‌کند
    $(document).on('input', '.lcm-numeric-only', function() {
        let val = $(this).val();
        // تبدیل اعداد فارسی به انگلیسی
        let p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
        let converted = p2e(val);
        // حذف تمام کاراکترهای غیرعددی (شامل علامت منفی)
        let clean = converted.replace(/[^0-9]/g, '');
        $(this).val(clean);
    });
    
    // افزودن داینامیک ردیف‌های کش‌بک جدید
    $('#lcm-add-rule-row').on('click', function() {
        let optionsHtml = '';
        <?php foreach ( $categories as $cat ) : ?>
            optionsHtml += '<option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?></option>';
        <?php endforeach; ?>
        
        let rowHtml = `<tr>
            <td><select name="lcm_cashback_multi_rules[${rowIndex}][category_slug]" class="lcm-input" style="width:100%; max-width:240px;">${optionsHtml}</select></td>
            <td><input type="text" name="lcm_cashback_multi_rules[${rowIndex}][min_spend]" value="0" class="lcm-input lcm-numeric-only" style="width:160px !important; text-align:center;"> تومان</td>
            <td><input type="text" name="lcm_cashback_multi_rules[${rowIndex}][reward]" value="0" class="lcm-input lcm-numeric-only" style="width:160px !important; text-align:center;"> تومان</td>
            <td style="text-align: center;"><button type="button" class="button" style="background:#e5383b; color:#fff; border:none; border-radius:6px; padding:4px 8px; cursor:pointer;" onclick="jQuery(this).closest('tr').remove();">❌</button></td>
        </tr>`;
        
        $('#lcm-rules-tbody').append(rowHtml);
        rowIndex++;
    });

    // افزودن داینامیک کارت گروه تخفیف جدید
    let groupIndex = $('#lcm-groups-container .lcm-group-card').length;
    $('#lcm-add-group-card').on('click', function() {
        let catOptions = '';
        <?php foreach ( $categories as $cat ) : ?>
            catOptions += '<option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?></option>';
        <?php endforeach; ?>

        let prodOptions = '';
        <?php foreach ( $all_products as $prod ) : ?>
            prodOptions += '<option value="<?php echo esc_attr($prod->ID); ?>"><?php echo esc_js($prod->post_title); ?></option>';
        <?php endforeach; ?>

        let cardHtml = `<div class="lcm-group-card" style="background: rgba(22, 26, 29, 0.4); border: 1px solid rgba(255,255,255,0.08); border-right: 4px solid #2ec4b6; border-radius: 12px; padding: 18px; margin-bottom: 16px;">
            <div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:12px;">
                <div style="flex:2; min-width:180px;">
                    <label style="font-size:0.75rem; color:#aaa;">🏷️ عنوان نمایشی گروه</label>
                    <input type="text" name="lcm_discount_groups[${groupIndex}][label]" class="lcm-input" style="width:100%;" placeholder="مثلاً: 🎓 دانشجویی">
                </div>
                <div style="flex:1; min-width:140px;">
                    <label style="font-size:0.75rem; color:#aaa;">شناسه (انگلیسی، بدون فاصله)</label>
                    <input type="text" name="lcm_discount_groups[${groupIndex}][slug]" class="lcm-input" style="width:100%;" placeholder="student">
                </div>
                <div style="width:90px;">
                    <label style="font-size:0.75rem; color:#aaa;">رنگ</label>
                    <input type="color" name="lcm_discount_groups[${groupIndex}][color]" value="#2ec4b6" style="width:100%; height:36px; border-radius:8px; border:none; cursor:pointer;">
                </div>
                <div style="width:110px;">
                    <label style="font-size:0.75rem; color:#aaa;">درصد تخفیف</label>
                    <input type="number" min="0" max="100" name="lcm_discount_groups[${groupIndex}][percent]" value="0" class="lcm-input" style="width:100%;">
                </div>
                <div style="width:150px;">
                    <label style="font-size:0.75rem; color:#aaa;">🏆 حداقل خرید برای ورود خودکار</label>
                    <input type="number" min="0" name="lcm_discount_groups[${groupIndex}][min_spend]" value="0" class="lcm-input" style="width:100%;" placeholder="0 = فقط دستی">
                </div>
                <div style="align-self:flex-end;">
                    <button type="button" class="button" style="background:#e5383b; color:#fff; border:none; border-radius:6px; padding:8px 12px; cursor:pointer;" onclick="jQuery(this).closest('.lcm-group-card').remove();">❌ حذف گروه</button>
                </div>
            </div>
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
                <div style="flex:1; min-width:220px;">
                    <label style="font-size:0.75rem; color:#aaa;">📦 روی کدام دسته‌بندی‌ها اعمال شود؟</label>
                    <select name="lcm_discount_groups[${groupIndex}][categories][]" multiple class="lcm-select-multiple" style="width:100%; min-height:70px;">${catOptions}</select>
                </div>
                <div style="flex:1; min-width:220px;">
                    <label style="font-size:0.75rem; color:#aaa;">🍽️ یا فقط روی این محصولات مشخص</label>
                    <select name="lcm_discount_groups[${groupIndex}][products][]" multiple class="lcm-select-multiple" style="width:100%; min-height:70px;">${prodOptions}</select>
                </div>
            </div>
        </div>`;

        $('#lcm-groups-container').append(cardHtml);
        groupIndex++;
    });
});

function lcmSendBulkSMS(btnElement) {
    const group = document.getElementById('smsTargetGroup').value;
    const msg = document.getElementById('smsMessageText').value;
    const statusDiv = document.getElementById('smsStatusResponse');
    if(!msg.trim()) { alert('متن پیامک خالی است!'); return; }
    btnElement.innerText = '⏳'; btnElement.disabled = true;
    let formData = new FormData();
    formData.append('action', 'lcm_send_bulk_sms');
    formData.append('nonce', LCM_ADMIN_NONCE);
    formData.append('target_group', group);
    formData.append('message', msg);
    fetch(ajaxurl, { method: 'POST', body: formData })
    .then(res => res.json())
    .then(resData => {
        if(resData.success) { statusDiv.style.color = '#43e97b'; statusDiv.innerText = '✅ شلیک شد! تعداد: ' + resData.data.count; }
        else { statusDiv.style.color = '#e5383b'; statusDiv.innerText = '❌ خطا'; }
        btnElement.innerText = '🚀 شلیک کمپین پیامکی'; btnElement.disabled = false;
    });
}

function lcmEnableWalletEdit(userId) {
    const row = document.getElementById('lcm-user-row-' + userId);
    row.querySelector('.view-balance-mode').style.display = 'none';
    row.querySelector('.edit-balance-mode').style.display = 'block';
    row.querySelector('.btn-edit').style.display = 'none';
    row.querySelector('.btn-save').style.display = 'inline-block';
    row.querySelector('.btn-cancel').style.display = 'inline-block';
}

function lcmCancelWalletEdit(userId) {
    const row = document.getElementById('lcm-user-row-' + userId);
    row.querySelector('.view-balance-mode').style.display = 'block';
    row.querySelector('.edit-balance-mode').style.display = 'none';
    row.querySelector('.btn-edit').style.display = 'inline-block';
    row.querySelector('.btn-save').style.display = 'none';
    row.querySelector('.btn-cancel').style.display = 'none';
}

function lcmSaveWalletEdit(userId, btnElement) {
    const row = document.getElementById('lcm-user-row-' + userId);
    const newBalance = row.querySelector('.input-balance-edit').value;
    btnElement.innerText = '⏳'; btnElement.disabled = true;
    const wpAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    let formData = new FormData();
    formData.append('action', 'lcm_update_wallet_balance');
    formData.append('nonce', LCM_ADMIN_NONCE);
    formData.append('user_id', userId);
    formData.append('balance', newBalance);
    fetch(wpAjaxUrl, { method: 'POST', body: formData })
    .then(res => { if (!res.ok) { throw new Error('Network response was not ok'); } return res.json(); })
    .then(resData => {
        if (resData.success) {
            row.querySelector('.current-bal-text').innerText = Number(newBalance).toLocaleString('fa-IR');
            lcmCancelWalletEdit(userId);
        } else { alert('خطا فرانت: ' + (resData.data || 'نامشخص')); }
        btnElement.innerText = '💾 ثبت'; btnElement.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطای ارتباط با سرور لوکال!');
        btnElement.innerText = '💾 ثبت'; btnElement.disabled = false;
    });
}

// تغییر دستی گروه یک مشتری از جدول لیدها
jQuery(document).ready(function($) {
    $(document).on('click', '.lcm-manual-group-save', function() {
        const btn = $(this);
        const memberId = btn.data('member-id');
        const groupSlug = $(`.lcm-manual-group-select[data-member-id="${memberId}"]`).val();
        const wpAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

        btn.text('⏳').prop('disabled', true);

        let formData = new FormData();
        formData.append('action', 'lcm_admin_set_member_group');
        formData.append('nonce', LCM_ADMIN_NONCE);
        formData.append('member_id', memberId);
        formData.append('group_slug', groupSlug);

        fetch(wpAjaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                btn.text('ثبت').prop('disabled', false);
                if (res.success) {
                    btn.closest('tr').find('.lcm-badge-grp').text($(`.lcm-manual-group-select[data-member-id="${memberId}"] option:selected`).text());
                } else {
                    alert('خطا: ' + (res.data || 'ثبت نشد'));
                }
            })
            .catch(() => {
                btn.text('ثبت').prop('disabled', false);
                alert('خطا در ارتباط با سرور.');
            });
    });
});

function lcmExportToCSV() {
    let csv = []; let rows = document.querySelectorAll("#lcmClubTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) { row.push(cols[j].innerText.replace(/,/g, " ")); }
        csv.push(row.join(","));        
    }	
    let csvFile = new Blob(["\ufeff" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    let downloadLink = document.createElement("a"); downloadLink.download = "leads.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile); downloadLink.click();
}
</script>