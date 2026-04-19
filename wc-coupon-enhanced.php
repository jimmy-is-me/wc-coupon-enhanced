<?php
/**
 * Plugin Name: WC Coupon Enhanced
 * Plugin URI:  https://github.com/jimmy-is-me/wc-coupon-enhanced
 * Description: WooCommerce 折價券強化：指定物流免運、購物車/結帳顯示可用優惠券、我的帳戶優惠券頁面
 * Version:     1.0.0
 * Author:      jimmy-is-me
 * License:     GPL2
 * Text Domain: wc-coupon-enhanced
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCE_URL',  plugin_dir_url( __FILE__ ) );

// 載入樣式
add_action( 'wp_enqueue_scripts', 'wcce_enqueue_styles' );
function wcce_enqueue_styles() {
    wp_enqueue_style( 'wcce-style', WCCE_URL . 'assets/style.css', array(), '1.0.0' );
}

/* ============================================================
 * 一、折價券後台「指定物流」Tab
 * ============================================================ */

// 1. 新增 Tab
add_filter( 'woocommerce_coupon_data_tabs', 'wcce_add_shipping_tab' );
function wcce_add_shipping_tab( $tabs ) {
    $tabs['shipping_restriction'] = array(
        'label'  => '指定物流',
        'target' => 'shipping_restriction_coupon_data',
        'class'  => array(),
    );
    return $tabs;
}

// 2. Tab 面板內容
add_action( 'woocommerce_coupon_data_panels', 'wcce_shipping_tab_panel', 10, 2 );
function wcce_shipping_tab_panel( $coupon_id, $coupon ) {
    $saved = get_post_meta( $coupon_id, '_allowed_shipping_methods', true );
    if ( ! is_array( $saved ) ) $saved = array();

    // 取得所有運送區域與方式
    $zones   = WC_Shipping_Zones::get_zones();
    $zone_0  = WC_Shipping_Zones::get_zone(0);
    $zones[0] = array(
        'zone_name'        => '無地區 (預設)',
        'shipping_methods' => $zone_0->get_shipping_methods( true ),
    );

    ?>
    <div id="shipping_restriction_coupon_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <div class="wcce-notice">
                設定後，只有勾選的物流方式才能觸發此折價券的免運效果。<br>
                <strong>若不勾選任何項目，則所有物流皆可免運（維持原本行為）。</strong>
            </div>

            <?php
            $has = false;
            foreach ( $zones as $zone_id => $zone_data ) :
                $methods = $zone_data['shipping_methods'];
                if ( empty( $methods ) ) continue;
                $has = true;
            ?>
            <div class="wcce-zone-block">
                <div class="wcce-zone-title">🚚 <?php echo esc_html( $zone_data['zone_name'] ); ?></div>
                <?php foreach ( $methods as $method ) :
                    $key     = $method->id . ':' . $method->instance_id;
                    $checked = in_array( $key, $saved ) ? 'checked="checked"' : '';
                ?>
                <div class="wcce-method-row">
                    <label>
                        <input type="checkbox"
                               name="_allowed_shipping_methods[]"
                               value="<?php echo esc_attr( $key ); ?>"
                               <?php echo $checked; ?>>
                        <span class="wcce-method-title"><?php echo esc_html( $method->get_title() ); ?></span>
                        <code class="wcce-method-key"><?php echo esc_html( $key ); ?></code>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if ( ! $has ) : ?>
            <p class="wcce-no-methods">⚠️ 尚未設定任何運送方式，請先至 <strong>WooCommerce → 設定 → 運送</strong> 新增物流。</p>
            <?php endif; ?>
        </div>
    </div>

    <style>
    /* 後台 Tab 樣式 */
    #shipping_restriction_coupon_data .wcce-notice {
        margin: 12px 12px 20px;
        padding: 10px 16px;
        background: #f0f6fc;
        border-left: 4px solid #2271b1;
        font-size: 13px;
        line-height: 1.6;
    }
    .wcce-zone-block {
        padding: 0 20px 16px;
    }
    .wcce-zone-title {
        font-weight: bold;
        font-size: 13px;
        color: #23282d;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eee;
    }
    .wcce-method-row {
        display: block !important;
        width: 100% !important;
        float: none !important;
        clear: both !important;
        margin-bottom: 8px !important;
        padding: 8px 12px !important;
        background: #fff !important;
        border: 1px solid #ddd !important;
        border-radius: 4px !important;
        box-sizing: border-box !important;
        line-height: 1.5 !important;
    }
    .wcce-method-row label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        font-weight: normal !important;
        cursor: pointer !important;
        margin: 0 !important;
        width: 100% !important;
        float: none !important;
    }
    .wcce-method-row input[type="checkbox"] {
        flex-shrink: 0 !important;
        margin: 0 !important;
        width: auto !important;
    }
    .wcce-method-title {
        flex: 1;
    }
    .wcce-method-key {
        font-size: 11px;
        color: #888;
        white-space: nowrap;
    }
    .wcce-no-methods {
        padding: 10px 20px;
        color: #888;
    }
    </style>
    <?php
}

// 3. 儲存
add_action( 'woocommerce_coupon_options_save', 'wcce_save_shipping_tab', 10, 2 );
function wcce_save_shipping_tab( $coupon_id, $coupon ) {
    $methods = isset( $_POST['_allowed_shipping_methods'] )
        ? array_map( 'sanitize_text_field', (array) $_POST['_allowed_shipping_methods'] )
        : array();
    update_post_meta( $coupon_id, '_allowed_shipping_methods', $methods );
}

// 4. 結帳判斷：不符合指定物流則移除免運
add_filter( 'woocommerce_package_rates', 'wcce_restrict_free_shipping', 100, 2 );
function wcce_restrict_free_shipping( $rates, $package ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return $rates;
    $applied = WC()->cart->get_applied_coupons();
    if ( empty( $applied ) ) return $rates;

    foreach ( $applied as $code ) {
        $coupon = new WC_Coupon( $code );
        if ( ! $coupon->get_free_shipping() ) continue;

        $allowed = get_post_meta( $coupon->get_id(), '_allowed_shipping_methods', true );
        if ( empty( $allowed ) || ! is_array( $allowed ) ) continue;

        foreach ( $rates as $rate_id => $rate ) {
            if ( 'free_shipping' !== $rate->method_id ) continue;
            $key = $rate->method_id . ':' . $rate->instance_id;
            if ( ! in_array( $key, $allowed ) ) {
                unset( $rates[ $rate_id ] );
            }
        }
    }
    return $rates;
}


/* ============================================================
 * 二、購物車 & 結帳頁：使用優惠券輸入框下方顯示滿足條件的可用券
 * ============================================================ */

// 取得滿足購物車條件的優惠券
function wcce_get_eligible_coupons() {
    $user_id    = get_current_user_id();
    $user_email = '';
    if ( $user_id ) {
        $user       = get_userdata( $user_id );
        $user_email = $user->user_email;
    }

    $cart_subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0;
    $applied       = WC()->cart ? array_map( 'strtolower', WC()->cart->get_applied_coupons() ) : array();

    $all = get_posts( array(
        'post_type'      => 'shop_coupon',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );

    $eligible = array();

    foreach ( $all as $post ) {
        $coupon = new WC_Coupon( $post->post_title );

        // 過期
        $expiry = $coupon->get_date_expires();
        if ( $expiry && $expiry->getTimestamp() < time() ) continue;

        // 使用次數
        $usage_limit = $coupon->get_usage_limit();
        if ( $usage_limit > 0 && $coupon->get_usage_count() >= $usage_limit ) continue;

        // 每人次數
        $limit_per_user = $coupon->get_usage_limit_per_user();
        if ( $limit_per_user > 0 && ( $user_id || $user_email ) ) {
            $used_by    = $coupon->get_used_by();
            $used_count = 0;
            foreach ( $used_by as $u ) {
                if ( ( $user_id && $u == $user_id ) || ( $user_email && $u == $user_email ) ) $used_count++;
            }
            if ( $used_count >= $limit_per_user ) continue;
        }

        // Email 限制
        $email_restrictions = $coupon->get_email_restrictions();
        if ( ! empty( $email_restrictions ) ) {
            if ( ! $user_email ) continue;
            $match = false;
            foreach ( $email_restrictions as $e ) {
                if ( strtolower($e) === strtolower($user_email) ) { $match = true; break; }
            }
            if ( ! $match ) continue;
        }

        // 最低消費條件
        $min = (float) $coupon->get_minimum_amount();
        if ( $min > 0 && $cart_subtotal < $min ) continue;

        // 最高消費條件
        $max = (float) $coupon->get_maximum_amount();
        if ( $max > 0 && $cart_subtotal > $max ) continue;

        $eligible[] = array(
            'coupon'   => $coupon,
            'applied'  => in_array( strtolower( $coupon->get_code() ), $applied ),
        );
    }

    return $eligible;
}

// 輸出優惠券卡片 HTML
function wcce_render_coupon_card( $coupon, $is_applied = false, $show_copy = false ) {
    $code   = strtoupper( $coupon->get_code() );
    $expiry = $coupon->get_date_expires();
    $min    = $coupon->get_minimum_amount();
    $type   = $coupon->get_discount_type();
    $amount = $coupon->get_amount();
    $free   = $coupon->get_free_shipping();

    $parts = array();
    if ( 'percent' === $type && $amount > 0 )       $parts[] = '折扣 ' . $amount . '%';
    elseif ( 'fixed_cart' === $type && $amount > 0 ) $parts[] = '折抵 NT$' . number_format( $amount );
    elseif ( 'fixed_product' === $type && $amount > 0 ) $parts[] = '商品折抵 NT$' . number_format( $amount );
    if ( $free ) $parts[] = '免運費';
    if ( $min > 0 ) $parts[] = '最低消費 NT$' . number_format( $min );

    $border_color = $is_applied ? '#4CAF50' : '#e0e0e0';
    $status_html  = $is_applied
        ? '<span class="wcce-tag wcce-tag--applied">✅ 已套用</span>'
        : '<button class="wcce-apply-btn" data-coupon="' . esc_attr( strtolower($coupon->get_code()) ) . '">套用</button>';

    if ( $show_copy ) {
        $status_html = '<button class="wcce-copy-btn" data-coupon="' . esc_attr( strtolower($coupon->get_code()) ) . '">複製</button>';
    }

    ob_start();
    ?>
    <div class="wcce-card" style="border-color:<?php echo esc_attr( $border_color ); ?>">
        <div class="wcce-card__left <?php echo $is_applied ? 'wcce-card__left--applied' : ''; ?>">
            <span class="wcce-card__code"><?php echo esc_html( $code ); ?></span>
            <?php echo $status_html; ?>
        </div>
        <div class="wcce-card__right">
            <?php if ( ! empty( $parts ) ) : ?>
            <div class="wcce-card__desc"><?php echo esc_html( implode( '・', $parts ) ); ?></div>
            <?php endif; ?>
            <?php if ( $expiry ) : ?>
            <div class="wcce-card__expiry">有效期至 <?php echo $expiry->date('Y-m-d'); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 購物車頁：coupon 輸入框下方
add_action( 'woocommerce_cart_coupon', 'wcce_show_coupons_cart', 20 );
function wcce_show_coupons_cart() {
    $eligible = wcce_get_eligible_coupons();
    if ( empty( $eligible ) ) return;

    echo '<div class="wcce-coupon-list wcce-coupon-list--cart">';
    echo '<div class="wcce-coupon-list__title">🎟️ 可用優惠券</div>';
    foreach ( $eligible as $item ) {
        echo wcce_render_coupon_card( $item['coupon'], $item['applied'] );
    }
    echo '</div>';
    wcce_print_js();
}

// 結帳頁：coupon 輸入框下方
add_action( 'woocommerce_checkout_coupon_form', 'wcce_show_coupons_checkout', 20 );
function wcce_show_coupons_checkout() {
    $eligible = wcce_get_eligible_coupons();
    if ( empty( $eligible ) ) return;

    echo '<div class="wcce-coupon-list wcce-coupon-list--checkout">';
    echo '<div class="wcce-coupon-list__title">🎟️ 可用優惠券</div>';
    foreach ( $eligible as $item ) {
        echo wcce_render_coupon_card( $item['coupon'], $item['applied'] );
    }
    echo '</div>';
    wcce_print_js();
}

// JS 套用按鈕（只印一次）
$wcce_js_printed = false;
function wcce_print_js() {
    global $wcce_js_printed;
    if ( $wcce_js_printed ) return;
    $wcce_js_printed = true;
    ?>
    <script>
    jQuery(function($){
        // 套用按鈕
        $(document).on('click', '.wcce-apply-btn', function(){
            var code = $(this).data('coupon');
            var $input = $('input#coupon_code, input[name="coupon_code"]').first();
            $input.val(code);
            $input.closest('form').find('[name="apply_coupon"]').trigger('click');
        });
        // 複製按鈕
        $(document).on('click', '.wcce-copy-btn', function(){
            var code = $(this).data('coupon');
            var $btn = $(this);
            navigator.clipboard.writeText(code).then(function(){
                var orig = $btn.text();
                $btn.text('已複製！');
                setTimeout(function(){ $btn.text(orig); }, 2000);
            });
        });
    });
    </script>
    <?php
}


/* ============================================================
 * 三、我的帳戶「我的優惠券」頁面
 * ============================================================ */

// 3-1. 註冊頁籤
add_filter( 'woocommerce_account_menu_items', 'wcce_add_menu_item' );
function wcce_add_menu_item( $items ) {
    $new = array();
    foreach ( $items as $key => $label ) {
        $new[$key] = $label;
        if ( 'orders' === $key ) $new['my-coupons'] = '我的優惠券';
    }
    return $new;
}

// 3-2. 註冊 endpoint
add_action( 'init', 'wcce_register_endpoint' );
function wcce_register_endpoint() {
    add_rewrite_endpoint( 'my-coupons', EP_ROOT | EP_PAGES );
}

// 3-3. 刷新固定連結（只刷一次）
add_action( 'wp_loaded', 'wcce_maybe_flush' );
function wcce_maybe_flush() {
    if ( get_option('wcce_flushed') !== 'yes' ) {
        flush_rewrite_rules();
        update_option('wcce_flushed', 'yes');
    }
}

// 3-4. 頁面內容
add_action( 'woocommerce_account_my-coupons_endpoint', 'wcce_my_coupons_page' );
function wcce_my_coupons_page() {
    $user_id    = get_current_user_id();
    $user       = get_userdata( $user_id );
    $user_email = $user->user_email;

    $all = get_posts( array(
        'post_type'      => 'shop_coupon',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );

    $available = $used_list = $expired_list = array();

    foreach ( $all as $post ) {
        $coupon = new WC_Coupon( $post->post_title );

        // Email 限制
        $restrictions = $coupon->get_email_restrictions();
        if ( ! empty( $restrictions ) ) {
            $match = false;
            foreach ( $restrictions as $e ) {
                if ( strtolower($e) === strtolower($user_email) ) { $match = true; break; }
            }
            if ( ! $match ) continue;
        }

        $expiry     = $coupon->get_date_expires();
        $used_by    = $coupon->get_used_by();
        $used_count = 0;
        foreach ( $used_by as $u ) {
            if ( $u == $user_id || $u == $user_email ) $used_count++;
        }

        if ( $expiry && $expiry->getTimestamp() < time() ) {
            $expired_list[] = $coupon;
        } elseif ( $used_count > 0 ) {
            $used_list[] = $coupon;
        } else {
            $limit = $coupon->get_usage_limit_per_user();
            if ( $limit > 0 && $used_count >= $limit ) {
                $used_list[] = $coupon;
            } else {
                $available[] = $coupon;
            }
        }
    }

    echo '<h2>我的優惠券</h2>';

    // 可用
    echo '<div class="wcce-section">';
    echo '<div class="wcce-section__title">🎟️ 可用優惠券（' . count($available) . '）</div>';
    if ( empty($available) ) {
        echo '<p class="wcce-empty">目前沒有可用的優惠券。</p>';
    } else {
        foreach ( $available as $coupon ) {
            echo wcce_render_coupon_card( $coupon, false, true );
        }
    }
    echo '</div>';

    // 已使用
    echo '<div class="wcce-section">';
    echo '<div class="wcce-section__title">✅ 已使用（' . count($used_list) . '）</div>';
    if ( empty($used_list) ) {
        echo '<p class="wcce-empty">尚無已使用記錄。</p>';
    } else {
        foreach ( $used_list as $coupon ) {
            echo wcce_render_coupon_card( $coupon, true, false );
        }
    }
    echo '</div>';

    // 已過期
    echo '<div class="wcce-section">';
    echo '<div class="wcce-section__title">⛔ 已過期（' . count($expired_list) . '）</div>';
    if ( empty($expired_list) ) {
        echo '<p class="wcce-empty">尚無已過期優惠券。</p>';
    } else {
        foreach ( $expired_list as $coupon ) {
            // 過期券用灰色左欄
            $code   = strtoupper( $coupon->get_code() );
            $expiry = $coupon->get_date_expires();
            $min    = $coupon->get_minimum_amount();
            $type   = $coupon->get_discount_type();
            $amount = $coupon->get_amount();
            $free   = $coupon->get_free_shipping();
            $parts  = array();
            if ( 'percent' === $type && $amount > 0 )        $parts[] = '折扣 ' . $amount . '%';
            elseif ( 'fixed_cart' === $type && $amount > 0 )  $parts[] = '折抵 NT$' . number_format($amount);
            if ( $free ) $parts[] = '免運費';
            if ( $min > 0 ) $parts[] = '最低消費 NT$' . number_format($min);
            ?>
            <div class="wcce-card wcce-card--expired" style="border-color:#e0e0e0">
                <div class="wcce-card__left wcce-card__left--expired">
                    <span class="wcce-card__code"><?php echo esc_html($code); ?></span>
                    <span class="wcce-tag wcce-tag--expired">已過期</span>
                </div>
                <div class="wcce-card__right">
                    <?php if ( ! empty($parts) ) : ?>
                    <div class="wcce-card__desc"><?php echo esc_html(implode('・', $parts)); ?></div>
                    <?php endif; ?>
                    <?php if ( $expiry ) : ?>
                    <div class="wcce-card__expiry">過期時間：<?php echo $expiry->date('Y-m-d'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
    echo '</div>';

    // 輸出複製 JS
    wcce_print_js();
}
