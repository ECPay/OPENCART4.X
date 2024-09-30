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

                // 商品重量、金流測試模式
                $weight = $this->cart->getWeight();
                $payment_test_mode = $this->config->get($this->setting_prefix . 'test_mode');
                $extra_order_data = [
                    'goodsWeight' => $weight, 
                ];
                
                // 儲存訂單額外資訊
                $this->{$this->model_name}->insertEcpayOrderExtend($order_id, $extra_order_data);

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

                $apiPaymentInfo        = $this->helper->get_ecpay_payment_api_info('AioCheckOut', $payment_test_mode);

                $factory = new Factory([
                    'hashKey' => $apiPaymentInfo['hashKey'],
                    'hashIv'  => $apiPaymentInfo['hashIv'],
                ]);
                $autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

                // 取得 SDK ChoosePayment
                $sdkPayment = $this->helper->getSdkPayment($choose_payment_array[1]);

                // 組合送往 AIO 參數
                $input = array(
                    'MerchantID'        => $apiPaymentInfo['merchantId'],
                    'MerchantTradeNo'   => $this->helper->getMerchantTradeNo($order_id),
                    'MerchantTradeDate' => date('Y/m/d H:i:s'),
                    'PaymentType'       => 'aio',
                    'TotalAmount'       => (int) $order_total,
                    'TradeDesc'         => 'opencart4x',
                    'ItemName'          => $this->language->get($this->lang_prefix . 'text_item_name'),
                    'ChoosePayment'     => $sdkPayment,
                    'EncryptType'       => 1,
                    'ReturnURL'         => $this->url->link($this->module_path . '|response', '', true),
                    'ClientBackURL'     => $this->url->link($this->module_path . '|client_back', 'order_id=' . $order_id, true),
                    'PaymentInfoURL'    => $this->url->link($this->module_path . '|response', '', true),
                    'NeedExtraPaidInfo' => 'Y',
                );

                // 取得額外參數
                if ($choose_payment_array[1] == 'dca') {
                    $input['PeriodReturnURL'] = $this->url->link($this->module_path . '|response', '', true);
                    $input['Frequency'] = $this->config->get($this->setting_prefix . 'dca_frequency');
                    $input['ExecTimes'] = $this->config->get($this->setting_prefix . 'dca_exec_times');
                    $input['PeriodType'] = $this->config->get($this->setting_prefix . 'dca_period_type');
                }
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
            $payment_test_mode = $this->config->get($this->setting_prefix . 'test_mode');
            $apiPaymentInfo = $this->helper->get_ecpay_payment_api_info('', $payment_test_mode);
            
            $factory = new Factory([
                'hashKey' => $apiPaymentInfo['hashKey'],
                'hashIv'  => $apiPaymentInfo['hashIv'],
            ]);

            $checkoutResponse = $factory->create(VerifiedArrayResponse::class);
            $info             = $checkoutResponse->get($_POST);
            $order_id = $this->helper->getOrderIdByMerchantTradeNo($info);

            // Get the cart order info
            $order            = $this->model_checkout_order->getOrder($order_id);
            $order_status_id  = $order['order_status_id'];
            $order_total      = $order['total'];

            // Check the amounts
            if (round($info['TradeAmt'], 0) == round($order_total, 0)) {
                if (($info['SimulatePaid'] ?? '') == 1) {
                    // Simulate paid
                    // 定期定額新增訂單 (非第一次回傳)
                    if (isset($info['PeriodType']) && $info['PeriodType'] != '' && $info['TotalSuccessTimes'] > 1) {
                        $order_id = $this->create_dca_order($info, $order_id);
                    }

                    $status_id = $order_status_id;
                    $comment   = $this->language->get($this->lang_prefix . 'text_simulate_paid');
                    $this->model_checkout_order->addHistory($order_id, $status_id, $comment, false, false);
                    unset($status_id, $comment);
                } else {
                    // Update the order status
                    switch ($info['RtnCode']) {
                    // Paid
                    case 1:
                        // 定期定額新增訂單 (非第一次回傳)
                        if (isset($info['PeriodType']) && $info['PeriodType'] != '' && $info['TotalSuccessTimes'] > 1) {
                            $order_id = $this->create_dca_order($info, $order_id);
                        }

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

    /**
     * AIO 返回商店按鈕轉導結果頁
     */
    public function client_back() {
        if (!is_null($_GET['order_id'])) {
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder($_GET['order_id']);
            $order_status_id  = $order['order_status_id'];

            // 訂單狀態為取消
            if ($order_status_id == '7') {
                $this->response->redirect($this->url->link('checkout/failure'));
            }
        }

        $this->response->redirect($this->url->link('checkout/success'));
    }

    /**
     * 建立定期定額新訂單
     * @param array $info
     * @param int $order_id
     */
    public function create_dca_order($info, $order_id) {
        $this->load->model('checkout/order');
        
        // 取得舊訂單
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info) {
            // 取得舊訂單資訊
            $order_products = $this->model_checkout_order->getProducts($order_id);
            foreach ($order_products as $key => $product) {
                $option_data = [];
                $options = $this->model_checkout_order->getOptions($order_id, $product['order_product_id']);

                if(!empty($options)){
                    foreach ($options as $option) {
                        $option_data[] = [
                            'product_option_id'       => $option['product_option_id'],
                            'product_option_value_id' => $option['product_option_value_id'],
                            'option_id'               => $option['option_id'] ?? '',
                            'option_value_id'         => $option['option_value_id'] ?? '',
                            'name'                    => $option['name'],
                            'value'                   => $option['value'],
                            'type'                    => $option['type']
                        ];
                    }   
                }
    
                $subscription_data = [];
                if (isset($product['subscription']) && $product['subscription']) {
                    $subscription_data = [
                        'subscription_plan_id' => $product['subscription']['subscription_plan_id'],
                        'name'                 => $product['subscription']['name'],
                        'trial_price'          => $product['subscription']['trial_price'],
                        'trial_tax'            => $this->tax->getTax($product['subscription']['trial_price'], $product['tax_class_id']),
                        'trial_frequency'      => $product['subscription']['trial_frequency'],
                        'trial_cycle'          => $product['subscription']['trial_cycle'],
                        'trial_duration'       => $product['subscription']['trial_duration'],
                        'trial_remaining'      => $product['subscription']['trial_remaining'],
                        'trial_status'         => $product['subscription']['trial_status'],
                        'price'                => $product['subscription']['price'],
                        'tax'                  => $this->tax->getTax($product['subscription']['price'], $product['tax_class_id']),
                        'frequency'            => $product['subscription']['frequency'],
                        'cycle'                => $product['subscription']['cycle'],
                        'duration'             => $product['subscription']['duration']
                    ];
                }

                $order_products[$key]['option'] = $option_data;
                $order_products[$key]['subscription'] = $subscription_data;
            }
            
            $order_info['products'] = $order_products;
            $order_info['vouchers'] = $this->model_checkout_order->getVouchers($order_id);
            $order_info['totals'] = $this->model_checkout_order->getTotals($order_id);

            // 建立新訂單 data
            $new_order_data = $order_info;

            // 儲存成新訂單
            $new_order_id = $this->model_checkout_order->addOrder($new_order_data);

            // 新訂單新增歷程
            $this->model_checkout_order->addHistory($new_order_id, $order_info['order_status_id'], '定期定額付款第' . $info['TotalSuccessTimes'] . '次繳費成功，原始訂單編號: ' . $order_id, false);
            // 舊訂單新增歷程
            $this->model_checkout_order->addHistory($order_id, $order_info['order_status_id'], '定期定額付款第' . $info['TotalSuccessTimes'] . '次繳費成功，新訂單號: ' . $new_order_id, false);

            return $new_order_id;
        }
    }
}
?>
