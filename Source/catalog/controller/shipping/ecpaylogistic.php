<?php
namespace Opencart\Catalog\Controller\Extension\Ecpay\Shipping;

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\AesService;
use Ecpay\Sdk\Response\VerifiedArrayResponse;

class EcpayLogistic extends \Opencart\System\Engine\Controller {
    private $separator  = '';
    private $url_secure = true;

    // payment
    private $ecpay_payment_module_name = 'ecpaypayment';
    private $ecpay_payment_module_path = '';

    // Logistic
    private $prefix                             = 'shipping_ecpaylogistic_';
    private $ecpay_logistic_module_name         = 'ecpaylogistic';
    private $ecpay_logistic_module_path         = '';
    private $ecpay_logistic_payment_module_path = '';
    private $ecpay_logistic_model_name          = '';

    // invoice
    private $ecpay_invoice_module_name    = 'ecpayinvoice';
    private $ecpay_invoice_setting_prefix = '';

    public function __construct($registry) {
        parent::__construct($registry);

        if (VERSION >= '4.0.2.0') {
            $this->separator = '.';
        } else {
            $this->separator = '|';
        }

        // payment
        $this->ecpay_payment_module_path          = 'extension/ecpay/payment/' . $this->ecpay_payment_module_name;
        $this->ecpay_logistic_payment_module_path = 'extension/ecpay/payment/' . $this->ecpay_logistic_module_name;

        // invoice
        $this->ecpay_invoice_setting_prefix = 'module_' . $this->ecpay_invoice_module_name . '_';

        // logistic
        $this->ecpay_logistic_module_path = 'extension/ecpay/shipping/' . $this->ecpay_logistic_module_name;
        $this->ecpay_logistic_model_name  = 'model_extension_ecpay_shipping_' . $this->ecpay_logistic_module_name;
        $this->load->model($this->ecpay_logistic_module_path);

        require_once DIR_EXTENSION . 'ecpay/system/library/EcpayLogisticHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayLogisticHelper;
    }

    public function index() {
        // PAYMENT
        if (true) {
            // Get the translations
            $this->load->language($this->ecpay_logistic_module_path);
            $data['text_checkout_button'] = $this->language->get($this->lang_prefix . 'text_checkout_button');
            $data['text_title']           = $this->language->get($this->lang_prefix . 'text_title');

            // Set the view data
            $data['id_prefix']   = $this->id_prefix;
            $data['module_name'] = $this->module_name;
            $data['name_prefix'] = $this->name_prefix;
        }

        // 轉導至門市選擇
        $data['redirect_url'] = $this->url->link(
            $this->ecpay_logistic_module_path . $this->separator . 'express_map',
            '',
            $this->url_secure
        );

        // Load the template
        return $this->load->view($this->ecpay_logistic_module_path, $data);
    }

    // 結帳頁顯示客製欄位
    public function add_shipping_field(&$route, &$args, &$output) {
        $this->load->language($this->ecpay_logistic_module_path);

        if ($this->config->get($this->prefix . 'status')) {
            $data = [];

            // 確認當前運送方式是不是選擇綠界物流
            $shipping_method_array = explode('.', $args['code']);
            // 確認綠界物流及金流皆有開啟
            $data['status'] = $shipping_method_array[0] === 'ecpaylogistic' ? true : false;
            // 取得現有session手機號碼
            if ($data['status']) {
                $data['customer']['telephone'] = $this->session->data['customer']['telephone'] ?? '';

            } else {
                if (isset($this->session->data['customer']['telephone'])) {
                    $this->session->data['customer']['telephone'] = '';
                }
            }

            $data['telephone_isinvalid'] = false;
            $data['telephone_error']     = '';

            // 當前是綠界物流的話，判斷手機號碼欄位是否已填寫
            if ($data['status'] && isset($this->session->data['telephone'])) {
                $data['telephone_isinvalid'] = !preg_match('/^09\d{8}$/', $this->session->data['telephone']);
                $data['telephone_error']     = $data['telephone_isinvalid'] ? 'No special symbols are allowed in the phone number, it must be ten digits long and start with 09' : '';
            }

            $template = $this->load->view('extension/ecpay/shipping/custom_field', $data);
            $output .= $template;
        }
    }

    public function validate_shipping_field($data = '') {
        $json = array(
            'success' => true,
            'message' => '',
        );

        // 綠界物流欄位列表
        $field_list = array(
            'telephone',
        );

        try {
            if (in_array($this->request->post['key'], $field_list)) {
                switch ($this->request->post['key']) {
                case 'telephone':
                    $this->session->data['customer'][$this->request->post['key']] = $this->request->post['value'];

                    // 驗證電話欄位格式
                    if (!preg_match('/^09\d{8}$/', $this->request->post['value'])) {
                        $json['success'] = false;
                        $json['message'] = 'No special symbols are allowed in the phone number, it must be ten digits long and start with 09';
                    } else {
                        if (isset($this->session->data['order_id'])) {
                            // 正確後存入 DB
                            $this->db->query("UPDATE " . DB_PREFIX . "order SET telephone = '" . $this->request->post['value'] . "' WHERE order_id = " . (int) $this->session->data['order_id']);
                        }
                    }
                    break;
                default:
                    break;
                }
            }

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));

        } catch (\Throwable $th) {
            $json['success'] = false;
            $json['message'] = $th->getMessage();

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }
    }

    // 電子地圖選擇門市
    public function express_map() {
        $ecpaylogisticSetting = $this->get_logistic_settings();

        if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'C2C') {
            $shippingMethod = [
                'fami'               => 'FAMIC2C',
                'fami_collection'    => 'FAMIC2C',
                'unimart'            => 'UNIMARTC2C',
                'unimart_collection' => 'UNIMARTC2C',
                'hilife'             => 'HILIFEC2C',
                'hilife_collection'  => 'HILIFEC2C',
                'okmart'             => 'OKMARTC2C',
                'okmart_collection'  => 'OKMARTC2C',
            ];
        } else {
            $shippingMethod = [
                'fami'               => 'FAMI',
                'fami_collection'    => 'FAMI',
                'unimart'            => 'UNIMART',
                'unimart_collection' => 'UNIMART',
                'hilife'             => 'HILIFE',
                'hilife_collection'  => 'HILIFE',
                'okmart'             => 'OKMART',
                'okmart_collection'  => 'OKMART',
            ];
        }

        $logisticSubType = explode(".", $this->session->data['shipping_method']['code']);
        $apiLogisticInfo = $this->helper->get_ecpay_logistic_api_info('map', $logisticSubType, $ecpaylogisticSetting);

        if (array_key_exists($logisticSubType[1], $shippingMethod)) {
            $al_subtype = $shippingMethod[$logisticSubType[1]];
        }

        if (!isset($al_subtype)) {
            exit;
        }

        // session fix
        $sessionId        = $_COOKIE[$this->config->get('session_name')];
        $dataBase64Encode = $this->sessionEncrypt($sessionId);

        $al_iscollection = 'N';
        $al_srvreply     = $this->url->link($this->ecpay_logistic_module_path . $this->separator . 'response_map&sid=' . $dataBase64Encode, '', $this->url_secure);

        try {
            $factory = new Factory([
                'hashKey'    => $apiLogisticInfo['hashKey'],
                'hashIv'     => $apiLogisticInfo['hashIv'],
                'hashMethod' => 'md5',
            ]);
            $autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

            $inputMap = array(
                'MerchantID'       => $apiLogisticInfo['merchantId'],
                'MerchantTradeNo'  => $this->helper->getMerchantTradeNo($this->session->data['order_id']),
                'LogisticsType'    => $this->helper->get_logistics_type($al_subtype),
                'LogisticsSubType' => $al_subtype,
                'IsCollection'     => $al_iscollection,
                'ServerReplyURL'   => $al_srvreply,
                'ExtraData'        => '',
            );

            $form_map = $autoSubmitFormService->generate($inputMap, $apiLogisticInfo['action']);

        } catch (Exception $e) {
            echo $e->getMessage();
        }
        echo $form_map;
    }

    // 電子地圖選擇門市回傳
    public function response_map_admin() {
        $ecpaylogisticSetting = $this->get_logistic_settings();
        $apiLogisticInfo = $this->helper->get_ecpay_logistic_api_info('', '', $ecpaylogisticSetting);

        $factory = new Factory([
            'hashKey' => $apiLogisticInfo['hashKey'],
            'hashIv'  => $apiLogisticInfo['hashIv'],
        ]);
        $aes_service = $factory->create(AesService::class);

        $decrypt_data = $aes_service->decrypt(str_replace(' ', '+', $this->request->get['oid']));
        $order_id     = $decrypt_data['order_id'];

        $this->load->model('checkout/order');
        if ($order = $this->model_checkout_order->getOrder($order_id)) {

            $shipping_method = explode('.', $order['shipping_method']['code']);
            if (!$this->helper->is_ecpay_cvs_logistics($shipping_method[1])) {
                exit;
            }

            $cvs_store_id      = (isset($_POST['CVSStoreID'])) ? $_POST['CVSStoreID'] : '';
            $cvs_store_name    = (isset($_POST['CVSStoreName'])) ? $_POST['CVSStoreName'] : '';
            $cvs_store_address = (isset($_POST['CVSAddress'])) ? $cvs_store_name . ' ' . $_POST['CVSAddress'] : $cvs_store_name;

            // 將門市資訊寫回訂單
            $this->db->query("UPDATE " . DB_PREFIX . "order SET shipping_address_1 = '" . $this->db->escape($cvs_store_id) . "', shipping_address_2 = '" . $this->db->escape($cvs_store_address) . "' WHERE order_id = " . (int) $order_id);

            // 新增訂單備註
            $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int) $order_id . "', order_status_id = " . $order['order_status_id'] . ", notify = '0', comment = '" . $this->db->escape('變更綠界物流超商門市' . print_r($_POST, true)) . "', date_added = NOW()");

            // 顯示門市資訊在畫面上
            echo '<section>';
            echo '  <h2>變更後門市資訊:</h2>';
            echo '  <table>';
            echo '      <tbody>';
            echo '          <tr>';
            echo '              <td>超商店舖編號:</td>';
            echo '              <td>' . $cvs_store_id . '</td>';
            echo '          </tr>';
            echo '          <tr>';
            echo '              <td>超商店舖名稱:</td>';
            echo '              <td>' . $cvs_store_name . '</td>';
            echo '          </tr>';
            echo '          <tr>';
            echo '              <td>超商店舖地址:</td>';
            echo '              <td>' . $cvs_store_address . '</td>';
            echo '          </tr>';
            echo '      </tbody>';
            echo '  </table>';
            echo '</section>';
        }
    }

    // 電子地圖選擇門市回傳
    public function response_map() {
        $order_id           = $this->helper->getOrderIdByMerchantTradeNo(['MerchantTradeNo' => $_POST['MerchantTradeNo']]);
        $shipping_address_1 = (isset($_POST['CVSStoreID'])) ? $_POST['CVSStoreID'] : '';
        $shipping_address_2 = (isset($_POST['CVSStoreName'])) ? $_POST['CVSStoreName'] : '';
        $shipping_address_2 = (isset($_POST['CVSAddress'])) ? $shipping_address_2 . ' ' . $_POST['CVSAddress'] : $shipping_address_2;

        // session restore
        $sid       = $this->request->get['sid'];
        $sessionId = $this->sessionDecrypt($sid);
        setcookie($this->config->get('session_name'), $sessionId, ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'));

        // 將門市資訊寫回訂單
        $this->db->query("UPDATE " . DB_PREFIX . "order SET shipping_address_1 = '" . $this->db->escape($shipping_address_1) . "', shipping_address_2 = '" . $this->db->escape($shipping_address_2) . "' WHERE order_id = " . (int) $order_id);

        // 取出訂單付款方式
        $order_query = $this->db->query("SELECT shipping_method FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int) $order_id . "'");
        if ($order_query->num_rows) {

            // 判斷是否為 超商取貨付款
            $shipping_method       = json_decode($order_query->row['shipping_method'], true);
            $shipping_method_array = explode('.', $shipping_method['code']);
            if (stripos($shipping_method_array[1], 'collection')) {
                // 貨到付款自動開立發票
                // Check E-Invoice model
                $ecpay_invoice_status = $this->config->get($this->ecpay_invoice_setting_prefix . 'status');

                // Get E-Invoice model name
                $invoice_module_name    = '';
                $invoice_setting_prefix = '';

                if ($ecpay_invoice_status === '1') {
                    $invoice_module_name    = $this->ecpay_invoice_module_name;
                    $invoice_setting_prefix = $this->ecpay_invoice_setting_prefix;
                }

                // E-Invoice auto issuel
                if ($invoice_module_name !== '') {

                    // 載入電子發票 Model
                    $invoice_model_name  = 'model_extension_ecpay_module_' . $invoice_module_name;
                    $invoice_module_path = 'extension/ecpay/module/' . $invoice_module_name;
                    $this->load->model($invoice_module_path);

                    // 取得自動開立設定值
                    $invoice_autoissue = $this->config->get($invoice_setting_prefix . 'autoissue');

                    if ($invoice_autoissue == 1) {
                        $this->{$invoice_model_name}->createInvoiceNo($order_id);
                    }
                }

                // 轉導回異動訂單狀態
                $this->response->redirect($this->url->link($this->ecpay_logistic_module_path . $this->separator . 'update_order_status', 'order_id=' . $order_id, $this->url_secure));
            } else {

                // 轉導ECPAY付款
                $this->response->redirect($this->url->link($this->ecpay_payment_module_path . $this->separator . 'redirect', '', $this->url_secure));
            }
        }
    }

    // Server端物流回傳網址
    public function logistics_c2c_reply() {
        try {
            $query = $this->db->query('Select * from ' . DB_PREFIX . 'ecpaylogistic_response where AllPayLogisticsID=' . $this->db->escape($this->request->post['1|AllPayLogisticsID']));
            if ($query->num_rows) {
                $aAL_info = $query->rows[0];
                $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = 1 WHERE order_id = " . (int) $aAL_info['order_id']);
                $sMsg = "綠界科技廠商管理後台更新門市通知:<br>" . print_r($this->request->post, true);
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int) $aAL_info['order_id'] . "', order_status_id = '1', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
                echo '1|OK';
            } else {
                echo '0|AllPayLogisticsID not found';
            }
        } catch (Exception $e) {
            echo '0|' . $e->getMessage();
        }
    }

    // 異動訂單狀態
    public function update_order_status() {
        $this->load->model('checkout/order');

        $order_id = $this->session->data['order_id'];

        // Update order status
        $status_id = $this->config->get('shipping_' . $this->ecpay_logistic_module_name . '_order_status');

        // Clear the cart
        $this->cart->clear();

        // Clear session data ecpayinvoice
        unset($this->session->data['ecpayinvoice']);

        $this->model_checkout_order->addHistory($order_id, $status_id);
        $this->response->redirect($this->url->link('checkout/success'));
    }

    // 依照物流過濾付款方式(EVENT)
    public function filter_payment_method(&$route, &$data, &$output) {
        $shipping_methods = array(
            'ecpaylogistic.unimart_collection',
            'ecpaylogistic.fami_collection',
            'ecpaylogistic.hilife_collection',
            'ecpaylogistic.okmart_collection',
            'ecpaylogistic.unimart',
            'ecpaylogistic.fami',
            'ecpaylogistic.hilife',
            'ecpaylogistic.okmart',
            'ecpaylogistic.post',
            'ecpaylogistic.tcat',
        );

        $payment_address = [];
        if ($this->config->get('config_checkout_payment_address') && isset($this->session->data['payment_address'])) {
            $payment_address = $this->session->data['payment_address'];
        } elseif ($this->config->get('config_checkout_shipping_address') && isset($this->session->data['shipping_address']['address_id'])) {
            $payment_address = $this->session->data['shipping_address'];
        }

        $this->load->model('checkout/payment_method');
        $payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);

        if ($payment_methods) {
            if (isset($this->session->data['shipping_method']['code'])) {

                // 判斷是否為綠界物流
                if (in_array($this->session->data['shipping_method']['code'], $shipping_methods)) {
                    // 只留下綠界金流
                    foreach ($payment_methods as $key => $payment_method) {
                        if ($payment_method['code'] != 'ecpaypayment') {
                            unset($payment_methods[$key]);
                        } else {
                            // 判斷是否為超商取貨付款
                            if (stripos($this->session->data['shipping_method']['code'], 'collection')) {
                                // 排除其他付款方式
                                foreach ($payment_method['option'] as $method_key => $ecpay_method) {
                                    if ($method_key != 'cod') {
                                        unset($payment_methods['ecpaypayment']['option'][$method_key]);
                                    }
                                }
                            } else {
                                // 排除貨到付款
                                unset($payment_methods['ecpaypayment']['option']['cod']);
                            }
                        }
                    }
                }
            }

            $json['payment_methods'] = $this->session->data['payment_methods'] = $payment_methods;
        } else {
            $json['error'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
     */

    /**
     * SessionId解密
     * @param  string $data
     * @return string
     */
    public function sessionDecrypt($data) {
        $ecpaylogisticSetting = $this->get_logistic_settings();
        $apiLogisticInfo = $this->helper->get_ecpay_logistic_api_info('', '', $ecpaylogisticSetting);
        
        $dataBase64Decode = $this->base64Decode($data);
        $dataAesDecrypt   = $this->aesDecrypt($dataBase64Decode, $apiLogisticInfo['hashKey'], $apiLogisticInfo['hashIv']);
        $sessionId        = $this->urlDecode($dataAesDecrypt);

        return $sessionId;
    }

    /**
     * SessionId加密
     * @param  string $sessionId
     * @return string
     */
    public function sessionEncrypt($sessionId) {
        $ecpaylogisticSetting = $this->get_logistic_settings();
        $apiLogisticInfo = $this->helper->get_ecpay_logistic_api_info('', '', $ecpaylogisticSetting);

        $dataEncrypt      = $this->aesEncrypt($sessionId, $apiLogisticInfo['hashKey'], $apiLogisticInfo['hashIv']);
        $dataBase64Encode = $this->base64Encode($dataEncrypt);
        $dataUrlEncode    = $this->urlEncode($dataBase64Encode);

        return $dataUrlEncode;
    }

    /**
     * AES 解密
     * @param  string $data
     * @param  string $key
     * @param  string $iv
     * @return string
     */
    public function aesDecrypt($data, $key, $iv) {
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted;
    }

    /**
     * AES 加密
     * @param  string $data
     * @param  string $key
     * @param  string $iv
     * @return string
     */
    public function aesEncrypt($data, $key, $iv) {
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $encrypted;
    }

    /**
     * Base64編碼
     * @param  string $encode
     * @return array
     */
    public function base64Encode($data) {
        return base64_encode($data);
    }

    /**
     * Base64解碼
     * @param  string $encoded
     * @return array
     */
    public function base64Decode($encoded) {
        return base64_decode($encoded);
    }

    /**
     * urlencode
     * @param  string $data
     * @return string
     */
    public function urlEncode($data) {
        return urlencode($data);

    }

    /**
     * urldecode
     * @param  string $encoded
     * @return string
     */
    public function urlDecode($encoded) {
        return urldecode($encoded);
    }

    public function get_logistic_settings() {
		$ecpaylogisticSetting = array();
        $sFieldName  = 'code';
        $sFieldValue = 'shipping_' . $this->ecpay_logistic_module_name;
        $get_ecpaylogistic_setting_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `" . $sFieldName . "` = '" . $sFieldValue . "'");
        foreach ($get_ecpaylogistic_setting_query->rows as $value) {
            $ecpaylogisticSetting[$value["key"]] = $value["value"];
        }

		return $ecpaylogisticSetting;
	}
}
?>