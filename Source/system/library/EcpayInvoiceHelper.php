<?php
namespace Opencart\System\Library;

require_once DIR_EXTENSION . 'ecpay/system/library/ModuleHelper.php';

use Opencart\System\Library\ModuleHelper;

class EcpayInvoiceHelper extends ModuleHelper
{
    private $module_name = 'ecpayinvoice';
    private $setting_prefix;
	
    /**
     * 發票開立方式代碼-個人
     */
    const INVOICE_TYPE_PERSONAL = 1;

    /**
     * 發票開立方式代碼-公司
     */
    const INVOICE_TYPE_COMPANY = 2;

    /**
     * 發票開立方式代碼-捐贈
     */
    const INVOICE_TYPE_DONATE = 3;

    /**
     * 載具類別代碼-索取紙本
     */
    const INVOICE_CARRIER_TYPE_PAPER = 1;

    /**
     * 載具類別代碼-雲端發票(中獎寄送紙本)
     */
    const INVOICE_CARRIER_TYPE_CLOUD = 2;

    /**
     * 載具類別代碼-自然人憑證
     */
    const INVOICE_CARRIER_TYPE_NATURAL_PERSON_ID = 3;

    /**
     * 載具類別代碼-手機條碼
     */
    const INVOICE_CARRIER_TYPE_MOBILE_BARCODE = 4;

    /**
     * 發票開立方式
     *
     * @var array
     */
    public $invoiceType = [
        self::INVOICE_TYPE_PERSONAL	=> '個人',
        self::INVOICE_TYPE_COMPANY	=> '公司',
        self::INVOICE_TYPE_DONATE	=> '捐贈',
    ];

    /**
     * 載具類別
     *
     * @var array
     */
    public $invoiceCarrierType = [
        self::INVOICE_CARRIER_TYPE_PAPER	         => '索取紙本',
        self::INVOICE_CARRIER_TYPE_CLOUD	         => '雲端發票(中獎寄送紙本)',
        self::INVOICE_CARRIER_TYPE_NATURAL_PERSON_ID => '自然人憑證',
        self::INVOICE_CARRIER_TYPE_MOBILE_BARCODE	 => '手機條碼',
    ];

	/**
     * EcpayInvoiceHelper constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setting_prefix = 'module_' . $this->module_name . '_';
    }

    /**
     * 取得綠界發票 API 介接資訊
     *
     * @param  string $action
     * @param  string $merchant_id
     * @return array  $api_info
     */
    public function get_ecpay_invoice_api_info($action = '', $test_mode = false) {
		$api_info = [
			'action' => '',
		];

        // API URL
		if ($test_mode) {
            $api_info['merchantId'] = '2000132';
            $api_info['hashKey'] = 'ejCk326UnaZWKisg';
            $api_info['hashIv'] = 'q9jcZX8Ib9LM8wYk';

			switch ($action) {
				case 'check_Love_code':
					$api_info['action'] = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/CheckLoveCode';
					break;
				case 'check_barcode':
					$api_info['action'] = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/CheckBarcode';
					break;
				case 'issue':
					$api_info['action'] = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue';
					break;
				case 'delay_issue':
					$api_info['action'] = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/DelayIssue';
					break;
				case 'invalid':
					$api_info['action'] = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Invalid';
					break;
				case 'cancel_delay_issue':
					$api_info['action'] = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/CancelDelayIssue';
					break;
				default:
					break;
			}
		} else {
            $api_info['merchantId'] = $this->config->get($this->setting_prefix . 'merchant_id');
            $api_info['hashKey'] = $this->config->get($this->setting_prefix . 'hash_key');
            $api_info['hashIv'] = $this->config->get($this->setting_prefix . 'hash_iv');

			switch ($action) {
				case 'check_Love_code':
					$api_info['action'] = 'https://einvoice.ecpay.com.tw/B2CInvoice/CheckLoveCode';
					break;
				case 'check_barcode':
					$api_info['action'] = 'https://einvoice.ecpay.com.tw/B2CInvoice/CheckBarcode';
					break;
				case 'issue':
					$api_info['action'] = 'https://einvoice.ecpay.com.tw/B2CInvoice/Issue';
					break;
				case 'delay_issue':
					$api_info['action'] = 'https://einvoice.ecpay.com.tw/B2CInvoice/DelayIssue';
					break;
				case 'invalid':
					$api_info['action'] = 'https://einvoice.ecpay.com.tw/B2CInvoice/Invalid';
					break;
				case 'cancel_delay_issue':
					$api_info['action'] = 'https://einvoice.ecpay.com.tw/B2CInvoice/CancelDelayIssue';
					break;
				default:
					break;
			}
		}

		return $api_info;
  	}

	/**
     * 取得發票自訂編號
     *
     * @param  string $order_id
     * @param  string $order_prefix
     * @return string
     */
    public function get_relate_number($order_id, $order_prefix = '') {
		$relate_no = $order_prefix . substr(str_pad($order_id, 8, '0', STR_PAD_LEFT), 0, 8) . 'SN' . substr(hash('sha256', (string) time()), -5);
		return substr($relate_no, 0, 20);
  	}
}
