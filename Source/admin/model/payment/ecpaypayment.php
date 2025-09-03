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
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "test_mode' , `value` = '1';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "create_status' , `value` = '1';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "success_status' , `value` = '15';");

        // 紀錄綠界付款資訊
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ecpay_payment_response_info` (
            `id`                     INT(11)      NOT NULL AUTO_INCREMENT,
            `order_id`              INT(11)      NOT NULL,
            `payment_method`        VARCHAR(60)  NOT NULL,
            `merchant_trade_no`     VARCHAR(60)  NOT NULL DEFAULT '',
            `payment_status`        INT(10)      NOT NULL DEFAULT 0,
            `is_completed_duplicate` INT(1)       NOT NULL DEFAULT 0,
            `MerchantID`           VARCHAR(10)   NULL,
            `MerchantTradeNo`      VARCHAR(20)   NULL,
            `StoreID`              VARCHAR(20)   NULL,
            `RtnCode`              INT(10)       NULL,
            `RtnMsg`               VARCHAR(200)  NULL,
            `TradeNo`              VARCHAR(20)   NULL,
            `TradeAmt`             INT(10)       NULL,
            `PaymentDate`          VARCHAR(20)   NULL,
            `PaymentType`          VARCHAR(20)   NULL,
            `PaymentTypeChargeFee` INT(10)       NULL,
            `PlatformID`           VARCHAR(20)   NULL,
            `TradeDate`            VARCHAR(20)   NULL,
            `SimulatePaid`         INT(1)        NULL,
            `CustomField1`         VARCHAR(50)   NULL,
            `CustomField2`         VARCHAR(50)   NULL,
            `CustomField3`         VARCHAR(50)   NULL,
            `CustomField4`         VARCHAR(50)   NULL,
            `CheckMacValue`        VARCHAR(200)  NULL,
            `eci`                  INT(10)       NULL,
            `card4no`              VARCHAR(4)    NULL,
            `card6no`              VARCHAR(6)    NULL,
            `process_date`         VARCHAR(20)   NULL,
            `auth_code`            VARCHAR(6)    NULL,
            `stage`                INT(10)       NULL,
            `stast`                INT(10)       NULL,
            `red_dan`              INT(10)       NULL,
            `red_de_amt`           INT(10)       NULL,
            `red_ok_amt`           INT(10)       NULL,
            `red_yet`              INT(10)       NULL,
            `gwsr`                 INT(10)       NULL,
            `PeriodType`           VARCHAR(1)    NULL,
            `Frequency`            INT(10)       NULL,
            `ExecTimes`            INT(10)       NULL,
            `amount`               INT(10)       NULL,
            `ProcessDate`          VARCHAR(20)   NULL,
            `AuthCode`             VARCHAR(6)    NULL,
            `FirstAuthAmount`      INT(10)       NULL,
            `TotalSuccessTimes`    INT(10)       NULL,
            `BankCode`             VARCHAR(3)    NULL,
            `vAccount`             VARCHAR(16)   NULL,
            `ATMAccNo`             VARCHAR(5)    NULL,
            `ATMAccBank`           VARCHAR(3)    NULL,
            `WebATMBankName`       VARCHAR(10)   NULL,
            `WebATMAccNo`          VARCHAR(5)    NULL,
            `WebATMAccBank`        VARCHAR(3)    NULL,
            `PaymentNo`            VARCHAR(14)   NULL,
            `ExpireDate`           VARCHAR(20)   NULL,
            `Barcode1`             VARCHAR(20)   NULL,
            `Barcode2`             VARCHAR(20)   NULL,
            `Barcode3`             VARCHAR(20)   NULL,
            `BNPLTradeNo`          VARCHAR(64)   NULL,
            `BNPLInstallment`      VARCHAR(2)    NULL,
            `TWQRTradeNo`          VARCHAR(64)   NULL,
            `response_count`       TINYINT(1)    NOT NULL DEFAULT '0',
            `updated_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) DEFAULT COLLATE=utf8_general_ci;");
    }

    // uninstall
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "order_extend`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ecpay_order_extend`;");
    }
}