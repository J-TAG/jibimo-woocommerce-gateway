<?php

defined('ABSPATH') or exit;

function loadJibimoGateway()
{

    if (class_exists('WC_Payment_Gateway') && !class_exists('JibimoWooCommerceGateway') && !function_exists('AddWoocommerceJibimoGateway')) {

        define('JIBIMO_BASE_URL_SANDBOX', "https://jibimo.com/sandbox/api/business/");
        define('JIBIMO_BASE_URL_PRODUCTION', "https://jibimo.com/api/business/");

        add_filter('woocommerce_payment_gateways', 'AddWoocommerceJibimoGateway');

        function AddWoocommerceJibimoGateway($methods)
        {
            $methods[] = 'JibimoWooCommerceGateway';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'addIranCurrency');

        function addIranCurrency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'addIranCurrencySymbol', 10, 2);

        function addIranCurrencySymbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class JibimoWooCommerceGateway extends WC_Payment_Gateway
        {

            public function __construct()
            {

                $this->id = 'JibimoWooCommerceGateway';
                $this->method_title = __('درگاه پرداخت جیبی‌مو', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت جیبی‌مو برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');

                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->apitoken = $this->settings['apitoken'];
                $this->gateway_type = $this->settings['gateway_type'];
                $this->icon_type = $this->settings['icon_type'];
                $this->privacy = $this->settings['privacy'];

                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                $icon_type = 'logo-vertical.png';
                if($this->icon_type != 'vertical') {
                    $icon_type = 'logo-horizontal.png';
                }

                $this->icon = apply_filters('JibimoWooCommerceGateway_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . "/assets/images/${icon_type}");

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }
                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'sendToJibimoGateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'returnFromJibimoGateway'));
            }

            function getJibimoUrl() {
                if($this->gateway_type == 'production') {
                    return JIBIMO_BASE_URL_PRODUCTION;
                }

                return JIBIMO_BASE_URL_SANDBOX;
            }

            public function admin_options()
            {
                parent::admin_options();
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('JibimoWooCommerceGateway_Config', array(
                        'base_confing' => array(
                            'title' => __('تنظیمات اولیه', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعال‌سازی/غیرفعال‌سازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعال‌سازی درگاه پرداخت جیبی‌مو', 'woocommerce'),
                            'description' => __('برای فعال‌سازی درگاه پرداخت جیبی‌مو باید این گزینه را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه پرداخت', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه پرداخت که در هنگام خرید به کاربر نمایش داده می‌شود', 'woocommerce'),
                            'default' => __('درگاه پرداخت جیبی‌مو', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در حین عملیات پرداخت به کاربر نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن با کلیه کارت‌های عضو شتاب و کیف پول جیبی‌مو‌', 'woocommerce')
                        ),
                        'account_confing' => array(
                            'title' => __('تنظیمات حساب جیبی‌مو', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'apitoken' => array(
                            'title' => __('API توکن', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('این کد امنیتی برای اهراز هویت شما در جیبی‌مو در پنل اختصاصی شما در دسترس است', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'gateway_type' => array(
                            'title' => __('نوع درگاه', 'woocommerce'),
                            'type' => 'select',
                            'description' => __('نوع درگاه متصل به سایت', 'woocommerce'),
                            'options' => array(
                                'production' => __('محیط واقعی', 'woocommerce'),
                                'sandbox' => __('محیط تست', 'woocommerce'),
                            ),
                            'desc_tip' => true
                        ),
                        'icon_type' => array(
                            'title' => __('نوع آیکن جیبی‌مو', 'woocommerce'),
                            'type' => 'select',
                            'description' => __('انتخاب آیکن جیبی‌مو که در هنگام خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'options' => array(
                                'horizontal' => __('افقی', 'woocommerce'),
                                'vertical' => __('عمودی', 'woocommerce'),
                            ),
                            'desc_tip' => true
                        ),
                        'privacy' => array(
                            'title' => __('محدوده‌ی نمایش تراکنش‌ها', 'woocommerce'),
                            'type' => 'select',
                            'description' => __('محدوده‌ی تراکنش‌های شما در برنامه جیبی‌مو', 'woocommerce'),
                            'options' => array(
                                'Public' => __('عمومی', 'woocommerce'),
                                'Friend' => __('دوستان', 'woocommerce'),
                                'Personal' => __('خصوصی', 'woocommerce'),
                            ),
                            'desc_tip' => true
                        ),
                        'payment_confing' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) جیبی‌مو استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما، خرید شما با موفقیت انجام شد.', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت جیبی‌مو ارسال می‌گردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            public function initiateJibimoRequest($token, $params)
            {
                try {
                    $handler = curl_init($this->getJibimoUrl() . 'request_transaction');
                    curl_setopt($handler, CURLOPT_USERAGENT, 'Jibimo Woocommerce Plugin v1.1');
                    curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($handler, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($handler, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: Bearer ' . $token,
                        'Content-Length: ' . strlen($params)
                    ));
                    $result = curl_exec($handler);
                    return json_decode($result, true);
                } catch (Exception $e) {
                    return false;
                }
            }

            public function verifyJibimoRequest($token, $transactionId)
            {
                try {
                    $curl = curl_init($this->getJibimoUrl() . 'request_transaction/' . $transactionId);
                    curl_setopt_array($curl, array(
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_USERAGENT => 'Jibimo Woocommerce Plugin v1.1',
                        CURLOPT_HTTPHEADER => array(
                            'Accept: application/json',
                            'Authorization: Bearer ' . $token,
                        )
                    ));

                    $result = curl_exec($curl);
                    $json = json_decode($result, true);
                    curl_close($curl);

                    if (isset($json["status"]) and $json["status"] == 'Accepted') {
                        // Transaction made successfully
                        return true;
                    } else {
                        // Something went wrong
                        return false;
                    }
                } catch (Exception $e) {
                    return false;
                }
            }

            public function sendRequestToJibimo($action, $token, $params)
            {
                try {
                    $handler = curl_init($this->getJibimoUrl() . $action);
                    curl_setopt($handler, CURLOPT_USERAGENT, 'Jibimo Woocommerce Plugin v1.1');
                    curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($handler, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($handler, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Authorization: Bearer ' . $token,
                        'Content-Length: ' . strlen($params)
                    ));
                    $result = curl_exec($handler);
                    curl_close($handler);
                    return json_decode($result, true);
                } catch (Exception $e) {
                    return false;
                }
            }

            public function sendToJibimoGateway($order_id)
            {
                global $woocommerce;
                $woocommerce->session->order_id_jibimo = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_order_currency();
                $currency = apply_filters('JibimoWooCommerceGateway_Currency', $currency, $order_id);

                $form = '<form action="" method="POST" class="jibimo-checkout-form" id="jibimo-checkout-form">
						<input type="submit" name="jibimo_submit" class="button alt" id="jibimo-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('JibimoWooCommerceGateway_Form', $form, $order_id, $woocommerce);

                do_action('JibimoWooCommerceGateway_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('JibimoWooCommerceGateway_Gateway_After_Form', $order_id, $woocommerce);


                $amount = intval($order->order_total);
                $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $amount, $currency);
                if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                ) {
                    $amount *= 1;
                } else if (strtolower($currency) == strtolower('IRHT')) {
                    $amount *= 1000;
                } else if (strtolower($currency) == strtolower('IRHR')) {
                    $amount *= 100;
                } else if (strtolower($currency) == strtolower('IRR')) {
                    $amount /= 10;
                }

                $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $amount, $currency);
                $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $amount, $currency);
                $amount = apply_filters('woocommerce_order_amount_total_jibimo_gateway', $amount, $currency);

                $callbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('JibimoWooCommerceGateway'));

                $products = array();
                $order_items = $order->get_items();
                foreach ((array)$order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
                $mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '';

                // If woo commerce has not enabled billing, we can try to load mobile from wordpress
                if(empty(trim($mobile))) {
                    $mobile = get_user_meta(get_current_user_id(), 'phone_number', true);

                    if(empty(trim($mobile))) {
                        // Try one more time with different key :)
                        $mobile = get_user_meta(get_current_user_id(), 'phone', true);
                    }
                }
                $email = $order->billing_email;
                $payer = $order->billing_first_name . ' ' . $order->billing_last_name;
                $resNumber = intval($order->get_order_number());

                $description = apply_filters('JibimoWooCommerceGateway_Description', $description, $order_id);
                $mobile = apply_filters('JibimoWooCommerceGateway_Mobile', $mobile, $order_id);
                $email = apply_filters('JibimoWooCommerceGateway_Email', $email, $order_id);
                $payer = apply_filters('JibimoWooCommerceGateway_Paymenter', $payer, $order_id);
                $resNumber = apply_filters('JibimoWooCommerceGateway_ResNumber', $resNumber, $order_id);
                do_action('JibimoWooCommerceGateway_Gateway_Payment', $order_id, $description, $mobile);
                $email = !filter_var($email, FILTER_VALIDATE_EMAIL) === false ? $email : '';

                if(preg_match('/^09[0-9]{9}/i', $mobile)) {
                    // Remove extra zero, and add +98
                    $mobile = '+98' . substr($mobile, 1);
                } else if(preg_match('/^9[0-9]{9}/i', $mobile)) {
                    // Add +98 to mobile
                    $mobile = '+98' . $mobile;
                } else if(preg_match('/^\+989[0-9]{9}/i', $mobile)) {
                    // Mobile is correct, there is no need to touch it
                } else {
                    // Invalid mobile number
                    $mobile = '';
                }
                $privacy = $this->privacy;

                $data = array('mobile_number' => $mobile, 'amount' => $amount, 'privacy' => $privacy, 'description' => $description, 'tracker_id' => uniqid(), 'return_url' => $callbackUrl);

                $result = $this->initiateJibimoRequest($this->apitoken, json_encode($data));
                if ($result === false) {
                    echo "Payment error.";
                } else {
                    if (isset($result["redirect"])) {
                        // transaction successfully made
                        wp_redirect($result["redirect"]);
                        exit;
                    } else {
                        // There was an error
                        if (isset($result["error"])) {
                            $message = ' تراکنش با مشکل مواجه شده است. خطا: ' . $result["error"];
                            $fault = '';
                        } else if (isset($result["errors"])) {
                            $message = ' تراکنش با مشکل مواجه شده است. خطا: ';
                            foreach ($result["errors"] as $key => $value) {
                                if (is_array($value)) {
                                    foreach ($value as $error) {
                                        $message .= $error;
                                    }
                                }
                            }
                            $fault = '';
                        }
                    }
                }

                if (!empty($message) && $message) {

                    $note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $message);
                    $note = apply_filters('JibimoWooCommerceGateway_Send_to_Gateway_Failed_Note', $note, $order_id, $fault);
                    $order->add_order_note($note);

                    $notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $message);
                    $notice = apply_filters('JibimoWooCommerceGateway_Send_to_Gateway_Failed_Notice', $notice, $order_id, $fault);
                    if ($notice) {
                        wc_add_notice($notice, 'error');
                    }

                    do_action('JibimoWooCommerceGateway_Send_to_Gateway_Failed', $order_id, $fault);
                }
            }

            public function returnFromJibimoGateway()
            {
                global $woocommerce;

                $order_id = $woocommerce->session->order_id_jibimo;

                if (!isset($_POST['id']) or !isset($order_id)) {
                    $fault = __('شماره سفارش وجود ندارد.', 'woocommerce');
                    $notice = wpautop(wptexturize($this->failed_massage));
                    $notice = str_replace("{fault}", $fault, $notice);
                    $notice = apply_filters('JibimoWooCommerceGateway_Return_from_Gateway_No_Order_ID_Notice', $notice, $order_id, $fault);
                    if ($notice) {
                        wc_add_notice($notice, 'error');
                    }
                    do_action('JibimoWooCommerceGateway_Return_from_Gateway_No_Order_ID', $order_id, 0, $fault);

                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }

                // Catch transaction ID here
                $transactionId = $_POST['id'];
                $order = new WC_Order($order_id);

                if ($order->status != 'completed') {

                    if ($_POST['status'] == 'Accepted') {
                        $result = $this->verifyJibimoRequest($this->apitoken, $transactionId);

                        if ($result == true) {
                            $status = 'completed';
                            $fault = '';
                            $Message = '';
                        } else {
                            $status = 'failed';
                            $fault = '';
                            $Message = 'تراکنش ناموفق بود';
                        }
                    } else {
                        $status = 'failed';
                        $fault = '';
                        $Message = 'تراکنش انجام نشد.';
                    }

                    if ($status == 'completed' && isset($transactionId) && $transactionId != 0) {
                        update_post_meta($order_id, '_transaction_id', $transactionId);
                        $order->payment_complete($transactionId);
                        $woocommerce->cart->empty_cart();
                        $note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $transactionId);
                        $note = apply_filters('JibimoWooCommerceGateway_Return_from_Gateway_Success_Note', $note, $order_id, $transactionId);
                        if ($note) {
                            $order->add_order_note($note, 1);
                        }

                        $notice = wpautop(wptexturize($this->success_massage));
                        $notice = str_replace("{transaction_id}", $transactionId, $notice);
                        $notice = apply_filters('JibimoWooCommerceGateway_Return_from_Gateway_Success_Notice', $notice, $order_id, $transactionId);
                        if ($notice) {
                            wc_add_notice($notice, 'success');
                        }
                        do_action('JibimoWooCommerceGateway_Return_from_Gateway_Success', $order_id, $transactionId);

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;
                    } else {

                        $tr_id = ($transactionId && $transactionId != 0) ? ('<br/>شماره تراکنش : ' . $transactionId) : '';
                        $note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);
                        $note = apply_filters('JibimoWooCommerceGateway_Return_from_Gateway_Failed_Note', $note, $order_id, $transactionId, $fault);
                        if ($note) {
                            $order->add_order_note($note, 1);
                        }

                        $notice = wpautop(wptexturize($this->failed_massage));
                        $notice = str_replace("{transaction_id}", $transactionId, $notice);
                        $notice = str_replace("{fault}", $Message, $notice);
                        $notice = apply_filters('JibimoWooCommerceGateway_Return_from_Gateway_Failed_Notice', $notice, $order_id, $transactionId, $fault);
                        if ($notice) {
                            wc_add_notice($notice, 'error');
                        }
                        do_action('JibimoWooCommerceGateway_Return_from_Gateway_Failed', $order_id, $transactionId, $fault);
                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }
                } else {

                    $transactionId = get_post_meta($order_id, '_transaction_id', true);
                    $notice = wpautop(wptexturize($this->success_massage));
                    $notice = str_replace("{transaction_id}", $transactionId, $notice);
                    $notice = apply_filters('JibimoWooCommerceGateway_Return_from_Gateway_ReSuccess_Notice', $notice, $order_id, $transactionId);
                    if ($notice) {
                        wc_add_notice($notice, 'success');
                    }

                    do_action('JibimoWooCommerceGateway_Return_from_Gateway_ReSuccess', $order_id, $transactionId);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

            }

        }
    }
}

add_action('plugins_loaded', 'loadJibimoGateway', 0);
