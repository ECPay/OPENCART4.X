<?php

namespace Opencart\Admin\Model\Extension\Ecpay\Shipping;

class EcpayLogistic extends \Opencart\System\Engine\Model {
	private $separator = '';
    private $prefix = 'shipping_ecpaylogistic_';
    private $module_name = 'ecpaylogistic';
    private $module_path = 'extension/ecpay/shipping/ecpaylogistic';

	public function initSeparator() {
		if (VERSION >= '4.0.2.0') {
			$this->separator = '.';
		} else {
			$this->separator = '|';
		}
	}

    public function install() {
		$this->initSeparator();

		// EVENT ADD
        $this->load->model($this->module_path);
		$this->load->model('setting/event');

		$this->model_setting_event->addEvent([
			'code' => 'ecpay_logistic_payment_method',
			'description' => '依照物流過濾付款方式',
			'trigger' => 'catalog/controller/checkout/payment_method.getMethods/after',
			'action' => $this->module_path . $this->separator . 'filter_payment_method',
			'status' => true,
			'sort_order' => 1,
		]);

		$this->model_setting_event->addEvent([
			'code' => 'ecpay_logistic_create_shipping',
			'description' => '判斷後台是否產生建立物流按鈕',
			'trigger' => 'admin/view/sale/order_info/before',
			'action' => $this->module_path . $this->separator . 'create_shipping_btn',
			'status' => true,
			'sort_order' => 1,
		]);

		$this->model_setting_event->addEvent([
			'code' => 'ecpay_logistic_print_shipping',
			'description' => '判斷後台是否顯示物流單列印按鈕',
			'trigger' => 'admin/view/sale/order_info/before',
			'action' => $this->module_path . $this->separator . 'print_shipping_btn',
			'status' => true,
			'sort_order' => 1,
		]);

		$this->model_setting_event->addEvent([
            'code' => 'ecpay_logistic_checkout_view',
            'trigger' => 'catalog/view/checkout/shipping_method/after',
            'action' => $this->module_path . $this->separator . 'add_shipping_field',
            'description' => '新增綠界物流必填欄位',
            'sort_order' => 1,
            'status' => true
        ]);

		$sFieldName = 'code';
		$sFieldValue = 'shipping_' . $this->module_name;

		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "unimart_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "fami_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hilife_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "okmart_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "post_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "tcat_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "fami_collection_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hilife_collection_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "unimart_collection_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "okmart_collection_status' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "order_status' , `value` = '1';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "mid' , `value` = '2000933';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hashkey' , `value` = 'XBERn1YOvpM9nfZc';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hashiv' , `value` = 'h1ONHk4P4yqbl5LK';");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "test_mode' , `value` = '1';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "type' , `value` = 'C2C';");

		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = 'None', `serialized` = '0'  WHERE `code` = 'config' AND `key` = 'config_session_samesite' AND `store_id` = '0'");

		// 記錄物流訂單回傳資訊
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ecpaylogistic_response` (
              `order_id` INT(11) DEFAULT '0' NOT NULL,
              `MerchantID` varchar(20) DEFAULT '0' NULL,
              `MerchantTradeNo` varchar(20) DEFAULT '0' NULL,
              `RtnCode` INT(10) DEFAULT '0' NULL,
              `RtnMsg` VARCHAR(200) DEFAULT '0' NULL,
              `AllPayLogisticsID` varchar(20) DEFAULT '0' NULL,
              `LogisticsType` varchar(20) DEFAULT '0' NULL,
              `LogisticsSubType` varchar(20) DEFAULT '0' NULL,
              `GoodsAmount` INT(10) DEFAULT '0' NULL,
              `UpdateStatusDate` varchar(20) DEFAULT '0' NULL,
              `ReceiverName` varchar(60) DEFAULT '0' NULL,
              `ReceiverPhone` varchar(20) DEFAULT '0' NULL,
              `ReceiverCellPhone` varchar(20) DEFAULT '0' NULL,
              `ReceiverEmail` varchar(50) DEFAULT '0' NULL,
              `ReceiverAddress` varchar(200) DEFAULT '0' NULL,
              `CVSPaymentNo` varchar(15) DEFAULT '0' NULL,
              `CVSValidationNo` varchar(10) DEFAULT '0' NULL,
              `BookingNote` varchar(50) DEFAULT '0' NULL,
              `createdate` INT(10) DEFAULT '0' NULL
            ) DEFAULT COLLATE=utf8_general_ci;"
        );
	}

	public function uninstall() {
		$this->initSeparator();

		$this->load->model('user/user_group');
		if (method_exists($this->user,"getGroupId")) {
			$this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', $this->module_path);
			$this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', $this->module_path);
		}

		// delete event
		$this->model_setting_event->deleteEventByCode('ecpay_logistic_payment_method');
		$this->model_setting_event->deleteEventByCode('ecpay_logistic_create_shipping');
		$this->model_setting_event->deleteEventByCode('ecpay_logistic_print_shipping');
		$this->model_setting_event->deleteEventByCode('ecpay_logistic_checkout_view');
	}
}