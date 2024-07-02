<?php
namespace Opencart\Catalog\Controller\Extension\Ecpay\Module;

class EcpayInvoice extends \Opencart\System\Engine\Controller {

	private $module_name = 'ecpayinvoice';
    private $lang_prefix = '';
    private $module_path = '';
    private $id_prefix = '';
    private $setting_prefix = '';
    private $model_name = '';
    private $name_prefix = '';
    private $helper = null;
    private $url_secure = true;

	// Constructor
	public function __construct($registry) {
		parent::__construct($registry);

        // Helper
        require_once DIR_EXTENSION . 'ecpay/system/library/EcpayInvoiceHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayInvoiceHelper;

		// Set the variables
		$this->lang_prefix = $this->module_name .'_';
        $this->id_prefix = 'module-' . $this->module_name;
        $this->setting_prefix = 'module_' . $this->module_name . '_';
        $this->module_path = 'extension/ecpay/module/' . $this->module_name;
        $this->model_name = 'model_extension_ecpay_module_' . $this->module_name;
        $this->name_prefix = 'module_' . $this->module_name;
        $this->load->model($this->module_path);
	}

	public function index() {
		$this->load->language($this->module_path);

		// 判斷電子發票模組是否啟用 1.啟用 0.未啟用
        $status = $this->config->get($this->setting_prefix . 'status');

        $data['status'] = $status ;
        $data['text_title'] = $this->language->get($this->module_name . '_text_title');

		// Load the template
		return $this->load->view($this->module_path, $data);
	}

    public function checkoutView(&$route, &$data, &$output) {

        $this->load->language($this->module_path);

        // 判斷電子發票模組是否啟用 1.啟用 0.未啟用
        $configStatus = $this->config->get($this->setting_prefix . 'status');

        if ($configStatus) {
            $data = [];
            $data['status'] = false;

            // 取得 session 金流
            if (isset($this->session->data['payment_method'])) {
                $paymentMethod = explode('.', $this->session->data['payment_method']['code']);

                $data['status'] = ($paymentMethod[0] == 'ecpaypayment');
            }

            $this->session->data['ecpayinvoice']['invoice_type']    = ($this->session->data['ecpayinvoice']['invoice_type']) ?? '1';
            $this->session->data['ecpayinvoice']['carrier_type']    = ($this->session->data['ecpayinvoice']['carrier_type']) ?? '1';
            $this->session->data['ecpayinvoice']['carrier_num']     = ($this->session->data['ecpayinvoice']['carrier_num']) ?? '';
            $this->session->data['ecpayinvoice']['love_code']       = ($this->session->data['ecpayinvoice']['love_code']) ?? '';
            $this->session->data['ecpayinvoice']['uniform_numbers'] = ($this->session->data['ecpayinvoice']['uniform_numbers']) ?? '';
            $this->session->data['ecpayinvoice']['customer_company'] = ($this->session->data['ecpayinvoice']['customer_company']) ?? '';

            $data['text_title'] = $this->language->get($this->lang_prefix . 'text_title');
            $data['invoice_type']    = $this->session->data['ecpayinvoice']['invoice_type'];
            $data['carrier_type']    = $this->session->data['ecpayinvoice']['carrier_type'];
            $data['carrier_num']     = $this->session->data['ecpayinvoice']['carrier_num'];
            $data['love_code']       = $this->session->data['ecpayinvoice']['love_code'];
            $data['uniform_numbers'] = $this->session->data['ecpayinvoice']['uniform_numbers'];
            $data['customer_company'] = $this->session->data['ecpayinvoice']['customer_company'];

            // 欄位驗證
            if (($data['invoice_type'] == '1' || $data['invoice_type'] == '2') && ($data['carrier_type'] == '3' || $data['carrier_type'] == '4')) {
                $validateResult = $this->validateInvoiceInfo(['key' => 'carrier_num', 'value' => $data['carrier_num']]);
                $data['validate_carrier_num'] = $validateResult;
            }
            if ($data['invoice_type'] == '2') {
                $validateResult = $this->validateInvoiceInfo(['key' => 'uniform_numbers', 'value' => $data['uniform_numbers']]);
                $data['validate_uniform_numbers'] = $validateResult;
                $validateResult = $this->validateInvoiceInfo(['key' => 'customer_company', 'value' => $data['customer_company']]);
                $data['validate_customer_company'] = $validateResult;
            }
            if ($data['invoice_type'] == '3') {
                $validateResult = $this->validateInvoiceInfo(['key' => 'love_code', 'value' => $data['love_code']]);
                $data['validate_love_code'] = $validateResult;
            }

            $template = $this->load->view($this->module_path, $data);
            $output .= $template;
        }
    }

    public function changeData() {
        $this->changeSessionData($this->request->post);

        // 欄位驗證
        $error = $this->validateInvoiceInfo($this->request->post);
        if (empty($error)) {
            $json['success'] = 'Success';
        } else {
            $json['error'] = $error;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function changeSessionData($request) {

        $this->session->data['ecpayinvoice'][$request['key']] = $request['value'];

        // 更新其他相關欄位
        switch ($request['key']) {
            case 'invoice_type':
                $this->session->data['ecpayinvoice']['carrier_type']         = ($request['value'] == '1' || $request['value'] == '2') ? '1' : '0';
                $this->session->data['ecpayinvoice']['uniform_numbers']      = '';
                $this->session->data['ecpayinvoice']['customer_company']     = '';
                $this->session->data['ecpayinvoice']['love_code']            = '';
                $this->session->data['ecpayinvoice']['carrier_num']          = '';
                break;
            case 'carrier_type':
                if ($request['value'] == '1' || $request['value'] == '2') {
                    $this->session->data['ecpayinvoice']['uniform_numbers']  = '';
                    $this->session->data['ecpayinvoice']['customer_company'] = '';
                    $this->session->data['ecpayinvoice']['carrier_num']      = '';
                }
                break;
        }
    }

    public function validateInvoiceInfo($data) {
        $this->load->language($this->module_path);

        $error = [];
        $result = [
            'code' => '1',
            'msg' => ''
        ];

        switch ($data['key']) {
            case 'uniform_numbers':
                $result = $this->model_extension_ecpay_module_ecpayinvoice->checkUniformNumbers($data['value']);
                break;
            case 'customer_company':
                $result = $this->model_extension_ecpay_module_ecpayinvoice->checkCustomerCompany($data['value'], $this->session->data['ecpayinvoice']['carrier_type']);
                break;
            case 'love_code':
                $result = $this->model_extension_ecpay_module_ecpayinvoice->checkLoveCode($data['value'], true);
                break;
            case 'carrier_num':
                if ($this->session->data['ecpayinvoice']['carrier_type'] == '3') {
                    $result = $this->model_extension_ecpay_module_ecpayinvoice->checkCitizenDigitalCertificate($data['value']);
                } elseif ($this->session->data['ecpayinvoice']['carrier_type'] == '4') {
                    $result = $this->model_extension_ecpay_module_ecpayinvoice->checkPhoneBarcode($data['value'], true);
                }
                break;
        }

        if ($result['code'] !== '1') {
            $error[$data['key']] = $this->language->get($result['msg']);
        }

        return $error;
    }

    public function validateData() {
        $this->load->language($this->module_path);

        $json = [];
        $result = [
            'code' => '1',
            'msg' => ''
        ];
        $ecpayinvoiceData = $this->request->post;

        switch ($ecpayinvoiceData['invoice_type']) {
            case '2':
                // invoice type is Company

                // 驗證統一編號
                $result = $this->model_extension_ecpay_module_ecpayinvoice->checkUniformNumbers($ecpayinvoiceData['uniform_numbers']);
                if ($result['code'] !== '1') {
                    $json['error']['uniform-numbers'] = $this->language->get($result['msg']);
                }

                // 驗證公司行號
                $result = $this->model_extension_ecpay_module_ecpayinvoice->checkCustomerCompany($ecpayinvoiceData['customer_company'], $ecpayinvoiceData['carrier_type']);
                if ($result['code'] !== '1') {
                    $json['error']['customer-company'] = $this->language->get($result['msg']);
                }

                // 驗證手機條碼
                if ($ecpayinvoiceData['carrier_type'] == '4') {
                    $result = $this->model_extension_ecpay_module_ecpayinvoice->checkPhoneBarcode($ecpayinvoiceData['carrier_num'], true);
                    if ($result['code'] !== '1') {
                        $json['error']['carrier-num'] = $this->language->get($result['msg']);
                    }
                }
                break;
            case '3':
                // invoice type is Donation
                $result = $this->model_extension_ecpay_module_ecpayinvoice->checkLoveCode($ecpayinvoiceData['love_code'], true);
                if ($result['code'] !== '1') {
                    $json['error']['love-code'] = $this->language->get($result['msg']);
                }
                break;
            default:
                // invoice type is Individual

                // 自然人憑證
                if ($ecpayinvoiceData['carrier_type'] == '3') {
                    $result = $this->model_extension_ecpay_module_ecpayinvoice->checkCitizenDigitalCertificate($ecpayinvoiceData['carrier_num']);

                }

                // 手機載具
                if ($ecpayinvoiceData['carrier_type'] == '4') {
                    $result = $this->model_extension_ecpay_module_ecpayinvoice->checkPhoneBarcode($ecpayinvoiceData['carrier_num'], true);
                }

                // 驗證結果
                if ($result['code'] !== '1') {
                    $json['error']['carrier-num'] = $this->language->get($result['msg']);
                }
                break;
        }

        if (!$json) {
            $this->load->model('checkout/order');
            $json['success'] = 'Success';
        }

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
    }

    public function saveInvoiceField() {
        $json = [];

        // 判斷電子發票模組是否啟用 1.啟用 0.未啟用
        $status = $this->config->get($this->setting_prefix . 'status');

        if ($status && isset($this->session->data['order_id'])) {

            // 確認是否已經存過發票資訊
            $orderId = (int)$this->session->data['order_id'];
		    $invoiceInfo = $this->model_extension_ecpay_module_ecpayinvoice->getInvoiceInfo($orderId);

            if (!$invoiceInfo) {
                $createdAt   = time() ;
                $invoiceData = $this->request->post;

                // 新增發票資訊
                $this->db->query("INSERT INTO `" . DB_PREFIX . "invoice_info` (`order_id`, `love_code`, `company_write`, `customer_company`, `invoice_type`, `carrier_type`, `carrier_num`, `createdate`) VALUES ('" . $orderId . "', '" . $this->db->escape($invoiceData['love_code']) . "', '" . $this->db->escape($invoiceData['uniform_numbers']) . "', '" . $this->db->escape($invoiceData['customer_company']) . "', '" . $this->db->escape($invoiceData['invoice_type']) . "', '" . $this->db->escape($invoiceData['carrier_type']) . "', '" . $this->db->escape($invoiceData['carrier_num']) . "', '" . $createdAt . "' )" );
            }
        }

        if (!$json) {
            $this->load->model('checkout/order');
            $json['success'] = 'Success';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
