<?php
/**
 * Plugin Name: WC Coupon Enhanced
 * Plugin URI:  https://github.com/jimmy-is-me/wc-coupon-enhanced
 * Description: WooCommerce 折價券強化：指定物流免運 + My Account 優惠券顯示
 * Version:     1.4.0
 * Author:      jimmy-is-me
 * License:     GPL2
 * Text Domain: wc-coupon-enhanced
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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

    $zones    = WC_Shipping_Zones::get_zones();
    $zone_0   = WC_Shipping_Zones::get_zone( 0 );
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
                'description' => '開啟後：勾選的物流方式將直接免運費，未勾選的物流則按照原本運費規則收費。',
                'desc_tip'    => false,
                'value'       => $enabled,
                'cbvalue'     => 'yes',
            ) );
            ?>
        </div>

        <div id="wccs_methods_block" class="options_group" style="<?php echo $enabled === 'yes' ? '' : 'display:none;'; ?>">

            <div class="wccs-notice">
                勾選的物流方式將<strong>直接免運費</strong>，未勾選的物流按照原本規則收費。
            </div>

            <?php
            $has_methods = false;
            foreach ( $zones as $zone_id => $zone_data ) :
                $methods = $zone_data['shipping_methods'];
                if ( empty( $methods ) ) continue;
                $has_methods = true;
            ?>
            <div class="wccs-zone-block">
                <div class="wccs-zone-title">🚚 <?php echo esc_html( $zone_data['zone_name'] ); ?></div>
                <?php foreach ( $methods as $method ) :
                    $key     = $method->id . ':' . $method->instance_id;
                    $checked = in_array( $key, $saved, true ) ? 'checked="checked"' : '';
                ?>
                <div class="wccs-method-row">
                    <label class="wccs-method-label">
                        <input type="checkbox"
                               name="_allowed_shipping_methods[]"
                               value="<?php echo esc_attr( $key ); ?>"
                               <?php echo $checked; ?>>
                        <span class="wccs-method-title"><?php echo esc_html( $method->get_title() ); ?></span>
                        <code class="wccs-method-key"><?php echo esc_html( $key ); ?></code>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if ( ! $has_methods ) : ?>
            <p class="wccs-no-methods">⚠️ 尚未設定任何運送方式，請先至 <strong>WooCommerce → 設定 → 運送</strong> 新增物流。</p>
            <?php endif; ?>

        </div>
    </div>

    <style>
    /* ── 指定免運 Tab 後台樣式（修正電腦版排版） ── */
    #shipping_restriction_coupon_data .wccs-notice {
        margin: 12px 12px 16px;
        padding: 10px 16px;
        background: #f0f6fc;
        border-left: 4px solid #2271b1;
        font-size: 13px;
        line-height: 1.6;
    }
    .wccs-zone-block {
        padding: 0 20px 16px;
        clear: both;
    }
    .wccs-zone-title {
        font-weight: bold;
        font-size: 13px;
        color: #23282d;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eee;
        clear: both;
    }
    .wccs-method-row {
        clear: both;
        width: 100%;
        margin-bottom: 8px;
        padding: 8px 12px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    /* 修正：覆蓋 WooCommerce 後台 label 的 float */
    .wccs-method-label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        float: none !important;
        width: 100% !important;
        font-weight: normal !important;
        cursor: pointer !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.5 !important;
    }
    .wccs-method-label input[type="checkbox"] {
        flex-shrink: 0 !important;
        float: none !important;
        margin: 0 !important;
        width: 16px !important;
        height: 16px !important;
        vertical-align: middle !important;
    }
    .wccs-method-title {
        flex: 1;
        font-size: 13px;
    }
    .wccs-method-key {
        font-size: 11px;
        color: #888;
        white-space: nowrap;
        background: #f5f5f5;
        padding: 1px 5px;
        border-radius: 3px;
    }
    .wccs-no-methods {
        padding: 10px 20px;
        color: #888;
        clear: both;
    }
    </style>

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
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return $rates;
    if ( ! WC()->cart ) return $rates;

    $applied_coupons = WC()->cart->get_applied_coupons();
    if ( empty( $applied_coupons ) ) return $rates;

    $forced_free_keys = array();

    foreach ( $applied_coupons as $code ) {
        $coupon  = new WC_Coupon( $code );
        $enabled = get_post_meta( $coupon->get_id(), '_wccs_enabled', true );
        if ( $enabled !== 'yes' ) continue;

        $allowed = get_post_meta( $coupon->get_id(), '_allowed_shipping_methods', true );
        if ( empty( $allowed ) || ! is_array( $allowed ) ) continue;

        foreach ( $allowed as $key ) {
            $forced_free_keys[] = $key;
        }
    }

    if ( empty( $forced_free_keys ) ) return $rates;

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
 * 二、My Account「我的優惠券」頁面
 *     - 只顯示該使用者有資格使用的優惠券（含 email 限制過濾）
 *     - 顯示優惠券使用條件
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
    if ( get_option( 'wcce_rewrite_flushed_140' ) !== 'yes' ) {
        flush_rewrite_rules();
        update_option( 'wcce_rewrite_flushed_140', 'yes' );
    }
}

add_action( 'woocommerce_account_my-coupons_endpoint', 'wcce_my_coupons_content' );
function wcce_my_coupons_content() {
    $user_id    = get_current_user_id();
    $user       = get_userdata( $user_id );
    $user_email = $user ? strtolower( $user->user_email ) : '';

    $coupon_posts = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    $my_coupons = array();

    foreach ( $coupon_posts as $post ) {
        $coupon = new WC_Coupon( $post->post_title );
        if ( $coupon->get_id() === 0 ) continue;

        // Email 限制：有設定時只顯示給指定使用者
        $restrictions = $coupon->get_email_restrictions();
        if ( ! empty( $restrictions ) ) {
            $match = false;
            foreach ( $restrictions as $e ) {
                if ( strtolower( trim( $e ) ) === $user_email ) {
                    $match = true;
                    break;
                }
            }
            if ( ! $match ) continue;
        }

        // 過期跳過
        $expiry = $coupon->get_date_expires();
        if ( $expiry && $expiry->getTimestamp() < time() ) continue;

        // 整體使用上限
        $usage_limit = $coupon->get_usage_limit();
        if ( $usage_limit > 0 && $coupon->get_usage_count() >= $usage_limit ) continue;

        // 個人使用上限
        $limit_per_user = $coupon->get_usage_limit_per_user();
        if ( $limit_per_user > 0 && ( $user_id || $user_email ) ) {
            $used_by    = $coupon->get_used_by();
            $used_count = 0;
            foreach ( $used_by as $u ) {
                if ( ( $user_id && (string) $u === (string) $user_id ) ||
                     ( $user_email && strtolower( $u ) === $user_email ) ) {
                    $used_count++;
                }
            }
            if ( $used_count >= $limit_per_user ) continue;
        }

        $my_coupons[] = $coupon;
    }

    // 組合條件說明
    function wcce_get_coupon_conditions( $coupon ) {
        $parts = array();

        $type   = $coupon->get_discount_type();
        $amount = $coupon->get_amount();

        if ( 'percent' === $type && $amount > 0 ) {
            $parts[] = '折扣 ' . $amount . '%';
        } elseif ( 'fixed_cart' === $type && $amount > 0 ) {
            $parts[] = '折抵 NT$' . number_format( $amount, 0 );
        } elseif ( 'fixed_product' === $type && $amount > 0 ) {
            $parts[] = '商品折抵 NT$' . number_format( $amount, 0 );
        }

        if ( $coupon->get_free_shipping() ) {
            $parts[] = '免運費';
        }

        $min = (float) $coupon->get_minimum_amount();
        if ( $min > 0 ) {
            $parts[] = '最低消費 NT$' . number_format( $min, 0 );
        }

        $max = (float) $coupon->get_maximum_amount();
        if ( $max > 0 ) {
            $parts[] = '最高消費 NT$' . number_format( $max, 0 );
        }

        $limit_per_user = $coupon->get_usage_limit_per_user();
        if ( $limit_per_user > 0 ) {
            $parts[] = '每人限用 ' . $limit_per_user . ' 次';
        }

        $individual = $coupon->get_individual_use();
        if ( $individual ) {
            $parts[] = '不可與其他折價券合併';
        }

        return $parts;
    }
    ?>

    <style>
    .wcce-my-coupons { margin: 0; padding: 0; list-style: none; }
    .wcce-my-coupons li {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px 14px;
        border-bottom: 1px solid #eee;
        gap: 12px;
    }
    .wcce-my-coupons li:last-child { border-bottom: none; }
    .wcce-coupon-wrap-box {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 4px;
    }
    .wcce-coupon-code {
        font-family: monospace;
        font-size: 16px;
        font-weight: bold;
        color: #23282d;
        letter-spacing: 1.5px;
    }
    .wcce-coupon-conditions {
        margin-top: 5px;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .wcce-coupon-conditions span {
        font-size: 11px;
        background: #f0f0f0;
        color: #555;
        padding: 2px 7px;
        border-radius: 3px;
        white-space: nowrap;
    }
    .wcce-coupon-expiry {
        font-size: 11px;
        color: #aaa;
        margin-top: 5px;
    }
    .wcce-coupon-desc {
        font-size: 12px;
        color: #888;
        margin-top: 3px;
    }
    .wcce-coupon-amount {
        font-size: 13px;
        color: #e65c00;
        font-weight: bold;
        white-space: nowrap;
        flex-shrink: 0;
        padding-top: 2px;
    }
    </style>

    <h3 style="font-size:16px; margin-bottom:16px;">🎟 我的優惠券</h3>

    <?php if ( empty( $my_coupons ) ) : ?>
        <p style="color:#888;">目前沒有屬於您的優惠券。</p>
    <?php else : ?>
        <div class="wcce-coupon-wrap-box">
            <ul class="wcce-my-coupons">
            <?php foreach ( $my_coupons as $coupon ) :
                $conditions = wcce_get_coupon_conditions( $coupon );
                $expiry     = $coupon->get_date_expires();
                $type       = $coupon->get_discount_type();
                $amount     = $coupon->get_amount();
            ?>
                <li>
                    <div>
                        <div class="wcce-coupon-code"><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></div>

                        <?php if ( $coupon->get_description() ) : ?>
                        <div class="wcce-coupon-desc"><?php echo esc_html( $coupon->get_description() ); ?></div>
                        <?php endif; ?>

                        <?php if ( ! empty( $conditions ) ) : ?>
                        <div class="wcce-coupon-conditions">
                            <?php foreach ( $conditions as $c ) : ?>
                            <span><?php echo esc_html( $c ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ( $expiry ) : ?>
                        <div class="wcce-coupon-expiry">有效期限：<?php echo esc_html( $expiry->date_i18n( 'Y/m/d' ) ); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="wcce-coupon-amount">
                        <?php
                        if ( 'percent' === $type && $amount > 0 ) {
                            echo esc_html( $amount ) . '% OFF';
                        } elseif ( 'fixed_cart' === $type && $amount > 0 ) {
                            echo 'NT$' . esc_html( number_format( $amount, 0 ) );
                        } elseif ( $coupon->get_free_shipping() ) {
                            echo '免運';
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
