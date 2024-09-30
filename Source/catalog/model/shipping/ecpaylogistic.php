<?php
namespace Opencart\Catalog\Model\Extension\Ecpay\Shipping;

class EcpayLogistic extends \Opencart\System\Engine\Model {

	private $module_name = 'ecpaylogistic';
	private $module_path = '';
	private $model_name = '';
	private $lang_prefix = '';
	private $setting_prefix = '';
	private $helper = null;
	private $url_secure = true;

	private $payment_module_name = 'ecpaypayment';
	private $payment_module_path = '';
	private $payment_model_name = '';

	// Constructor
	public function __construct($registry) {
		parent::__construct($registry);

		// Set the variables
		$this->module_path = 'extension/ecpay/shipping/' . $this->module_name;
		$this->model_name = 'model_extension_ecpay_shipping_' . $this->module_name;
		$this->lang_prefix = $this->module_name .'_';
		$this->setting_prefix = 'shipping_' . $this->module_name . '_';

		$this->payment_module_path = 'extension/ecpay/payment/' . $this->module_name;
		$this->payment_model_name = 'model_extension_ecpay_payment_' . $this->payment_module_name;

		require_once DIR_EXTENSION . 'ecpay/system/library/EcpayLogisticHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayLogisticHelper;
	}

	public function getQuote($address) {
		$this->load->language($this->module_path);

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($this->setting_prefix . 'geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if (!$this->config->get($this->setting_prefix . 'geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$ecpaylogisticSetting = array();
		$sFieldName  = 'code';
		$sFieldValue = 'shipping_' . $this->module_name;
		$get_ecpaylogistic_setting_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `" . $sFieldName . "` = '" . $sFieldValue . "'");
		foreach ($get_ecpaylogistic_setting_query->rows as $value) {
			$ecpaylogisticSetting[$value["key"]] = $value["value"];
		}

		// 訂單金額範圍
		if ($this->cart->getSubTotal() < $ecpaylogisticSetting[$this->setting_prefix . 'min_amount'] || $this->cart->getSubTotal() > $ecpaylogisticSetting[$this->setting_prefix . 'max_amount']) {
			$status = false;
		}
		// 超商取貨金額範圍1~20000元
		$cvsStatus = true;
		if ($this->cart->getSubTotal() > 20000) {
			$cvsStatus = false;
		}
		// 免運費金額
		$isFreeShipping = false;
		if ($this->cart->getSubTotal() >= $ecpaylogisticSetting[$this->setting_prefix . 'free_shipping_amount']) {
			$isFreeShipping = true;
		}

		if ($status) {
			// shipping_method view 所需的額外資訊
			$Extra = array();
			// 定義 ecpaylogistic-control-area 的位置
			$Extra['last_ecpaylogistic_shipping_code'] = '';
			// 語系
			$Extra['text_choice'] = $this->language->get('text_choice');
			$Extra['text_rechoice'] = $this->language->get('text_rechoice');
			$Extra['text_store_name'] = $this->language->get('text_store_name');
			$Extra['text_store_address'] = $this->language->get('text_store_address');
			$Extra['text_store_tel'] = $this->language->get('text_store_tel');
			$Extra['text_store_info'] = $this->language->get('text_store_info');
			$Extra['error_no_storeinfo'] = $this->language->get('error_no_storeinfo');

			if ($ecpaylogisticSetting[$this->setting_prefix . 'unimart_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'unimart_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['unimart'] = array(
						'code'         => 'ecpaylogistic.unimart',
						'name'        => $this->language->get('text_unimart'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'unimart';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'unimart_collection_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'unimart_collection_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['unimart_collection'] = array(
						'code'         => 'ecpaylogistic.unimart_collection',
						'name'        => $this->language->get('text_unimart_collection'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'unimart_collection';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'fami_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'fami_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['fami'] = array(
						'code'         => 'ecpaylogistic.fami',
						'name'        => $this->language->get('text_fami'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'fami';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'fami_collection_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'fami_collection_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['fami_collection'] = array(
						'code'         => 'ecpaylogistic.fami_collection',
						'name'        => $this->language->get('text_fami_collection'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'fami_collection';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'hilife_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'hilife_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['hilife'] = array(
						'code'         => 'ecpaylogistic.hilife',
						'name'        => $this->language->get('text_hilife'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'hilife';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'hilife_collection_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'hilife_collection_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['hilife_collection'] = array(
						'code'         => 'ecpaylogistic.hilife_collection',
						'name'        => $this->language->get('text_hilife_collection'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'hilife_collection';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'okmart_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'okmart_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['okmart'] = array(
						'code'         => 'ecpaylogistic.okmart',
						'name'        => $this->language->get('text_okmart'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'okmart';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'okmart_collection_status'] && $cvsStatus) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'okmart_collection_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['okmart_collection'] = array(
						'code'         => 'ecpaylogistic.okmart_collection',
						'name'        => $this->language->get('text_okmart_collection'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'okmart_collection';
			}

			if ($ecpaylogisticSetting[$this->setting_prefix . 'post_status']) {
                // 重量
                $weight = $this->cart->getWeight();
				$weight_class = $this->config->get('config_weight_class_id');
				$weight_level = $this->helper->cal_home_post_shipping_cost($weight, $weight_class);

				// 中華郵政重量限制 0~20公斤
				if ($weight > 0 && $weight_level !== '5') {
					$weightCost = $ecpaylogisticSetting[$this->setting_prefix . 'post_' . $weight_level . '_fee'];
					$shipping_cost = ($isFreeShipping) ? 0 : $weightCost;
					$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
					$quote_data['post'] = array(
							'code'         => 'ecpaylogistic.post',
							'name'        => $this->language->get('text_post'),
							'cost'         => $shipping_cost,
							'tax_class_id' => 0,
							'text'         => $quote_text,
					);
					$Extra['last_ecpaylogistic_shipping_code'] = 'post';
				}
            }

			if ($ecpaylogisticSetting[$this->setting_prefix . 'tcat_status']) {
				$shipping_cost = ($isFreeShipping) ? 0 : $ecpaylogisticSetting[$this->setting_prefix . 'tcat_fee'];
				$quote_text = $this->currency->format($shipping_cost, $this->session->data['currency']);
				$quote_data['tcat'] = array(
						'code'         => 'ecpaylogistic.tcat',
						'name'        => $this->language->get('text_tcat'),
						'cost'         => $shipping_cost,
						'tax_class_id' => 0,
						'text'         => $quote_text,
				);
				$Extra['last_ecpaylogistic_shipping_code'] = 'tcat';
            }

			unset($quote_text);

			if (!isset($quote_data)) {
				$status = false;
			} else {
				$quote_data[$Extra['last_ecpaylogistic_shipping_code']]['Extra'] = $Extra;
			}
		}

		$method_data = array();
		if ($status) {
			$method_data = array(
					'code'       => 'ecpaylogistic',
					'name'      => $this->language->get('heading_title'),
					'quote'      => $quote_data,
					'sort_order' => $this->config->get($this->setting_prefix . 'sort_order'),
					'extra' 	 => $Extra,
					'error'      => false
			);
		}

		return $method_data;
	}
}
