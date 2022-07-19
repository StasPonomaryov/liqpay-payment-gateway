<?php
/*
 * Plugin Name: LiqPay for WooCommerce
 * Description: LiqPay payment gate plugin for Woocommerce
 * Version: 1.0
 * Author: Stas Ponomaryov
 */

 /**
  * Page of plugin on dashboard
  */
class LiqPayInMenu
{
  public $slug = 'admin.php?page=wc-settings&tab=checkout&section=liqpay';

  public function __construct()
  {
    add_action('admin_menu', array($this, 'register_admin_menu'));
  }

  public function register_admin_menu()
  {
    add_menu_page('LiqPay', 'LiqPay', 'manage_options', $this->slug, false, plugin_dir_url(__FILE__) . 'img/liqpay.png', 30);
  }
}
new LiqPayInMenu();

/**
 * Register methods of payment gateway class
 */
function add_liqpay_payment_method($methods)
{
  require_once(__DIR__ . '/includes/class-wc-payment-liqpay.php');
  $methods[] = 'WC_Gateway_Liqpay';

  return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_liqpay_payment_method');
