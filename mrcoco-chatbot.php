<?php
/**
 * Plugin Name:       MrCoco AI Chatbot
 * Description:       افزونه اتصال چت‌بات هوش مصنوعی MrCoco به وب‌سایت با قابلیت کنترل صفحات و استثناها.
 * Version:           1.0.0
 * Author:            MrCoco Team
 * Author URI:        https://mrcoco.ir
 * Text Domain:       mrcoco-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * تنظیمات پیش‌فرض افزونه
 */
function mrcoco_default_options() {
    return array(
        'enabled'         => 1,
        'allow_patterns'  => "/*",
        'ignore_patterns' => "/wp-admin/*\n/dashboard/*",
    );
}

/**
 * ثبت تنظیمات در هسته وردپرس
 */
function mrcoco_register_settings() {
    $defaults = mrcoco_default_options();

    foreach ( $defaults as $key => $val ) {
        if ( get_option( "mrcoco_$key", null ) === null ) {
            add_option( "mrcoco_$key", $val );
        }
    }

    register_setting( 'mrcoco_group', 'mrcoco_enabled', array(
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) { return $val ? 1 : 0; },
        'default'           => 1,
    ) );

    $sanitize_patterns = function( $val ) {
        $val   = (string) $val;
        $val   = str_replace( array("\r\n", "\r"), "\n", $val );
        $lines = array_filter( array_map( 'trim', explode( "\n", $val ) ) );
        return implode( "\n", array_unique( $lines ) );
    };

    register_setting( 'mrcoco_group', 'mrcoco_allow_patterns', array(
        'type'              => 'string',
        'sanitize_callback' => $sanitize_patterns,
        'default'           => '/*',
    ) );

    register_setting( 'mrcoco_group', 'mrcoco_ignore_patterns', array(
        'type'              => 'string',
        'sanitize_callback' => $sanitize_patterns,
        'default'           => "/wp-admin/*\n/dashboard/*",
    ) );
}
add_action( 'admin_init', 'mrcoco_register_settings' );

/**
 * ایجاد منو در پیشخوان وردپرس (زیرمجموعه تنظیمات)
 */
function mrcoco_add_admin_menu() {
    add_options_page(
        'تنظیمات چت‌بات MrCoco',
        'چت‌بات MrCoco',
        'manage_options',
        'mrcoco-chatbot',
        'mrcoco_settings_page_html'
    );
}
add_action( 'admin_menu', 'mrcoco_add_admin_menu' );

/**
 * ظاهر صفحه تنظیمات
 */
function mrcoco_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap" style="max-width: 800px; direction: rtl; text-align: right;">
        <h1>تنظیمات چت‌بات هوش مصنوعی MrCoco</h1>
        <p>وضعیت و صفحاتی که می‌خواهید ویجت چت در آن‌ها نمایش داده شود را مشخص کنید.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'mrcoco_group' ); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">وضعیت چت‌بات</th>
                    <td>
                        <?php $enabled = (int) get_option( 'mrcoco_enabled', 1 ); ?>
                        <label>
                            <input type="checkbox" name="mrcoco_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
                            فعال‌سازی چت‌بات در سایت
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="mrcoco_ignore_patterns">صفحات مستثنی (Ignore)</label></th>
                    <td>
                        <textarea id="mrcoco_ignore_patterns" name="mrcoco_ignore_patterns" rows="5" class="large-text code" style="direction: ltr;"><?php echo esc_textarea( get_option( 'mrcoco_ignore_patterns', "/wp-admin/*\n/dashboard/*" ) ); ?></textarea>
                        <p class="description">
                            آدرس صفحاتی که <strong>نمی‌خواهید</strong> چت‌بات در آن‌ها لود شود (هر خط یک آدرس).<br>
                            مثال: <code>/dashboard/*</code> یا <code>/cart</code> یا <code>/checkout</code>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="mrcoco_allow_patterns">صفحات مجاز (Allow)</label></th>
                    <td>
                        <textarea id="mrcoco_allow_patterns" name="mrcoco_allow_patterns" rows="4" class="large-text code" style="direction: ltr;"><?php echo esc_textarea( get_option( 'mrcoco_allow_patterns', '/*' ) ); ?></textarea>
                        <p class="description">
                            پیش‌فرض <code>/*</code> یعنی همه صفحات مجاز هستند مگر مواردی که در بخش استثناها وارد شده باشند.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'ذخیره تنظیمات' ); ?>
        </form>
    </div>
    <?php
}

/**
 * تبدیل الگوهای دارای ستاره (*) به فرمت Regex
 */
function mrcoco_pattern_to_regex( $pattern ) {
    if ( empty( $pattern ) ) {
        return '';
    }
    if ( $pattern[0] !== '/' ) {
        $pattern = '/' . $pattern;
    }
    $escaped = preg_quote( $pattern, '#' );
    $escaped = str_replace( '\*', '.*', $escaped );
    return '#^' . $escaped . '$#i';
}

/**
 * تطبیق آدرس URL با الگوها
 */
function mrcoco_matches_patterns( $path, $patterns_text ) {
    if ( empty( $patterns_text ) ) {
        return false;
    }
    $lines = array_filter( array_map( 'trim', preg_split( '/\R/u', $patterns_text ) ) );
    foreach ( $lines as $pattern ) {
        $regex = mrcoco_pattern_to_regex( $pattern );
        if ( $regex && preg_match( $regex, $path ) ) {
            return true;
        }
    }
    return false;
}

/**
 * شرط بررسی وضعیت نمایش اسکریپت
 */
function mrcoco_should_inject_script() {
    // 1. اگر در پیشخوان ادمین باشد، اجرا نشود
    if ( is_admin() ) {
        return false;
    }

    // 2. اگر کلاً غیرفعال شده باشد
    if ( ! (bool) get_option( 'mrcoco_enabled', 1 ) ) {
        return false;
    }

    // گرفتن مسیر صفحه فعلی (بدون پارامترهای query)
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
    $path = parse_url( $uri, PHP_URL_PATH );
    if ( empty( $path ) ) {
        $path = '/';
    }

    // 3. اگر مسیر در لیست Ignore باشد، نمایش نده
    $ignore = get_option( 'mrcoco_ignore_patterns', '' );
    if ( mrcoco_matches_patterns( $path, $ignore ) ) {
        return false;
    }

    // 4. بررسی لیست مجازها (Allow)
    $allow = get_option( 'mrcoco_allow_patterns', '/*' );
    return mrcoco_matches_patterns( $path, $allow );
}

/**
 * تزریق اسکریپت چت‌بات در انتهای صفحه (فوتر)
 */
function mrcoco_inject_chat_script() {
    if ( mrcoco_should_inject_script() ) {
        ?>
        <!-- MrCoco AI Chatbot -->
        <script src="https://app.mrcoco.ir/chatbot/coco-chat.js" defer></script>
        <!-- /MrCoco AI Chatbot -->
        <?php
    }
}
add_action( 'wp_footer', 'mrcoco_inject_chat_script', 99 );
