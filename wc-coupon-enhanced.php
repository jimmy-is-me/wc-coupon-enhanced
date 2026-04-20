<?php
/**
 * Plugin Name: WC Coupon Enhanced
 * Plugin URI:  https://github.com/jimmy-is-me/wc-coupon-enhanced
 * Description: WooCommerce 折價券強化：指定物流免運 + My Account 優惠券顯示 + 購物車套用提示
 * Version:     1.3.0
 * Author:      jimmy-is-me
 * License:     GPL2
 * Text Domain: wc-coupon-enhanced
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCE_URL',  plugin_dir_url( __FILE__ ) );

add_action( 'wp_enqueue_scripts', 'wcce_enqueue_styles' );
function wcce_enqueue_styles() {
    wp_enqueue_style( 'wcce-style', WCCE_URL . 'assets/style.css', array(), '1.3.0' );
}

/* ============================================================
 * 一、折價券後台「指定免運」Tab
 * ============================================================ */

add_filter( 'woocommerce_coupon_data_tabs', 'wcce_add_shipping_tab' );
function wcce_add_shipping_tab( $tabs ) {
    $tabs['shipping_restriction'] = array(
        'label'  => '指定免運',
        'target' => 'shipping_restriction_coupon_data',
        'class'  => array(),
    );
    return $tabs;
}

add_action( 'woocommerce_coupon_data_panels', 'wcce_shipping_tab_panel', 10, 2 );
function wcce_shipping_tab_panel( $coupon_id, $coupon ) {
    $enabled = get_post_meta( $coupon_id, '_wccs_enabled', true );
    $saved   = get_post_meta( $coupon_id, '_allowed_shipping_methods', true );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    $zones  = WC_Shipping_Zones::get_zones();
    $zone_0 = WC_Shipping_Zones::get_zone( 0 );
    $zones[0] = array(
        'zone_name'        => '無地區 (預設)',
        'shipping_methods' => $zone_0->get_shipping_methods( true ),
    );
    ?>
    <div id="shipping_restriction_coupon_data" class="panel woocommerce_options_panel">

        <div class="options_group">
            <?php
            woocommerce_wp_checkbox( array(
                'id'          => '_wccs_enabled',
                'label'       => '啟用「指定免運」功能',
                'description' => '開啟後：勾選的物流方式將<strong>直接免運費</strong>，未勾選的物流則按照原本運費規則收費。',
                'desc_tip'    => false,
                'value'       => $enabled,
                'cbvalue'     => 'yes',
            ) );
            ?>
        </div>

        <div id="wccs_methods_block" class="options_group" style="<?php echo $enabled === 'yes' ? '' : 'display:none;'; ?>">

            <div style="margin:10px 12px 16px; padding:10px 16px; background:#f0f6fc; border-left:4px solid #2271b1; font-size:13px; line-height:1.6;">
                勾選的物流方式將<strong>直接免運費</strong>，未勾選的物流按照原本規則收費。
            </div>

            <?php
            $has_methods = false;
            foreach ( $zones as $zone_id => $zone_data ) :
                $methods = $zone_data['shipping_methods'];
                if ( empty( $methods ) ) continue;
                $has_methods = true;
            ?>
            <div style="padding:0 20px 16px; box-sizing:border-box;">
                <div style="font-weight:bold; font-size:13px; color:#23282d; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #eee;">
                    🚚 <?php echo esc_html( $zone_data['zone_name'] ); ?>
                </div>

                <?php foreach ( $methods as $method ) :
                    $key     = $method->id . ':' . $method->instance_id;
                    $checked = in_array( $key, $saved, true ) ? 'checked="checked"' : '';
                ?>
                <div style="display:block !important; width:100% !important; margin-bottom:8px !important; padding:8px 12px !important; background:#fff !important; border:1px solid #ddd !important; border-radius:4px !important; box-sizing:border-box !important;">
                    <label style="display:flex !important; align-items:center !important; gap:8px !important; font-weight:normal !important; cursor:pointer !important; margin:0 !important;">
                        <input type="checkbox"
                               name="_allowed_shipping_methods[]"
                               value="<?php echo esc_attr( $key ); ?>"
                               <?php echo $checked; ?>
                               style="flex-shrink:0 !important; margin:0 !important; width:auto !important;">
                        <span style="flex:1;"><?php echo esc_html( $method->get_title() ); ?></span>
                        <code style="font-size:11px; color:#888; white-space:nowrap;"><?php echo esc_html( $key ); ?></code>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if ( ! $has_methods ) : ?>
            <p style="padding:10px 20px; color:#888;">
                ⚠️ 尚未設定任何運送方式，請先至 <strong>WooCommerce → 設定 → 運送</strong> 新增物流。
            </p>
            <?php endif; ?>

        </div>
    </div>

    <script>
    jQuery(function($){
        $('#_wccs_enabled').on('change', function(){
            if ( $(this).is(':checked') ) {
                $('#wccs_methods_block').slideDown(200);
            } else {
                $('#wccs_methods_block').slideUp(200);
            }
        });
    });
    </script>
    <?php
}

add_action( 'woocommerce_coupon_options_save', 'wcce_save_shipping_tab', 10, 2 );
function wcce_save_shipping_tab( $coupon_id, $coupon ) {
    $enabled = ( isset( $_POST['_wccs_enabled'] ) && $_POST['_wccs_enabled'] === 'yes' ) ? 'yes' : '';
    update_post_meta( $coupon_id, '_wccs_enabled', $enabled );

    $methods = isset( $_POST['_allowed_shipping_methods'] )
        ? array_map( 'sanitize_text_field', (array) $_POST['_allowed_shipping_methods'] )
        : array();
    update_post_meta( $coupon_id, '_allowed_shipping_methods', $methods );
}

add_filter( 'woocommerce_package_rates', 'wcce_apply_free_shipping_by_method', 100, 2 );
function wcce_apply_free_shipping_by_method( $rates, $package ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return $rates;
    }

    if ( ! WC()->cart ) {
        return $rates;
    }

    $applied_coupons = WC()->cart->get_applied_coupons();
    if ( empty( $applied_coupons ) ) {
        return $rates;
    }

    $forced_free_keys = array();

    foreach ( $applied_coupons as $code ) {
        $coupon  = new WC_Coupon( $code );
        $enabled = get_post_meta( $coupon->get_id(), '_wccs_enabled', true );
        if ( $enabled !== 'yes' ) {
            continue;
        }

        $allowed = get_post_meta( $coupon->get_id(), '_allowed_shipping_methods', true );
        if ( empty( $allowed ) || ! is_array( $allowed ) ) {
            continue;
        }

        foreach ( $allowed as $key ) {
            $forced_free_keys[] = $key;
        }
    }

    if ( empty( $forced_free_keys ) ) {
        return $rates;
    }

    foreach ( $rates as $rate_id => $rate ) {
        $key_a = $rate->method_id . ':' . $rate->instance_id;
        $key_b = $rate_id;

        if ( in_array( $key_a, $forced_free_keys, true ) || in_array( $key_b, $forced_free_keys, true ) ) {
            $rates[ $rate_id ]->cost  = 0;
            $rates[ $rate_id ]->taxes = array();
            $rates[ $rate_id ]->label = $rate->label . '（免運）';
        }
    }

    return $rates;
}

/* ============================================================
 * 二、優惠券清單
 * ============================================================ */

function wcce_get_available_coupons() {
    $coupon_posts = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    $available = array();
    foreach ( $coupon_posts as $post ) {
        $coupon = new WC_Coupon( $post->post_title );
        if ( $coupon->get_id() === 0 ) {
            continue;
        }

        $expiry = $coupon->get_date_expires();
        if ( $expiry && $expiry->getTimestamp() < time() ) {
            continue;
        }

        $usage_limit = $coupon->get_usage_limit();
        if ( $usage_limit > 0 && $coupon->get_usage_count() >= $usage_limit ) {
            continue;
        }

        $available[] = $coupon;
    }

    return $available;
}

add_action( 'wp_footer', 'wcce_render_coupon_popup' );
function wcce_render_coupon_popup() {
    if ( is_cart() || is_checkout() ) {
        return;
    }

    $coupons = wcce_get_available_coupons();
    if ( empty( $coupons ) ) {
        return;
    }
    ?>
    <div id="wcce-popup-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99998;"></div>
    <div id="wcce-popup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:8px; padding:24px; min-width:320px; max-width:480px; z-index:99999; box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <button id="wcce-popup-close" style="position:absolute; top:10px; right:14px; background:none; border:none; font-size:20px; cursor:pointer; color:#888;">✕</button>
        <h3 style="margin:0 0 16px; font-size:16px;">🎟 可用優惠券</h3>
        <?php foreach ( $coupons as $coupon ) : ?>
        <div style="border:1px dashed #2271b1; border-radius:6px; padding:10px 14px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; background:#f0f6fc;">
            <div>
                <strong style="font-size:15px; letter-spacing:1px;"><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></strong>
                <div style="font-size:12px; color:#555; margin-top:2px;"><?php echo esc_html( $coupon->get_description() ); ?></div>
            </div>
            <span style="font-size:12px; background:#2271b1; color:#fff; padding:3px 8px; border-radius:4px; white-space:nowrap;">
                <?php
                if ( $coupon->get_discount_type() === 'percent' ) {
                    echo esc_html( $coupon->get_amount() ) . '% OFF';
                } else {
                    echo 'NT$' . esc_html( $coupon->get_amount() ) . ' 折抵';
                }
                ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
    jQuery(function($){
        $('#wcce-popup-close, #wcce-popup-overlay').on('click', function(){
            $('#wcce-popup, #wcce-popup-overlay').fadeOut(200);
        });
    });
    </script>
    <?php
}

add_action( 'woocommerce_cart_coupon', 'wcce_cart_coupon_button' );
function wcce_cart_coupon_button() {
    $coupons = wcce_get_available_coupons();
    if ( empty( $coupons ) ) {
        return;
    }
    ?>
    <button type="button" id="wcce-toggle-btn" style="background:none; border:none; color:#2271b1; font-size:13px; cursor:pointer; padding:0 8px; text-decoration:underline;">
        🏷 可用優惠券
    </button>

    <div id="wcce-inline-list" style="display:none; width:100%; margin-top:10px; background:#f8f9fa; border:1px solid #ddd; border-radius:6px; padding:10px 14px; box-sizing:border-box;">
        <?php foreach ( $coupons as $coupon ) : ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #eee;">
            <div>
                <strong style="font-size:14px; letter-spacing:1px; cursor:pointer; color:#2271b1;"
                        onclick="jQuery('#coupon_code').val('<?php echo esc_js( $coupon->get_code() ); ?>'); jQuery('[name=apply_coupon]').trigger('click'); jQuery('#wcce-inline-list').hide();">
                    <?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?>
                </strong>
                <?php if ( $coupon->get_description() ) : ?>
                <div style="font-size:12px; color:#666;"><?php echo esc_html( $coupon->get_description() ); ?></div>
                <?php endif; ?>
            </div>
            <span style="font-size:12px; color:#e65c00; font-weight:bold; white-space:nowrap; margin-left:12px;">
                <?php
                if ( $coupon->get_discount_type() === 'percent' ) {
                    echo esc_html( $coupon->get_amount() ) . '% OFF';
                } else {
                    echo '折抵 NT$' . esc_html( $coupon->get_amount() );
                }
                ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    jQuery(function($){
        $('#wcce-toggle-btn').on('click', function(){
            $('#wcce-inline-list').slideToggle(200);
        });
    });
    </script>
    <?php
}

/* ============================================================
 * 三、我的帳戶「我的優惠券」頁面
 * ============================================================ */

add_filter( 'woocommerce_account_menu_items', 'wcce_add_menu_item', 20 );
function wcce_add_menu_item( $items ) {
    $new_items = array();
    foreach ( $items as $key => $label ) {
        $new_items[ $key ] = $label;
        if ( 'orders' === $key ) {
            $new_items['my-coupons'] = '我的優惠券';
        }
    }

    if ( ! isset( $new_items['my-coupons'] ) ) {
        $new_items['my-coupons'] = '我的優惠券';
    }

    return $new_items;
}

add_action( 'init', 'wcce_add_coupons_endpoint' );
function wcce_add_coupons_endpoint() {
    add_rewrite_endpoint( 'my-coupons', EP_ROOT | EP_PAGES );
}

add_action( 'wp_loaded', 'wcce_maybe_flush_rewrite_rules' );
function wcce_maybe_flush_rewrite_rules() {
    if ( get_option( 'wcce_rewrite_flushed_130' ) !== 'yes' ) {
        flush_rewrite_rules();
        update_option( 'wcce_rewrite_flushed_130', 'yes' );
    }
}

add_action( 'woocommerce_account_my-coupons_endpoint', 'wcce_my_coupons_content' );
function wcce_my_coupons_content() {
    $coupons = wcce_get_available_coupons();
    ?>
    <style>
    .wcce-coupon-list-simple { list-style:none; margin:0; padding:0; }
    .wcce-coupon-list-simple li {
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:10px 14px;
        border-bottom:1px solid #eee;
        font-size:14px;
    }
    .wcce-coupon-list-simple li:last-child { border-bottom:none; }
    .wcce-coupon-code {
        font-family:monospace;
        font-size:15px;
        font-weight:bold;
        color:#23282d;
        letter-spacing:1px;
    }
    .wcce-coupon-desc { font-size:12px; color:#888; margin-top:2px; }
    .wcce-coupon-amount {
        font-size:13px;
        color:#e65c00;
        font-weight:bold;
        white-space:nowrap;
        margin-left:12px;
    }
    .wcce-coupon-expiry { font-size:11px; color:#aaa; margin-top:2px; }
    .wcce-coupon-wrap { background:#fff; border:1px solid #e0e0e0; border-radius:6px; overflow:hidden; }
    </style>

    <h3 style="font-size:16px; margin-bottom:16px;">🎟 可用優惠券</h3>

    <?php if ( empty( $coupons ) ) : ?>
        <p style="color:#888;">目前沒有可用的優惠券。</p>
    <?php else : ?>
        <div class="wcce-coupon-wrap">
            <ul class="wcce-coupon-list-simple">
            <?php foreach ( $coupons as $coupon ) : ?>
                <li>
                    <div>
                        <div class="wcce-coupon-code"><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></div>
                        <?php if ( $coupon->get_description() ) : ?>
                        <div class="wcce-coupon-desc"><?php echo esc_html( $coupon->get_description() ); ?></div>
                        <?php endif; ?>
                        <?php $expiry = $coupon->get_date_expires(); ?>
                        <?php if ( $expiry ) : ?>
                        <div class="wcce-coupon-expiry">有效期限：<?php echo esc_html( $expiry->date_i18n( 'Y/m/d' ) ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="wcce-coupon-amount">
                        <?php
                        if ( $coupon->get_discount_type() === 'percent' ) {
                            echo esc_html( $coupon->get_amount() ) . '% OFF';
                        } else {
                            echo '折抵 NT$' . esc_html( $coupon->get_amount() );
                        }
                        ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <p style="font-size:12px; color:#aaa; margin-top:10px;">* 至購物車結帳時輸入優惠券代碼即可套用。</p>
    <?php endif; ?>
    <?php
}
