<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LCM_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_footer', array( $this, 'render_popup' ) );
    }

    private function is_target_page() {
        // --------------------------------------------------------
        // کلید مخفی توسعه‌دهنده: اجبار به نمایش پاپ‌آپ برای تست
        // --------------------------------------------------------
        if ( isset( $_GET['lcm_test'] ) && $_GET['lcm_test'] == '1' ) {
            return true;
        }

        $target_pages = get_option( 'lcm_target_pages', array() );
        
        if ( empty( $target_pages ) || ! is_array( $target_pages ) ) {
            return false;
        }

        // استانداردترین تابع وردپرس برای گرفتن آیدی دقیق صفحه فعلی
        $current_page_id = get_queried_object_id();

        // تبدیل تمام تنظیمات ذخیره شده به "عدد" تا با هم همخوان شوند
        $target_pages = array_map( 'intval', $target_pages );

        if ( in_array( $current_page_id, $target_pages, true ) ) {
            return true;
        }

        return false;
    }

    public function enqueue_scripts() {
        if ( ! $this->is_target_page() ) {
            return;
        }

        wp_enqueue_style( 'lcm-public-style', LCM_CLUB_PLUGIN_URL . 'assets/css/public-style.css', array(), '1.0.0' );
        wp_enqueue_script( 'lcm-public-script', LCM_CLUB_PLUGIN_URL . 'assets/js/public-script.js', array('jquery'), '1.0.0', true );

        wp_localize_script( 'lcm-public-script', 'lcmData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ) );
    }

    public function render_popup() {
        if ( ! $this->is_target_page() ) {
            return;
        }

        require_once LCM_CLUB_PLUGIN_DIR . 'public/views/view-popup.php';
    }
}

// اجرای کلاس
new LCM_Public();