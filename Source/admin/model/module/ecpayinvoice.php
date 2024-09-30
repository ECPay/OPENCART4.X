<?php
namespace Opencart\Admin\Model\Extension\Ecpay\Module;

class EcpayInvoice extends \Opencart\System\Engine\Model {

	private $module_name = 'ecpayinvoice';
	private $prefix = 'module_ecpayinvoice_';

	public function install(): void {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "invoice_info` (
                `order_id`         INT(11)     DEFAULT 0  NOT NULL,
                `love_code`        VARCHAR(50) DEFAULT '' NOT NULL,
                `company_write`    VARCHAR(10) DEFAULT '' NOT NULL,
                `customer_company` VARCHAR(60) DEFAULT '' NOT NULL,
                `invoice_type`     TINYINT(2)  DEFAULT 0  NOT NULL,
                `carrier_type`     TINYINT(2)  DEFAULT 0  NOT NULL,
                `carrier_num`      VARCHAR(20) DEFAULT '' NOT NULL,
                `relate_number`    VARCHAR(30) DEFAULT '' NOT NULL,
                `random_number`    VARCHAR(4)  DEFAULT '' NOT NULL,
                `invoice_process`  TINYINT(1)  DEFAULT 0  NOT NULL,
                `createdate`       INT(10)     NOT NULL
			) DEFAULT COLLATE=utf8_general_ci;");

		// 異動電子發票欄位型態
		$this->db->query(" ALTER TABLE `" . DB_PREFIX . "order` CHANGE `invoice_no` `invoice_no` VARCHAR(10) NOT NULL DEFAULT '0'; ");
		$sFieldName = 'code';
		$sFieldValue = 'module_' . $this->module_name;
		$query = $this->db->query("SHOW COLUMNS FROM " . DB_PREFIX . "setting LIKE 'code'");
		if ($query->num_rows == 0) $sFieldName = 'group';

		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "mid' , `value` = '2000132';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hashkey' , `value` = 'ejCk326UnaZWKisg';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "hashiv' , `value` = 'q9jcZX8Ib9LM8wYk';");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "test_mode' , `value` = '1';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "autoissue' , `value` = '0';");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0 , `" . $sFieldName . "` = '" . $sFieldValue . "' , `key` = '" . $this->prefix . "status' , `value` = '0';");
	}

	public function uninstall(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "invoice_info`");
	}

	public function getInvoiceInfo(int $orderId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "invoice_info` WHERE `order_id` = '" . (int)$orderId . "'");

		if ($query->num_rows) {
			return $query->row;
		} else {
			return [];
		}
	}

	public function updateInvoiceInfo(int $orderId, array $invoiceInfo) {
		$query = '';

		foreach ($invoiceInfo as $key => $value) {
			if ($query !== '') {
				$query .= ",";
			}
			$query .= $key . " = '" . $value . "'";
		}

		if ($query !== '') {
			$this->db->query("UPDATE `" . DB_PREFIX . "invoice_info` SET " . $query . " WHERE order_id = '" . (int)$orderId . "'");
		}
	}
}