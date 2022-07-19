<?php
if (!defined('ABSPATH')) {
  exit;
}

/**
 * WC_Gateway_Liqpay Class.
 */
class WC_Gateway_Liqpay extends WC_Payment_Gateway
{
  public function __construct()
  {
    // Setup general properties
    $this->setup_properties();
    // Load the settings
    $this->init_form_fields();
    $this->init_settings();

    // Get settings
    $this->title = $this->get_option('title');
    $this->description = $this->get_option('description');
    $this->instructions = $this->get_option('instructions');
    $this->cancel_pay = $this->get_option('cancel_pay');
    $this->lang = $this->get_option('lang', 'ru');
    $this->enable_for_methods = $this->get_option('enable_for_methods', array());
    $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';
    
    // Update Gateways
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    // Extend 'Thank you' page with method thankyou_page()
    add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
    // Add IPN callback
    add_action( 'woocommerce_api_wc_gateway_liqpay', array( $this, 'callback' ) );
  }

  /**
   * Setup general properties for the Woocommerce gateway
   */
  protected function setup_properties()
  {
    $this->id = 'liqpay';
    $this->icon = apply_filters('woocommerce_cod_icon', '');
    $this->method_title = 'LiqPay';
    $this->method_description = 'Оплата картами Visa, MasterCard.';
    $this->has_fields = false;
  }

  /**
   * Initialise Gateway Settings Form Fields on plugin's page
   */
  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'label' => 'Увімкнути',
        'type' => 'checkbox',
        'description' => '',
        'default' => 'no',
      ),
      'title' => array(
        'title' => __('Title', 'woocommerce'),
        'type' => 'text',
        'description' => 'Платежі картами Visa, Mastercard',
        'default' => 'Оплата картою (LiqPay)',
        'desc_tip' => true,
      ),
      'description' => array(
        'title' => __('Description', 'woocommerce'),
        'type' => 'textarea',
        'description' => 'Оплата картами Visa, MasterCard.',
        'default' => 'Оплата картами Visa, MasterCard.',
        'desc_tip' => true,
      ),
      'instructions' => array(
        'title' => __('Instructions', 'woocommerce'),
        'type' => 'textarea',
        'description' => '',
        'default' => '',
        'desc_tip' => true,
      ),
      'public_key' => array(
        'title' => __('API public_key', 'woocommerce'),
        'type' => 'text',
        'description' => '',
        'default' => '',
        'desc_tip' => true,
        'placeholder' => '',
      ),
      'private_key' => array(
        'title' => __('API private_key', 'woocommerce'),
        'type' => 'text',
        'description' => '',
        'default' => '',
        'desc_tip' => true,
        'placeholder' => '',
      ),
      'cancel_pay' => array(
        'title' => __('Відміна платежу', 'woocommerce'),
        'type' => 'textarea',
        'description' => '',
        'default' => 'Вашу оплату прийнято!',
        'desc_tip' => true,
      ),
      'lang' => array(
        'title' => __('Мова інтерфейсу сайту LiqPay', 'woocommerce'),
        'type' => 'select',
        'default' => 'ru',
        'options' => array(
          'ru' => 'ru',
          'en' => 'en',
          'uk' => 'uk'
        )
      )
    );
  }

  /**
   * Get description for Liqpay API
   * @param $order_id
   * @return string
   */
  private function getDescription($order_id)
  {
    switch ($this->lang) {
      case 'ru':
        $description = 'Оплата заказа № ' . $order_id;
        break;
      case 'en':
        $description = 'Order payment # ' . $order_id;
        break;
      case 'uk':
        $description = 'Оплата замовлення № ' . $order_id;
        break;
      default:
        $description = 'Оплата заказа № ' . $order_id;
    }

    return $description;
  }

  /**
   * Process the payment and return the result.
   * Owerrides default method in WC_Payment_Gateway
   * @param int $order_id - Order ID.
   * @return array
   */
  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);

    // Clear cart
    WC()->cart->empty_cart();

    require_once(__DIR__ . '/classes/LiqPay.php');
    $LiqPay = new LiqPay($this->get_option('public_key'), $this->get_option('private_key'));
    
    // Get redirect link for payment by Liqpay API
    $url = $LiqPay->cnb_link(array(
      'version' => '3',
      'action' => 'pay',
      'amount' => $order->get_total(),
      'currency' => $order->get_currency(),
      'description' => $this->getDescription($order->get_id()),
      'order_id' => $order->get_id(),
      'result_url' => $this->get_return_url($order),
      'server_url' => WC()->api_request_url('WC_Payment_Gateway_Liqpay'),
      'language' => $this->get_option('lang'),
      'sandbox' => '1'
    ));

    return array(
      'result' => 'success',
      'redirect' => $url,
    );
  }

  /**
   * IPN callback method
   */
  public function callback()
  {
    $post = file_get_contents("php://input");
    $parts = parse_url($post);
    // Get array of args from POST request
    parse_str($parts['query'], $query);
    if (isset($query['data'])) {
      $private_key = $this->get_option('private_key');
      $data        = $query['data'];
      $signature   = $query['signature'];
      $sign_check  = base64_encode(sha1($private_key . $data . $private_key, 1));
      $parsed_data = json_decode(base64_decode($data), true);

      $liqpay_order_success_status = 'processing';
      $liqpay_order_failure_status = 'canceled';

      $status = ($parsed_data['status'] == 'success' || $parsed_data['status'] == 'sandbox') ? $liqpay_order_success_status : $liqpay_order_failure_status;

      if ($sign_check == $signature) {
        $order = wc_get_order($parsed_data['order_id']);
        $order->update_status('pending', $status);
      }
    }
  }

  /**
   * Extend 'Thank you' page
   */
  public function thankyou_page($order_id)
  {
    sleep(5);
    $order = wc_get_order($order_id);

    // Show trouble or cancel payment message
    if (!$order->has_status('processing') && $this->cancel_pay) {
      echo wp_kses_post(wpautop(wptexturize($this->cancel_pay)));
    }
    // Show instructions message
    if ($this->instructions) {
      echo wp_kses_post(wpautop(wptexturize($this->instructions)));
    }
  }
}
