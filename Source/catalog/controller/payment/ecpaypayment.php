<?php
namespace Opencart\Catalog\Controller\Extension\Ecpay\Payment;

use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\VerifiedArrayResponse;

class Ecpaypayment extends \Opencart\System\Engine\Controller
{
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
    public function __construct($registry)
    {
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
        $this->helper = new \Opencart\System\Library\EcpayPaymentHelper($this->registry);

        // invoice
        $this->invoice_setting_prefix = 'module_' . $this->invoice_module_name . '_';

        // logistic
        $this->logistic_module_path    = 'extension/ecpay/shipping/' . $this->logistic_module_name;
        $this->logistic_setting_prefix = 'shipping_' . $this->logistic_module_name . '_';
    }

    /**
     * 結帳選完 Payment 後執行
     */
    public function index()
    {
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
            $ecpay_payment_methods = [];
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
            $delivery_method = [
                'ecpaylogistic.unimart_collection',
                'ecpaylogistic.fami_collection',
                'ecpaylogistic.hilife_collection',
                'ecpaylogistic.okmart_collection',
                'ecpaylogistic.tcat_collection',
                'ecpaylogistic.unimart',
                'ecpaylogistic.fami',
                'ecpaylogistic.hilife',
                'ecpaylogistic.okmart',
                'ecpaylogistic.tcat',
                'ecpaylogistic.post',
            ];

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
    public function confirm()
    {
        // loading example payment language
        $this->load->language($this->module_path);

        $json = [];
        if (! isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        $payment_methods = $this->config->get($this->setting_prefix . 'payment_methods');
        $choose_payment_array = explode('.', $this->session->data['payment_method']['code']);
        if (! isset($this->session->data['payment_method']) || !in_array($choose_payment_array[1], $payment_methods)) {
            $json['error'] = $this->language->get('error_payment_method');

        }
        if (! $json) {
            $this->load->model('checkout/order');
            $status_id = $this->config->get($this->setting_prefix . 'create_status');
            $this->model_checkout_order->addHistory($this->session->data['order_id'], $status_id);

            $json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // Redirect to AIO
    public function redirect()
    {
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

                // 訂單沒有運送方式時賦予空值，防止原生程式沒有 shipping_method 的 warning
                if ($order['shipping_method'] == '') {
                    $shippingMethod = [
                        'shipping_method' => [
                            'code' => '',
                            'name' => '',
                        ],
                    ];
                    $this->model_checkout_order->editOrder($order_id, $shippingMethod);
                }

                // Update order status and comments
                $comment   = $this->language->get($this->lang_prefix . 'text_' . $choose_payment_array[1]);
                $status_id = $this->config->get($this->setting_prefix . 'create_status');
                $this->model_checkout_order->addHistory($order_id, $status_id, $comment, true, false);

                // 商品重量、金流測試模式
                $weight            = $this->cart->getWeight();
                $payment_test_mode = $this->config->get($this->setting_prefix . 'test_mode');
                $extra_order_data  = [
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
                    $activity_data = [
                        'customer_id' => $this->customer->getId(),
                        'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                        'order_id'    => $order_id,
                    ];
                } else {
                    $activity_key  = 'order_guest';
                    $guest         = $this->session->data['customer'];
                    $activity_data = [
                        'name'     => $guest['firstname'] . ' ' . $guest['lastname'],
                        'order_id' => $order_id,
                    ];
                }
                $this->model_account_activity->addActivity($activity_key, $activity_data);

                // Clean the session
                $session_list = [
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
                ];
                foreach ($session_list as $name) {
                    unset($this->session->data[$name]);
                }

                $apiPaymentInfo = $this->helper->get_ecpay_payment_api_info('AioCheckOut', $payment_test_mode);

                $factory = new Factory([
                    'hashKey' => $apiPaymentInfo['hashKey'],
                    'hashIv'  => $apiPaymentInfo['hashIv'],
                ]);
                $autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

                // 取得 SDK ChoosePayment
                $sdkPayment = $this->helper->getSdkPayment($choose_payment_array[1]);

                // 組合送往 AIO 參數
                $input = [
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
                ];

                // 取得額外參數
                if ($choose_payment_array[1] == 'dca') {
                    $input['PeriodReturnURL'] = $this->url->link($this->module_path . '|response', '', true);
                    $input['Frequency']       = $this->config->get($this->setting_prefix . 'dca_frequency');
                    $input['ExecTimes']       = $this->config->get($this->setting_prefix . 'dca_exec_times');
                    $input['PeriodType']      = $this->config->get($this->setting_prefix . 'dca_period_type');
                }
                $input = $this->helper->add_type_info($input, $choose_payment_array[1]);

                // 紀錄綠界付款資訊
                $result = $this->helper->insertEcpayResponsePaymentInfo($order_id, $choose_payment_array[1], $input['MerchantTradeNo'], 0);

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
    public function response()
    {
        // Load the model and translation
        $this->load->language($this->module_path);
        $this->load->model('checkout/order');

        // Set the default result message
        $result_message = '1|OK';
        $order_id       = null;
        $order          = null;

        try {
            $payment_test_mode = $this->config->get($this->setting_prefix . 'test_mode');
            $apiPaymentInfo    = $this->helper->get_ecpay_payment_api_info('', $payment_test_mode);

            $factory = new Factory([
                'hashKey' => $apiPaymentInfo['hashKey'],
                'hashIv'  => $apiPaymentInfo['hashIv'],
            ]);

            $checkoutResponse = $factory->create(VerifiedArrayResponse::class);
            $info             = $checkoutResponse->get($_POST);
            $order_id         = $this->helper->getOrderIdByMerchantTradeNo($info);

            // Get the cart order info
            $order           = $this->model_checkout_order->getOrder($order_id);
            $order_status_id = $order['order_status_id'];
            $order_total     = $order['total'];

            // 取出金額參數
            $TradeAmt = 0;
            if (isset($info['Amount'])) {
                // 定期定額付款結果時 Amount 會有值
                $TradeAmt = $info['Amount'];
            } else if (isset($info['TradeAmt'])) {
                // 其他付款結果時 TradeAmt 會有值
                $TradeAmt = $info['TradeAmt'];
            }

            // Check the amounts
            if (round($TradeAmt, 0) == round($order_total, 0)) {
                if (($info['SimulatePaid'] ?? '') == 1) {
                    // 模擬付款 僅執行備註寫入
                    $status_id = $order_status_id;
                    $comment   = $this->language->get($this->lang_prefix . 'text_simulate_paid');
                    $this->model_checkout_order->addHistory($order_id, $status_id, $comment, false, false);
                    unset($status_id, $comment);

                } else {
                    // 計算定期定額付款結果回傳交易成功最大次數
                    $max_success_times = $this->helper->checkDcaMaxTotalSuccessTimes($info['MerchantTradeNo']);

                    // 將綠界回傳付款結果存至 DB
                    $this->helper->updateEcpayResponsePaymentInfo($order_id, $info);

                    // Update the order status
                    switch ($info['RtnCode']) {
                        // Paid
                        case 1:
                            $status_id = $this->config->get($this->setting_prefix . 'success_status');

                            // 檢查是否為定期定額
                            if (isset($info['PeriodType']) && $info['PeriodType'] != '') {

                                // 確認訂單狀態存在
                                $is_exist = $this->helper->isEcpayPaymentResponseInfoExist($order_id, $info['MerchantTradeNo']);
                                if ($is_exist) {
                                    $dca_success_comment = '綠界定期定額訂單第' .$info['TotalSuccessTimes']. '次付款結果回傳';

                                    // 確認定期定額訂單最後交易成功次數
                                    if ($max_success_times == 0 && $info['TotalSuccessTimes'] == 1) {
                                        // 第一次
                                        $dca_success_comment .= '(Master)';
                                    }
                                    else {
                                        // 非第一次
                                        // 判斷是否已接收過定期定額付款結果，若重複則不處理
                                        if ($max_success_times < $info['TotalSuccessTimes']) {
                                            $order_id = $this->create_dca_order($info, $order_id);
                                        }
                                    }

                                    // 增加定期定額分期資訊
                                    $dca_pattern = $this->language->get($this->lang_prefix . 'text_dca_comment');
                                    $comment = $this->helper->getComment($dca_pattern, $info, 1);
                                    $this->model_checkout_order->addHistory($order_id, $status_id, $comment, false, false);
                                    unset($dca_pattern, $comment);

                                    // 增加定期定額成功次數資訊
                                    $this->model_checkout_order->addHistory($order_id, $status_id, $dca_success_comment, false, false);

                                }
                                else {
                                    // (新舊版外掛相容)若為舊版訂單後續付款 response 將會查無原始訂單，直接寫入資料
                                    $order = $this->model_checkout_order->getOrder($_GET['order_id']);
                                    $payment_method = explode('.', $order['payment_method']['code']);
                                    $result = $this->helper->insertEcpayResponsePaymentInfo($order_id, $payment_method[1], $info['MerchantTradeNo'], 1);
                                }
                            }

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
            if (! is_null($order_id)) {
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
    public function client_back()
    {
        if (! is_null($_GET['order_id'])) {
            $this->load->model('checkout/order');
            $order           = $this->model_checkout_order->getOrder($_GET['order_id']);
            $order_status_id = $order['order_status_id'];

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
    public function create_dca_order($info, $order_id)
    {
        $this->load->model('checkout/order');

        // 取得舊訂單
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info) {
            // 取得舊訂單資訊
            $order_products = $this->model_checkout_order->getProducts($order_id);
            foreach ($order_products as $key => $product) {
                $option_data = [];
                $options     = $this->model_checkout_order->getOptions($order_id, $product['order_product_id']);

                if (! empty($options)) {
                    foreach ($options as $option) {
                        $option_data[] = [
                            'product_option_id'       => $option['product_option_id'],
                            'product_option_value_id' => $option['product_option_value_id'],
                            'option_id'               => $option['option_id'] ?? '',
                            'option_value_id'         => $option['option_value_id'] ?? '',
                            'name'                    => $option['name'],
                            'value'                   => $option['value'],
                            'type'                    => $option['type'],
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
                        'duration'             => $product['subscription']['duration'],
                    ];
                }

                $order_products[$key]['option']       = $option_data;
                $order_products[$key]['subscription'] = $subscription_data;
            }

            $order_info['products'] = $order_products;
            $order_info['vouchers'] = $this->model_checkout_order->getVouchers($order_id);
            $order_info['totals']   = $this->model_checkout_order->getTotals($order_id);

            // 建立新訂單 data
            $new_order_data = $order_info;

            // 儲存成新訂單
            $new_order_id = $this->model_checkout_order->addOrder($new_order_data);

            // 處理發票資訊
            $query_old_invoice = $this->db->query("SELECT * FROM " . DB_PREFIX . "invoice_info WHERE order_id = '" . (int) $order_id . "'");
            $query_new_invoice = $this->db->query("SELECT * FROM " . DB_PREFIX . "invoice_info WHERE order_id = '" . (int) $new_order_id . "'");
            if ($query_old_invoice->num_rows > 0 && $query_new_invoice->num_rows == 0) {
                $order_invoice = $query_old_invoice->rows[0];

                // 新訂單新增發票資訊
                $this->db->query("INSERT INTO `" . DB_PREFIX . "invoice_info` (`order_id`, `love_code`, `company_write`, `customer_company`, `invoice_type`, `carrier_type`, `carrier_num`, `createdate`) VALUES ('" . $new_order_id . "', '" . $this->db->escape($order_invoice['love_code']) . "', '" . $this->db->escape($order_invoice['company_write']) . "', '" . $this->db->escape($order_invoice['customer_company']) . "', '" . $this->db->escape($order_invoice['invoice_type']) . "', '" . $this->db->escape($order_invoice['carrier_type']) . "', '" . $this->db->escape($order_invoice['carrier_num']) . "', '" . time() . "' )");
            }

            // 新訂單新增歷程
            $this->model_checkout_order->addHistory($new_order_id, $order_info['order_status_id'], '定期定額付款第' . $info['TotalSuccessTimes'] . '次繳費成功，原始訂單編號: ' . $order_id, false);
            // 舊訂單新增歷程
            $this->model_checkout_order->addHistory($order_id, $order_info['order_status_id'], '定期定額付款第' . $info['TotalSuccessTimes'] . '次繳費成功，新訂單號: ' . $new_order_id, false);

            return $new_order_id;
        }
    }
}
