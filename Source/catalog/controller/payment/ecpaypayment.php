<?php

namespace Opencart\Catalog\Controller\Extension\Ecpay\Payment;

use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\VerifiedArrayResponse;

class Ecpaypayment extends \Opencart\System\Engine\Controller {
    private $separator                   = '';
    private $module_name                 = 'ecpaypayment';
    private $lang_prefix                 = '';
    private $module_path                 = '';
    private $id_prefix                   = '';
    private $setting_prefix              = '';
    private $model_name                  = '';
    private $name_prefix                 = '';
    private $chosen_payment_session_name = 'chosen_payment';
    private $helper                      = null;
    private $url_secure                  = true;

    // Invoice
    private $invoice_module_name    = 'ecpayinvoice';
    private $invoice_setting_prefix = '';

    // Logistic
    private $logistic_module_name    = 'ecpaylogistic';
    private $logistic_module_path    = '';
    private $logistic_setting_prefix = '';

    // Constructor
    public function __construct($registry) {
        parent::__construct($registry);

        if (VERSION >= '4.0.2.0') {
            $this->separator = '.';
        } else {
            $this->separator = '|';
        }

        // Set the variables

        // payment
        $this->lang_prefix    = $this->module_name . '_';
        $this->id_prefix      = 'payment-' . $this->module_name;
        $this->setting_prefix = 'payment_' . $this->module_name . '_';
        $this->module_path    = 'extension/ecpay/payment/' . $this->module_name;
        $this->model_name     = 'model_extension_ecpay_payment_' . $this->module_name;
        $this->name_prefix    = 'payment_' . $this->module_name;

        $this->load->model($this->module_path);

        // load helper
        require_once DIR_EXTENSION . 'ecpay/system/library/EcpayPaymentHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayPaymentHelper;

        // invoice
        $this->invoice_setting_prefix = 'module_' . $this->invoice_module_name . '_';

        // logistic
        $this->logistic_module_path    = 'extension/ecpay/shipping/' . $this->logistic_module_name;
        $this->logistic_setting_prefix = 'shipping_' . $this->logistic_module_name . '_';
    }

    /**
     * 結帳選完 Payment 後執行
     */
    public function index() {
        $this->load->language($this->module_path);

        $data['language'] = $this->config->get('config_language');

        $data['text_checkout_button'] = $this->language->get($this->lang_prefix . 'text_checkout_button');
        $data['text_title']           = $this->language->get($this->lang_prefix . 'text_title');
        $data['entry_payment_method'] = $this->language->get($this->lang_prefix . 'entry_payment_method');

        if (isset($this->session->data[$this->module_name][$this->chosen_payment_session_name]) === true) {
            $chosen_payment         = $this->session->data[$this->module_name][$this->chosen_payment_session_name];
            $data['chosen_payemnt'] = $this->language->get($this->lang_prefix . 'text_' . $chosen_payment);
        } else {
            $data['chosen_payemnt'] = '';
        }

        // 設定 view 參數
        $data['id_prefix']    = $this->id_prefix;
        $data['module_name']  = $this->module_name;
        $data['name_prefix']  = $this->name_prefix;
        $data['redirect_url'] = $this->url->link(
            $this->module_path . '.redirect',
            '',
            $this->url_secure
        );

        $view_data_name = $this->module_name . '_' . 'payment_methods';

        // 取得付款方式
        $ecpay_payment_methods = $this->config->get($this->setting_prefix . 'payment_methods');
        if (empty($ecpay_payment_methods) === true) {
            $ecpay_payment_methods = array();
        } else {
            foreach ($ecpay_payment_methods as $name) {
                $lower_name                         = strtolower($name);
                $lang_key                           = $this->lang_prefix . 'text_' . $lower_name;
                $data[$view_data_name][$lower_name] = $this->language->get($lang_key);
                unset($lang_key, $lower_name);
            }
        }

        // 物流
        if ($this->config->get($this->logistic_setting_prefix . 'status') && isset($this->session->data['shipping_method'])) {
            // 判斷是否為綠界物流
            $delivery_method = array(
                'ecpaylogistic.unimart_collection',
                'ecpaylogistic.fami_collection',
                'ecpaylogistic.hilife_collection',
                'ecpaylogistic.okmart_collection',
                'ecpaylogistic.unimart',
                'ecpaylogistic.fami',
                'ecpaylogistic.hilife',
                'ecpaylogistic.okmart',
                'ecpaylogistic.tcat',
                'ecpaylogistic.post',
            );

            if (in_array($this->session->data['shipping_method']['code'], $delivery_method)) {
                // 轉導至門市選擇
                $data['redirect_url'] = $this->url->link(
                    $this->logistic_module_path . $this->separator . 'express_map',
                    '',
                    $this->url_secure
                );
            }
        }

        // Load the template
        $view_path = $this->module_path;
        return $this->load->view($this->module_path, $data);
    }

    /**
     * confirm
     *
     * @return json|string
     */
    public function confirm() {
        // loading example payment language
        $this->load->language($this->module_path);

        $json = [];
        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != $this->module_name . '.credit') {
            $json['error'] = $this->language->get('error_payment_method');

        }
        if (!$json) {
            $this->load->model('checkout/order');
            $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get($this->setting_prefix . 'order_status_id'));
            $json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // Redirect to AIO
    public function redirect() {
        try {
            // Load translation
            $this->load->language($this->module_path);

            // Check choose payment
            $payment_methods      = $this->config->get($this->setting_prefix . 'payment_methods');
            $choose_payment_array = explode('.', $this->session->data['payment_method']['code']);

            // Validate choose payment
            if ($choose_payment_array[0] == $this->module_name && in_array($choose_payment_array[1], $payment_methods) && isset($this->session->data['order_id'])) {
                $order_id = $this->session->data['order_id'];

                // Get the order info
                $this->load->model('checkout/order');
                $order       = $this->model_checkout_order->getOrder($order_id);
                $order_total = $order['total'];

                // Update order status and comments
                $comment   = $this->language->get($this->lang_prefix . 'text_' . $choose_payment_array[1]);
                $status_id = $this->config->get($this->setting_prefix . 'create_status');
                $this->model_checkout_order->addHistory($order_id, $status_id, $comment, true, false);

                // 儲存訂單商品重量
                $weight = $this->cart->getWeight();
                $this->{$this->model_name}->insertEcpayOrderExtend($order_id, ['goodsWeight' => $weight]);

                // Clear the cart
                $this->cart->clear();

                // Add to activity log
                $this->load->model('account/activity');
                if (empty($this->customer->isLogged()) === false) {
                    $activity_key  = 'order_account';
                    $activity_data = array(
                        'customer_id' => $this->customer->getId(),
                        'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                        'order_id'    => $order_id,
                    );
                } else {
                    $activity_key  = 'order_guest';
                    $guest         = $this->session->data['customer'];
                    $activity_data = array(
                        'name'     => $guest['firstname'] . ' ' . $guest['lastname'],
                        'order_id' => $order_id,
                    );
                }
                $this->model_account_activity->addActivity($activity_key, $activity_data);

                // Clean the session
                $session_list = array(
                    'shipping_method',
                    'shipping_methods',
                    'payment_method',
                    'payment_methods',
                    'guest',
                    'comment',
                    'order_id',
                    'coupon',
                    'reward',
                    'voucher',
                    'vouchers',
                    'totals',
                    'error',
                    'ecpayinvoice',
                );
                foreach ($session_list as $name) {
                    unset($this->session->data[$name]);
                }

                $factory = new Factory([
                    'hashKey' => $this->config->get($this->setting_prefix . 'hash_key'),
                    'hashIv'  => $this->config->get($this->setting_prefix . 'hash_iv'),
                ]);

                $autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
                $apiPaymentInfo        = $this->helper->get_ecpay_payment_api_info('AioCheckOut', $this->config->get($this->setting_prefix . 'merchant_id'));

                // 取得 SDK ChoosePayment
                $sdkPayment = $this->helper->getSdkPayment($choose_payment_array[1]);

                // 組合送往 AIO 參數
                $input = array(
                    'MerchantID'        => $this->config->get($this->setting_prefix . 'merchant_id'),
                    'MerchantTradeNo'   => $this->helper->getMerchantTradeNo($order_id),
                    'MerchantTradeDate' => date('Y/m/d H:i:s'),
                    'PaymentType'       => 'aio',
                    'TotalAmount'       => (int) $order_total,
                    'TradeDesc'         => 'opencart4x',
                    'ItemName'          => $this->language->get($this->lang_prefix . 'text_item_name'),
                    'ChoosePayment'     => $sdkPayment,
                    'EncryptType'       => 1,
                    'ReturnURL'         => $this->url->link($this->module_path . '|response', '', true),
                    'ClientBackURL'     => $this->url->link('checkout/success'),
                    'PaymentInfoURL'    => $this->url->link($this->module_path . '|response', '', true),
                    'NeedExtraPaidInfo' => 'Y',
                );

                // 取得額外參數
                $input = $this->helper->add_type_info($input, $choose_payment_array[1]);

                $generateForm = $autoSubmitFormService->generate($input, $apiPaymentInfo['action']);
                echo $generateForm;
            } else {
                $this->session->data['error'] = 'Payment method verification failed.';
                $this->response->redirect($this->url->link('checkout/checkout', '', $this->url_secure));
            }
        } catch (RtnException $e) {
            // Process the exception
            $this->session->data['error'] = $e->getMessage();
            $this->response->redirect($this->url->link('checkout/checkout', '', $this->url_secure));
        }
    }

    // Process AIO response
    public function response() {
        // Load the model and translation
        $this->load->language($this->module_path);
        $this->load->model('checkout/order');

        // Set the default result message
        $result_message = '1|OK';
        $order_id       = null;
        $order          = null;

        try {
            $factory = new Factory([
                'hashKey' => $this->config->get($this->setting_prefix . 'hash_key'),
                'hashIv'  => $this->config->get($this->setting_prefix . 'hash_iv'),
            ]);

            $checkoutResponse = $factory->create(VerifiedArrayResponse::class);
            $info             = $checkoutResponse->get($_POST);

            $order_id = $this->helper->getOrderIdByMerchantTradeNo($info);

            // Get the cart order info
            $order            = $this->model_checkout_order->getOrder($order_id);
            $order_status_id  = $order['order_status_id'];
            $create_status_id = $this->config->get($this->setting_prefix . 'create_status');
            $order_total      = $order['total'];

            // Check the amounts
            if (round($info['TradeAmt'], 0) == round($order_total, 0)) {
                if (($info['SimulatePaid'] ?? '') == 1) {
                    // Simulate paid
                    $status_id = $order_status_id;
                    $comment   = $this->language->get($this->lang_prefix . 'text_simulate_paid');
                    $this->model_checkout_order->addHistory($order_id, $status_id, $comment, false, false);
                    unset($status_id, $comment);
                } else {
                    // Update the order status
                    switch ($info['RtnCode']) {
                    // Paid
                    case 1:
                        $status_id = $this->config->get($this->setting_prefix . 'success_status');
                        $pattern   = $this->language->get($this->lang_prefix . 'text_payment_result_comment');
                        $comment   = $this->helper->getComment($pattern, $info);
                        $this->model_checkout_order->addHistory($order_id, $status_id, $comment, true, false);
                        unset($status_id, $pattern, $comment);

                        // Save AIO response
                        $result = $this->{$this->model_name}->saveResponse($order_id, $info);

                        // Check E-Invoice model
                        $ecpay_invoice_status = $this->config->get($this->invoice_setting_prefix . 'status');

                        // Get E-Invoice model name
                        $invoice_module_name    = '';
                        $invoice_setting_prefix = '';

                        if ($ecpay_invoice_status === '1') {
                            $invoice_module_name    = $this->invoice_module_name;
                            $invoice_setting_prefix = $this->invoice_setting_prefix;
                        }

                        // E-Invoice auto issuel
                        if ($invoice_module_name !== '') {

                            // 載入電子發票 Model
                            $invoice_model_name  = 'model_extension_ecpay_module_' . $invoice_module_name;
                            $invoice_module_path = 'extension/ecpay/module/' . $invoice_module_name;
                            $this->load->model($invoice_module_path);

                            // 取得自動開立設定值
                            $invoice_autoissue = $this->config->get($invoice_setting_prefix . 'autoissue');

                            if ($invoice_autoissue === '1') {
                                $this->{$invoice_model_name}->createInvoiceNo($order_id);
                            }
                        }
                        break;

                    // Get code 2:ATM/BNPL 10100073:CVS 10100073:BARCODE
                    case 2:
                    case 10100073:
                    case 10100073:
                        $status_id    = $order_status_id;
                        $payment_type = explode('_', $info['PaymentType']);
                        $pattern      = $this->language->get($this->lang_prefix . 'text_' . strtolower($payment_type[0]) . '_comment');
                        $comment      = $this->helper->getComment($pattern, $info, 1);
                        $this->model_checkout_order->addHistory($order_id, $status_id, $comment, true, false);
                        unset($status_id, $pattern, $comment);
                        break;

                    // State error
                    default:
                        if ($this->{$this->model_name}->isResponsed($order_id) === false) {
                            // Update payment result
                            $status_id = $this->config->get($this->setting_prefix . 'failed_status');
                            $pattern   = $this->language->get($this->lang_prefix . 'text_payment_result_comment');
                            $comment   = $this->helper->getComment($pattern, $info);
                            $this->model_checkout_order->addHistory($order_id, $status_id, $comment, true, false);

                            // Save AIO response
                            $result = $this->{$this->model_name}->saveResponse($order_id, $info);
                        }
                        break;
                    }
                }
            }

        } catch (Exception $e) {
            $error = $e->getMessage();
            if (!is_null($order_id)) {
                $status_id = $this->config->get($this->setting_prefix . 'failed_status');
                $pattern   = $this->language->get($this->lang_prefix . 'text_failure_comment');
                $comment   = sprintf(
                    $pattern,
                    $info['PaymentType'],
                    $info['RtnCode'],
                    $info['RtnMsg']
                );
                $this->model_checkout_order->addHistory($order_id, $status_id, $comment, true, false);

                unset($status_id, $pattern, $comment);
            }

            // Set the failure result
            $result_message = '0|' . $error;
        }

        $this->helper->echoAndExit($result_message);
    }
}
?>
