<?php
namespace Opencart\Admin\Controller\Extension\Ecpay\Module;

use Ecpay\Sdk\Factories\Factory;

class EcpayInvoice extends \Opencart\System\Engine\Controller {
    private $error           = array();
    private $separator       = '';
    private $module_name     = 'ecpayinvoice';
    private $module_path     = '';
    private $name_prefix     = '';
    private $id_prefix       = '';
    private $lang_prefix     = '';
    private $setting_prefix  = '';
    private $module_code     = '';
    private $extension_route = 'marketplace/extension';
    private $url_secure      = true;
    private $validate_fields = array(
        'mid',
        'hashkey',
        'hashiv',
    );

    public function __construct($registry) {
        parent::__construct($registry);

        require_once DIR_EXTENSION . 'ecpay/system/library/EcpayInvoiceHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayInvoiceHelper;

        if (VERSION >= '4.0.2.0') {
            $this->separator = '.';
        } else {
            $this->separator = '|';
        }

        // Set the variables
        $this->module_code    = 'module_' . $this->module_name;
        $this->module_path    = 'extension/ecpay/module/' . $this->module_name;
        $this->lang_prefix    = $this->module_name . '_';
        $this->name_prefix    = 'module_' . $this->module_name;
        $this->id_prefix      = 'module-' . $this->module_name;
        $this->setting_prefix = 'module_' . $this->module_name . '_';
    }

    public function index() {
        $this->load->language($this->module_path);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        // Token
        $token = $this->session->data['user_token'];

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_' . $this->module_name, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link($this->extension_route, 'user_token=' . $token . '&type=module', $this->url_secure));
        }

        $data['heading_title']  = $this->language->get('heading_title');
        $data['text_edit']      = $this->language->get('text_edit');
        $data['text_enabled']   = $this->language->get('text_enabled');
        $data['text_disabled']  = $this->language->get('text_disabled');
        $data['text_autoissue'] = $this->language->get('text_autoissue');

        $data['entry_mid']       = $this->language->get('entry_mid');
        $data['entry_hashkey']   = $this->language->get('entry_hashkey');
        $data['entry_hashiv']    = $this->language->get('entry_hashiv');
        $data['entry_test_mode'] = $this->language->get('entry_test_mode');
        $data['entry_test_mode_info'] = $this->language->get('entry_test_mode_info');
        $data['entry_autoissue'] = $this->language->get('entry_autoissue');
        $data['entry_status']    = $this->language->get('entry_status');

        $data['button_save']   = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        // Get ECPay translations
        $translation_names = array(
            'text_edit',
            'text_autoissue',

            'entry_status',
            'entry_mid',
            'entry_hashkey',
            'entry_hashiv',
            'entry_test_mode',
            'entry_autoissue',
        );
        foreach ($translation_names as $name) {
            $data[$name] = $this->language->get($name);
        }
        unset($translation_names);

        if (isset($this->error['error_warning'])) {
            $data['error_warning'] = $this->error['error_warning'];
        } else {
            $data['error_warning'] = '';
        }

        $ecpayErrorList = array(
            'mid',
            'hashkey',
            'hashiv',
        );
        foreach ($ecpayErrorList as $errorName) {
            if (isset($this->error[$errorName])) {
                $data['error_' . $errorName] = $this->error[$errorName];
            } else {
                $data['error_' . $errorName] = '';
            }
        }
        unset($ecpayErrorList);

        $data['breadcrumbs']   = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $token, $this->url_secure),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link($this->extension_route, 'user_token=' . $token . '&type=module', $this->url_secure),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->module_path, 'user_token=' . $token, $this->url_secure),
        );

        $data[$this->name_prefix . 'statuses']   = array();
        $data[$this->name_prefix . 'statuses'][] = array(
            'value' => '1',
            'text'  => $this->language->get('text_enabled'),
        );
        $data[$this->name_prefix . 'statuses'][] = array(
            'value' => '0',
            'text'  => $this->language->get('text_disabled'),
        );

        $data[$this->name_prefix . 'autoissues']   = array();
        $data[$this->name_prefix . 'autoissues'][] = array(
            'value' => '0',
            'text'  => $this->language->get('text_disabled'),
        );
        $data[$this->name_prefix . 'autoissues'][] = array(
            'value' => '1',
            'text'  => $this->language->get('text_enabled'),
        );

        $data['save'] = $this->url->link($this->module_path . $this->separator . 'save', 'user_token=' . $token, $this->url_secure);
        $data['back'] = $this->url->link($this->extension_route, 'user_token=' . $token . '&type=module', $this->url_secure);

        // Get the setting
        $settings = array(
            'status',
            'mid',
            'hashkey',
            'hashiv',
            'test_mode',
            'autoissue',
        );

        foreach ($settings as $name) {
            $variable_name = $this->name_prefix . '_' . $name;
            if (isset($this->request->post[$variable_name])) {
                $data[$variable_name] = $this->request->post[$variable_name];
            } else {
                $data[$variable_name] = $this->config->get($variable_name);
            }
            unset($variable_name);
        }
        unset($settings);

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $data['name_prefix'] = $this->name_prefix;
        $data['id_prefix']   = $this->id_prefix;

        $this->response->setOutput($this->load->view($this->module_path, $data));
    }

    public function save() {
        $json = [];

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->load->language($this->module_path);
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting($this->module_code, $this->request->post);

            $json['success'] = $this->language->get('text_success');
        } else {
            $json['error'] = $this->error;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function validate() {
        $this->load->language($this->module_path);

        // Premission validate
        if (!$this->user->hasPermission('modify', $this->module_path)) {
            $this->error['error_warning'] = $this->language->get('error_permission');
        }

        // Required fields validate
        foreach ($this->validate_fields as $name) {
            $field_name = $this->setting_prefix . $name;
            if (empty($this->request->post[$field_name])) {
                $this->error[$this->id_prefix . '-' . $name] = $this->language->get('error_' . $name);
            }
            unset($field_name);
        }

        return !$this->error;
    }

    // 手動開立發票
    public function createInvoiceNo() {

        $this->load->language('sale/order');

        $json = array();

        if (!$this->user->hasPermission('modify', 'sale/order')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif (isset($this->request->get['order_id'])) {

            $this->load->model('sale/order');

            // 取得訂單
            $orderId = (int) $this->request->get['order_id'];
            $order   = $this->model_sale_order->getOrder($orderId);

            // 訂單付款方式
            $paymentMethod = explode('.', $order['payment_method']['code']);

            // 判斷是否啟動ECPAY電子發票開立並且選擇綠界金流
            $invoiceStatus = $this->config->get($this->setting_prefix . 'status');
            
            if ($invoiceStatus == 1 && $paymentMethod[0] == 'ecpaypayment') {
                // 1.參數初始化
                define('WEB_MESSAGE_NEW_LINE', '|'); // 前端頁面訊息顯示換行標示語法

                $sMsg   = '';
                $sMsgP2 = ''; // 金額有差異提醒
                $bError = false; // 判斷各參數是否有錯誤，沒有錯誤才可以開發票

                // *訂單資訊
                $orderProduct = $this->model_sale_order->getProducts($orderId); // 訂購商品
                $orderTotal   = $this->model_sale_order->getTotals($orderId); // 訂單金額

                // *統編與愛心碼資訊
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "invoice_info WHERE order_id = '" . $orderId . "'");

                // 3.判斷資料正確性
                if ($query->num_rows == 0) {
                    $bError = true;
                    $sMsg .= (empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE) . '開立發票資訊不存在。';
                } else {
                    $invoiceInfo = $query->rows[0];
                }

                $ecpayinvoiceTestMode = $this->config->get($this->setting_prefix . 'test_mode');	// 測試模式
                $apiInfo = $this->helper->get_ecpay_invoice_api_info('issue', $ecpayinvoiceTestMode);

                // *MID判斷是否有值
                if ($apiInfo['merchantId'] == '') {
                    $bError = true;
                    $sMsg .= (empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE) . '請填寫商店代號(Merchant ID)。';
                }

                // *HASHKEY判斷是否有值
                if ($apiInfo['hashKey'] == '') {
                    $bError = true;
                    $sMsg .= (empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE) . '請填寫金鑰(Hash Key)。';
                }

                // *HASHIV判斷是否有值
                if ($apiInfo['hashIv'] == '') {
                    $bError = true;
                    $sMsg .= (empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE) . '請填寫向量(Hash IV)。';
                }

                // 判斷是否開過發票
                if ($order['invoice_no'] != 0) {
                    $bError = true;
                    $sMsg .= (empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE) . '已存在發票紀錄，請重新整理頁面。';
                }

                // 判斷商品是否存在
                if (count($orderProduct) < 0) {
                    $bError = true;
                    $sMsg .= (empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE) . ' 該訂單編號不存在商品，不允許開立發票。';
                } else {
                    // 判斷商品是否含小數點
                    foreach ($orderProduct as $key => $value) {
                        if (!strstr($value['price'], '.00')) {
                            $sMsgP2 .= (empty($sMsgP2) ? '' : WEB_MESSAGE_NEW_LINE) . '提醒：商品 ' . $value['name'] . ' 金額存在小數點，將以無條件進位開立發票。';
                        }
                    }
                }

                if (!$bError) {
                    $loveCode           = '';
                    $isDonation         = '0';
                    $isPrint            = '0';
                    $customerIdentifier = '';
                    $customerName       = $order['lastname'] . $order['firstname'];

                    $carrierType = '';
                    $carrierNum  = '';

                    switch ($invoiceInfo['invoice_type']) {
                    // 個人
                    case 1:
                        switch ($invoiceInfo['carrier_type']) {
                        // 紙本
                        case 1:
                            $carrierType = '';
                            $isPrint     = '1';
                            break;
                        // 雲端發票
                        case 2:
                            $carrierType = '1';
                            break;
                        // 自然人憑證
                        case 3:
                            $carrierType = '2';
                            $carrierNum  = $invoiceInfo['carrier_num'];
                            break;
                        // 手機條碼
                        case 4:
                            $carrierType = '3';
                            $carrierNum  = $invoiceInfo['carrier_num'];
                            break;
                        default:
                            $carrierType = '';
                            $isPrint     = '1';
                            break;
                        }
                        break;
                    // 公司
                    case 2:
                        $isPrint            = '1';
                        $customerIdentifier = $invoiceInfo['company_write'];
                        $customerName       = ($invoiceInfo['customer_company']) ?: $customerName;

                        switch ($invoiceInfo['carrier_type']) {
                        // 雲端發票
                        case 2:
                            $isPrint     = '0';
                            $carrierType = '1';
                            break;
                        // 手機條碼
                        case 4:
                            $carrierType = '3';
                            $carrierNum  = $invoiceInfo['carrier_num'];
                            break;
                        }
                        break;
                    // 捐贈
                    case 3:
                        $isDonation = '1';
                        $loveCode   = $invoiceInfo['love_code'];
                        break;
                    }

                    // 4.送出參數
                    try {
                        // 算出商品各別金額
                        $subTotalReal = 0; // 實際無條進位小計

                        foreach ($orderProduct as $key => $value) {
                            $quantity = ceil($value['quantity']);
                            $price    = ceil($value['price']);
                            $total    = $quantity * $price; // 各商品小計

                            $subTotalReal = $subTotalReal + $total; // 計算發票總金額

                            $productName = $value['name'];
                            $productNote = $value['model'] . '-' . $value['product_id'];

                            mb_internal_encoding('UTF-8');
                            $stringLimit  = 10;
                            $sourceLength = mb_strlen($productNote);

                            if ($stringLimit < $sourceLength) {

                                $stringLimit = $stringLimit - 3;

                                if ($stringLimit > 0) {
                                    $productNote = mb_substr($productNote, 0, $stringLimit) . '...';
                                }
                            }

                            $items[] = [
                                'ItemName'    => $productName,
                                'ItemCount'   => $quantity,
                                'ItemWord'    => '批',
                                'ItemPrice'   => $price,
                                'ItemTaxType' => '1',
                                'ItemAmount'  => $total,
                                'ItemRemark'  => $productNote,
                            ];
                        }

                        // 找出sub-total
                        $total = 0;
                        foreach ($orderTotal as $key2 => $value2) {
                            if ($value2['title'] == 'Total') {
                                $total = (int) $value2['value'];
                                break;
                            }
                        }

                        // 其他項目計算
                        foreach ($orderTotal as $key2 => $value2) {
                            if ($value2['code'] != 'total' && $value2['code'] != 'sub_total') {

                                $subTotalReal = $subTotalReal + (int) $value2['value']; // 計算發票總金額

                                $items[] = [
                                    'ItemName'    => $value2['title'],
                                    'ItemCount'   => 1,
                                    'ItemWord'    => '批',
                                    'ItemPrice'   => (int) $value2['value'],
                                    'ItemTaxType' => 1,
                                    'ItemAmount'  => (int) $value2['value'],
                                    'ItemRemark'  => $value2['title'],
                                ];
                            }
                        }

                        // 無條件位後加總有差異
                        if ($total != $subTotalReal) {
                            $sMsgP2 .= (empty($sMsgP2) ? '' : WEB_MESSAGE_NEW_LINE) . '綠界科技電子發票開立，實際金額 $' . $total . '， 無條件進位後 $' . $subTotalReal;
                        }

                        // 買受人地址
                        $customerAddr = '';
                        if (empty($order['payment_country']) ||
                            empty($order['payment_postcode']) ||
                            empty($order['payment_city']) ||
                            empty($order['payment_address_1']) ||
                            empty($order['payment_address_2'])
                        ) {
                            $customerAddr = $order['shipping_country'] . $order['shipping_postcode'] . $order['shipping_city'] . $order['shipping_address_1'] . $order['shipping_address_2'];
                        } else {
                            $customerAddr = $order['payment_country'] . $order['payment_postcode'] . $order['payment_city'] . $order['payment_address_1'] . $order['payment_address_2'];
                        }

                        // 特店自訂編號
                        $relateNumber = $this->helper->get_relate_number($orderId);

                        // 記錄發票備註卡號末四碼
                        $creditRemark = '';
                        if (str_contains($paymentMethod[1], 'credit')) {
                            $orderExtend  = $this->db->query('Select * from ' . DB_PREFIX . 'order_extend where order_id=' . $orderId);
                            $creditRemark = ' 信用卡末四碼' . ($orderExtend->row['card_no4']) ?? '';
                        }
                        
                        $factory = new Factory([
                            'hashKey' => $apiInfo['hashKey'],
                            'hashIv'  => $apiInfo['hashIv'],
                        ]);
                        $postService = $factory->create('PostWithAesJsonResponseService');

                        $data = [
                            'MerchantID'         => $apiInfo['merchantId'],
                            'RelateNumber'       => $relateNumber,
                            'CustomerID'         => '',
                            'CustomerIdentifier' => $customerIdentifier,
                            'CustomerName'       => $customerName,
                            'CustomerAddr'       => $customerAddr,
                            'CustomerPhone'      => $order['telephone'],
                            'CustomerEmail'      => $order['email'],
                            'ClearanceMark'      => '',
                            'Print'              => $isPrint,
                            'Donation'           => $isDonation,
                            'LoveCode'           => $loveCode,
                            'CarrierType'        => $carrierType,
                            'CarrierNum'         => $carrierNum,
                            'TaxType'            => 1,
                            'SalesAmount'        => $subTotalReal,
                            'Items'              => $items,
                            'InvType'            => '07',
                            'vat'                => '',
                            'InvoiceRemark'      => 'OC4_ECPayInvoice' . $creditRemark,
                        ];

                        $input = [
                            'MerchantID' => $apiInfo['merchantId'],
                            'RqHeader'   => [
                                'Timestamp' => time(),
                                'Revision'  => '3.0.0',
                            ],
                            'Data'       => $data,
                        ];

                        $returnInfo = $postService->post($input, $apiInfo['action']);
                    } catch (Exception $e) {
                        // 例外錯誤處理
                        $sMsg = $e->getMessage();
                    }

                    // 5.有錯誤訊息或回傳狀態 RtnCode 不等於1 則不寫入DB
                    $ecpayInvoiceNo = '';
                    if ($sMsg != '' || !isset($returnInfo['Data']['RtnCode']) || $returnInfo['Data']['RtnCode'] != 1) {
                        $sMsg .= '綠界科技電子發票手動開立訊息';
                        $sMsg .= (isset($returnInfo)) ? print_r($returnInfo, true) : '';

                        $json['error']      = $sMsg;
                        $json['invoice_no'] = '';

                        // A.寫入LOG
                        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . $orderId . "', order_status_id = '" . (int) $order['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
                    } else {
                        // 無條件進位 金額有差異，寫入LOG提醒管理員
                        if ($sMsgP2 != '') {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . $orderId . "', order_status_id = '" . (int) $order['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsgP2) . "', date_added = NOW()");
                        }

                        // A.更新發票號碼欄位
                        $ecpayInvoiceNo     = $returnInfo['Data']['InvoiceNo'];
                        $json['invoice_no'] = $ecpayInvoiceNo;

                        // B.整理發票號碼並寫入DB
                        $invoiceNoPre = substr($ecpayInvoiceNo, 0, 2);
                        $invoiceNo    = substr($ecpayInvoiceNo, 2);

                        // C.回傳資訊轉陣列提供history資料寫入
                        $returnInfoHistory = '綠界科技電子發票手動開立訊息';
                        $returnInfoHistory .= print_r($returnInfo, true);

                        // D.更新發票資訊
                        $this->load->model($this->module_path);
                        $this->model_extension_ecpay_module_ecpayinvoice->updateInvoiceInfo($orderId, ['relate_number' => $relateNumber, 'random_number' => $returnInfo['Data']['RandomNumber'], 'invoice_process' => 1]);

                        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET invoice_no = '" . $invoiceNo . "', invoice_prefix = '" . $this->db->escape($invoiceNoPre) . "' WHERE order_id = '" . $orderId . "'");
                        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . $orderId . "', order_status_id = '" . (int) $order['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($returnInfoHistory) . "', date_added = NOW()");
                    }

                    return $ecpayInvoiceNo;
                } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . $orderId . "', order_status_id = '" . (int) $order['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
                    return '';
                }
            }
            return '';
        } else {
            return '';
        }
    }

    // 訂單頁發票資訊
    public function orderInvoiceView(&$route, &$data, &$output) {
        // 檢查發票Table 是否存在
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `code` = '" . $this->module_name . "'");
        if ($result->num_rows) {
            // 判斷是否啟動綠界電子發票並且選擇綠界金流
            $invoiceStatus = $this->config->get($this->setting_prefix . 'status');
            $paymentMethod = explode('.', $data['payment_code']);

            // 確認綠界發票區塊是否存在
            $pos = strpos($output, '<div id="ecpayinvoice-order-info">');

            // 取得發票資訊
            $this->load->language($this->module_path);
            $this->load->model($this->module_path);

            $orderId     = $data['order_id'];
            $invoiceInfo = $this->model_extension_ecpay_module_ecpayinvoice->getInvoiceInfo($orderId);

            if ($invoiceStatus == 1 && !empty($invoiceInfo) && $paymentMethod[0] == 'ecpaypayment' && $pos === false) {
                // 傳入 view 的資料
                $data['invoice_type']      = $invoiceInfo['invoice_type'];
                $data['invoice_type_name'] = $this->helper->invoiceType[$invoiceInfo['invoice_type']];
                $data['carrier_type']      = $invoiceInfo['carrier_type'];
                $data['carrier_type_name'] = ($invoiceInfo['carrier_type'] == 0) ? '' : $this->helper->invoiceCarrierType[$invoiceInfo['carrier_type']];
                $data['carrier_num']       = $invoiceInfo['carrier_num'];
                $data['company_write']     = $invoiceInfo['company_write'];
                $data['customer_company']  = $invoiceInfo['customer_company'];
                $data['love_code']         = $invoiceInfo['love_code'];
                $data['invoice_process']   = $invoiceInfo['invoice_process'];

                $template = $this->load->view($this->module_path . '_order', $data);
                $search   = '<span id="invoice-value">' . $data['invoice_prefix'] . $data['invoice_no'] . '</span>';
                $replace  = $search . $template;

                $output = str_replace($search, $replace, $output);
            }
        }
    }

    public function install() {
        // EVENT ADD
        $this->load->model($this->module_path);
        $this->load->model('setting/event');

        // add invoice checkout view event
        $this->model_setting_event->addEvent([
            'code'        => 'ecpay_invoice_checkout_view',
            'trigger'     => 'catalog/view/checkout/payment_method/after',
            'action'      => 'extension/ecpay/module/ecpayinvoice' . $this->separator . 'checkoutView',
            'description' => '',
            'sort_order'  => 1,
            'status'      => true,
        ]);

        // add invoice admin order view
        $this->model_setting_event->addEvent([
            'code'        => 'ecpay_invoice_admin_view',
            'trigger'     => 'admin/view/sale/order_info/after',
            'action'      => 'extension/ecpay/module/ecpayinvoice' . $this->separator . 'orderInvoiceView',
            'description' => '',
            'sort_order'  => 1,
            'status'      => true,
        ]);

        // add invoice create event
        $this->model_setting_event->addEvent([
            'code'        => 'ecpay_invoice_create',
            'trigger'     => 'admin/model/sale/order/createInvoiceNo/before',
            'action'      => 'extension/ecpay/module/ecpayinvoice' . $this->separator . 'createInvoiceNo',
            'description' => '',
            'sort_order'  => 1,
            'status'      => true,
        ]);

        // db query
        $this->model_extension_ecpay_module_ecpayinvoice->install();
    }

    public function uninstall() {
        $this->load->model($this->module_path);
        $this->load->model('setting/event');

        // delete module event
        $this->model_setting_event->deleteEventByCode('ecpay_invoice_checkout_view');
        $this->model_setting_event->deleteEventByCode('ecpay_invoice_admin_view');
        $this->model_setting_event->deleteEventByCode('ecpay_invoice_create');

        // db query
        $this->model_extension_ecpay_module_ecpayinvoice->uninstall();
    }
}