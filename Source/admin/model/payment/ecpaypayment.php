<?php

namespace Opencart\Admin\Model\Extension\Ecpay\Payment;

class Ecpaypayment extends \Opencart\System\Engine\Model {

    private $module_name = 'ecpaypayment';
	private $prefix = 'payment_ecpaypayment_';

    // install
    public function install() {

        // card_no4 記錄信用卡後四碼提供電子發票開立使用
        // response_count AIO 回應次數

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_extend` (
              `order_id` INT(11) DEFAULT '0' NOT NULL,
              `card_no4` INT(4) DEFAULT '0' NOT NULL,
              `response_count` TINYINT(1) DEFAULT '0' NOT NULL,
              `createdate` INT(10) DEFAULT '0' NOT NULL
            ) DEFAULT COLLATE=utf8_general_ci;");

		// 記錄訂單額外資訊
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ecpay_order_extend` (
                `order_id` INT(11) DEFAULT '0' NOT NULL,
                `goods_weight` DECIMAL(15,3) NOT NULL DEFAULT '0.000',
                `createdate` INT(10) DEFAULT '0' NULL
            ) DEFAULT COLLATE=utf8_general_ci;"
        );

        // 後台設定頁欄位預設值
        $sFieldName = 'code';
		$sFieldValue = 'payment_' . $this->module_name;

        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "merchant_id' , `value` = '3002607';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hash_key' , `value` = 'pwFHCqoQZGmho4w6';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hash_iv' , `value` = 'EkRm7iFT261dpevs';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "create_status' , `value` = '1';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "success_status' , `value` = '15';");
    }

    // uninstall
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "order_extend`;");
    }
}