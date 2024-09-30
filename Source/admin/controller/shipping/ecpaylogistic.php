<?php
namespace Opencart\Admin\Controller\Extension\Ecpay\Shipping;

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\AesService;

class EcpayLogistic extends \Opencart\System\Engine\Controller
{
	private $separator = '';
	private $error = array();
	private $module_name = 'ecpaylogistic';
	private $prefix = 'shipping_ecpaylogistic_';
	private $module_code = '';
	private $module_path = 'extension/ecpay/shipping/ecpaylogistic';
	private $extension_route = 'extension/ecpay/shipping';
	private $url_secure;
    private $ecpay_logistic_model_name = '';


	// Constructor
	public function __construct($registry) {
		parent::__construct($registry);
		if (VERSION >= '4.0.2.0') {
			$this->separator = '.';
		} else {
			$this->separator = '|';
		}

		$this->url_secure = ( empty($this->config->get('config_secure')) ) ? false : true ;

        $this->ecpay_logistic_model_name = 'model_extension_ecpay_shipping_' . $this->module_name;
		$this->module_code = 'shipping_' . $this->module_name;
        $this->load->model($this->module_path);

		require_once DIR_EXTENSION . 'ecpay/system/library/EcpayLogisticHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayLogisticHelper;
	}

	// install
    public function install() {
        $this->{$this->ecpay_logistic_model_name}->install();
    }

    // uninstall
    public function uninstall() {
		$this->{$this->ecpay_logistic_model_name}->uninstall();

        $this->load->model('setting/setting');
        $this->load->model('setting/extension');

        $this->model_setting_setting->deleteSetting($this->request->get['extension']);
        $this->model_setting_extension->uninstall($this->module_code, $this->request->get['extension']);
    }

	public function index() {
		$this->load->language($this->module_path);
		$heading_title = $this->language->get('heading_title');
		$this->document->setTitle($heading_title);
		$this->load->model('setting/setting');

		// Token
		$token = $this->session->data['user_token'];

		$data['heading_title'] = $heading_title;
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_general'] = $this->language->get('text_general');
		$data['text_unimart_collection'] = $this->language->get('text_unimart_collection');
		$data['text_fami_collection'] = $this->language->get('text_fami_collection');
		$data['text_unimart'] = $this->language->get('text_unimart');
		$data['text_fami'] = $this->language->get('text_fami');
		$data['text_hilife_collection'] = $this->language->get('text_hilife_collection');
		$data['text_hilife'] = $this->language->get('text_hilife');
		$data['text_okmart_collection'] = $this->language->get('text_okmart_collection');
		$data['text_okmart'] = $this->language->get('text_okmart');

		$data['text_sender_cellphone'] = $this->language->get('text_sender_cellphone');

		$data['entry_mid'] = $this->language->get('entry_mid');
		$data['entry_hashkey'] = $this->language->get('entry_hashkey');
		$data['entry_hashiv'] = $this->language->get('entry_hashiv');
		$data['entry_test_mode'] = $this->language->get('entry_test_mode');
        $data['entry_test_mode_info'] = $this->language->get('entry_test_mode_info');
		$data['entry_type'] = $this->language->get('entry_type');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_FreeShippingAmount'] = $this->language->get('entry_FreeShippingAmount');
		$data['entry_MinAmount'] = $this->language->get('entry_MinAmount');
		$data['entry_MaxAmount'] = $this->language->get('entry_MaxAmount');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_sender_name'] = $this->language->get('entry_sender_name');
		$data['entry_sender_cellphone'] = $this->language->get('entry_sender_cellphone');
		$data['entry_sender_zipcode'] = $this->language->get('entry_sender_zipcode');
        $data['entry_sender_address'] = $this->language->get('entry_sender_address');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['entry_UNIMART_Collection_fee'] = $this->language->get('entry_UNIMART_Collection_fee');
		$data['entry_FAMI_Collection_fee'] = $this->language->get('entry_FAMI_Collection_fee');
		$data['entry_HILIFE_Collection_fee'] = $this->language->get('entry_HILIFE_Collection_fee');
		$data['entry_OKMART_Collection_fee'] = $this->language->get('entry_OKMART_Collection_fee');
		$data['entry_UNIMART_fee'] = $this->language->get('entry_UNIMART_fee');
		$data['entry_FAMI_fee'] = $this->language->get('entry_FAMI_fee');
		$data['entry_HILIFE_fee'] = $this->language->get('entry_HILIFE_fee');
		$data['entry_OKMART_fee'] = $this->language->get('entry_OKMART_fee');
		$data['entry_POST_1_fee'] = $this->language->get('entry_POST_1_fee');
        $data['entry_POST_2_fee'] = $this->language->get('entry_POST_2_fee');
        $data['entry_POST_3_fee'] = $this->language->get('entry_POST_3_fee');
        $data['entry_POST_4_fee'] = $this->language->get('entry_POST_4_fee');
        $data['entry_TCAT_fee'] = $this->language->get('entry_TCAT_fee');

		if (isset($this->error['error_warning'])) {
			$data['error_warning'] = $this->error['error_warning'];
		} else {
			$data['error_warning'] = '';
		}

		$ecpayErrorList = array(
			'mid',
			'hashkey',
			'hashiv',
			'test_mode',
			'UNIMART_Collection_fee',
			'FAMI_Collection_fee',
			'HILIFE_Collection_fee',
			'OKMART_Collection_fee',
			'FreeShippingAmount',
			'MinAmount',
			'MaxAmount',
			'UNIMART_fee',
			'FAMI_fee',
			'HILIFE_fee',
			'OKMART_fee',
			'POST_1_fee',
            'POST_2_fee',
            'POST_3_fee',
            'POST_4_fee',
            'TCAT_fee',
			'sender_name',
			'sender_cellphone',
		);
		foreach ($ecpayErrorList as $errorName) {
			if (isset($this->error[$errorName])) {
				$data['error_' . $errorName] = $this->error[$errorName];
			} else {
				$data['error_' . $errorName] = '';
			}
		}
		unset($ecpayErrorList);

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $token, true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
            		'href' => $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=shipping', true)
		);
		$data['breadcrumbs'][] = array(
            		'text' => $heading_title,
            		'href' => $this->url->link($this->module_path, 'user_token=' . $token, true)
		);

		$data[$this->prefix . 'types'] = array();
		$data[$this->prefix . 'types'][] = array(
			'value' => 'C2C',
			'text' => 'C2C'
		);
		$data[$this->prefix . 'types'][] = array(
			'value' => 'B2C',
			'text' => 'B2C'
		);

		$data[$this->prefix . 'statuses'] = array();
		$data[$this->prefix . 'statuses'][] = array(
			'value' => '1',
			'text' => $this->language->get('text_enabled')
		);
		$data[$this->prefix . 'statuses'][] = array(
			'value' => '0',
			'text' => $this->language->get('text_disabled')
		);

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['dca_period_types'] = ['Y', 'M', 'D'];

		$data['action'] = $this->url->link(
            $this->module_path . $this->separator . 'save',
            'user_token=' . $token,
            $this->url_secure
        );
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=shipping', true);

		// Get the setting
		$settings = array(
			'mid',
			'hashkey',
			'hashiv',
			'test_mode',
			'type',
			'unimart_collection_fee',
			'fami_collection_fee',
			'hilife_collection_fee',
			'okmart_collection_fee',
			'unimart_fee',
			'fami_fee',
			'hilife_fee',
			'okmart_fee',
			'post_1_fee',
			'post_2_fee',
			'post_3_fee',
			'post_4_fee',
			'tcat_fee',
			'unimart_status',
			'fami_status',
			'hilife_status',
			'okmart_status',
			'post_status',
			'tcat_status',
			'unimart_collection_status',
			'fami_collection_status',
			'hilife_collection_status',
			'okmart_collection_status',
			'geo_zone_id',
			'status',
			'free_shipping_amount',
			'max_amount',
			'min_amount',
			'order_status',
			'sender_name',
			'sender_cellphone',
			'sender_zipcode',
			'sender_address',
		);
		foreach ($settings as $name) {
			$variable_name = $this->prefix . $name;
				if (isset($this->request->post[$variable_name])) {
				$data[$variable_name] = $this->request->post[$variable_name];
			} else {
				$data[$variable_name] = $this->config->get($variable_name);
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->module_path, $data));
	}

	public function save(){
		$this->load->language($this->module_path);
		$json = [];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$shipping_type_list = array(
				'unimart_collection',
				'fami_collection',
				'hilife_collection',
				'okmart_collection',
				'fami',
				'unimart',
				'hilife',
				'okmart',
				'post',
				'tcat',
			);

			foreach ($shipping_type_list as $type_name) {
				if ($this->request->post[$this->prefix . $type_name . '_status'] != '1') {
					if ($type_name !== 'post') {
						unset($this->request->post[$this->prefix . $type_name . '_fee']);
					} else {
						unset($this->request->post[$this->prefix . $type_name . '_1_fee']);
                        unset($this->request->post[$this->prefix . $type_name . '_2_fee']);
                        unset($this->request->post[$this->prefix . $type_name . '_3_fee']);
                        unset($this->request->post[$this->prefix . $type_name . '_4_fee']);
					}
				}
			}
			unset($shipping_type_list);

			$module_settings = $this->request->post;
			$this->model_setting_setting->editSetting('shipping_' . $this->module_name, $module_settings);

			$payment_status_name = str_replace('shipping', 'payment', $this->prefix) . 'status';
			$payment_status_value = $module_settings[$this->prefix . 'status'];
			$this->model_setting_setting->editSetting('payment_' . $this->module_name, array(
				$payment_status_name => $payment_status_value
			));

			$json['success'] = $this->language->get('text_success');
		}
		else {
			$json['error'] = $this->error;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}


	private function validate() {
		if (!$this->user->hasPermission('modify', $this->module_path)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Required fields validate
		$require_fields = array(
			'mid',
			'hashkey',
			'hashiv',
			'sender_name',
			'sender_address',
			'sender_cellphone',
			'sender_zipcode',
		);
		foreach ($require_fields as $name) {
			if (empty($this->request->post[$this->prefix . $name])) {
                $this->error['shipping-ecpaylogistic-' . $name] = $this->language->get('error_' . $name);
			}
		}
		unset($require_fields);

		$bite_sender_name = $this->helper->bite_str($this->request->post[$this->prefix . 'sender_name'],0,10);
		if ($bite_sender_name != $this->request->post[$this->prefix . 'sender_name']) {
			$this->error['shipping-ecpaylogistic-sender-name'] = $this->language->get('error_sender_name_length');
		}

		if (empty($this->request->post[$this->prefix . 'sender_cellphone'])) {
			$this->error['shipping-ecpaylogistic-sender-cellphone'] = $this->language->get('error_sender_cellphone');
		} else {
			if (!preg_match('/^09\d{8}$/', $this->request->post[$this->prefix . 'sender_cellphone'])) {
				$this->error['shipping-ecpaylogistic-sender-cellphone'] = $this->language->get('error_sender_cellphone_length');
			}
		}

		// Shipping fee validation
		$shipping_type_list = array(
			'unimart_collection' => 'UNIMART_Collection',
			'fami_collection' => 'FAMI_Collection',
			'hilife_collection' => 'HILIFE_Collection',
			'okmart_collection' => 'OKMART_Collection',
			'fami' => 'FAMI',
			'unimart' => 'UNIMART',
			'hilife' => 'HILIFE',
			'okmart' => 'OKMART',
			'post' => 'POST',
			'tcat' => 'TCAT',
		);
		foreach ($shipping_type_list as $type_name => $error_type_name) {
			if ($this->request->post[$this->prefix . $type_name . '_status'] == '1') {
				if ($type_name !== 'post') {
					if(!is_numeric($this->request->post[$this->prefix . $type_name . '_fee']) || $this->request->post[$this->prefix . $type_name . '_fee'] < 0){
						$this->error[$error_type_name . '_fee'] = $this->language->get('error_' . $error_type_name . '_fee');
					}
				} else {
					if(!is_numeric($this->request->post[$this->prefix . $type_name . '_1_fee']) || $this->request->post[$this->prefix . $type_name . '_1_fee'] < 0){
						$this->error[$error_type_name . '_1_fee'] = $this->language->get('error_' . $error_type_name . '_1_fee');
					}
					if(!is_numeric($this->request->post[$this->prefix . $type_name . '_2_fee']) || $this->request->post[$this->prefix . $type_name . '_2_fee'] < 0){
						$this->error[$error_type_name . '_2_fee'] = $this->language->get('error_' . $error_type_name . '_2_fee');
					}
					if(!is_numeric($this->request->post[$this->prefix . $type_name . '_3_fee']) || $this->request->post[$this->prefix . $type_name . '_3_fee'] < 0){
						$this->error[$error_type_name . '_3_fee'] = $this->language->get('error_' . $error_type_name . '_3_fee');
					}
					if(!is_numeric($this->request->post[$this->prefix . $type_name . '_4_fee']) || $this->request->post[$this->prefix . $type_name . '_4_fee'] < 0){
						$this->error[$error_type_name . '_4_fee'] = $this->language->get('error_' . $error_type_name . '_4_fee');
					}
				}
	        }
		}
		unset($shipping_type_list);

		if (!is_numeric($this->request->post[$this->prefix . 'min_amount']) || $this->request->post[$this->prefix . 'min_amount'] < 0){
			$this->error['shipping-ecpaylogistic-min_amount'] = $this->language->get('error_MinAmount');
		}
		if (!is_numeric($this->request->post[$this->prefix . 'free_shipping_amount']) || $this->request->post[$this->prefix . 'free_shipping_amount'] < 0){
			$this->error['shipping-ecpaylogistic-free_shipping_amount'] = $this->language->get('error_FreeShippingAmount');
		}
		if (!is_numeric($this->request->post[$this->prefix . 'max_amount']) || $this->request->post[$this->prefix . 'max_amount'] < 0 || $this->request->post[$this->prefix . 'max_amount'] <= $this->request->post[$this->prefix . 'min_amount']){
			$this->error['shipping-ecpaylogistic-max_amount'] = $this->language->get('error_MaxAmount');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}

	// 建立物流訂單
	public function create_shipping_order() {
		$ajax_return['code'] = 700;
		$ajax_return['rtn'] = '0|fail';
		$ajax_return['msg'] = '';

		$order_id = $this->request->get['order_id'];

		$ecpaylogistic_query = $this->db->query('Select * from ' . DB_PREFIX . 'ecpaylogistic_response where order_id=' . (int) $order_id);

		if (!$ecpaylogistic_query->num_rows) {
			$this->load->model('sale/order');
			$order_info = $this->model_sale_order->getOrder($order_id);

			if ($order_info) {
				$ecpaylogisticSetting = $this->get_logistic_settings();
				$logisticSubType = explode(".", $order_info['shipping_method']['code']);

				// 物流類型
				$logisticsType = $this->helper->get_ecpay_logistics_type($logisticSubType[1]);

				if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'C2C') {
					$shippingMethod = [
						'fami' => 'FAMIC2C',
						'fami_collection' => 'FAMIC2C',
						'unimart' => 'UNIMARTC2C',
						'unimart_collection' => 'UNIMARTC2C',
						'hilife' => 'HILIFEC2C',
						'hilife_collection' => 'HILIFEC2C',
						'okmart' => 'OKMARTC2C',
                        'okmart_collection' => 'OKMARTC2C',
						'post' => 'POST',
						'tcat' => 'TCAT',
					];
				}
				else {
					$shippingMethod = [
						'fami' => 'FAMI',
						'fami_collection' => 'FAMI',
						'unimart' => 'UNIMART',
						'unimart_collection' => 'UNIMART',
						'hilife' => 'HILIFE',
						'hilife_collection' => 'HILIFE',
						'post' => 'POST',
						'tcat' => 'TCAT',
					];
				}

				if (array_key_exists($logisticSubType[1], $shippingMethod)) {
					$_LogisticsSubType = $shippingMethod[$logisticSubType[1]];
				}

				$_IsCollection = 'N';
				$_CollectionAmount = 0;
				if (strpos($logisticSubType[1], "_collection") !== false) {
					$_IsCollection = 'Y';
					$_CollectionAmount = (int)ceil($order_info['total']);
				}

				$this->load->model('catalog/product');
				$goodsWeight = 0;
				$products = $this->model_sale_order->getProducts($order_id);
				$aGoods = array();
				foreach ($products as $product) {
					$aGoods[] = $product['name'] . '(' . $product['model'] . ')';

					// 計算商品重量
					$productInfo =  $this->model_catalog_product->getProduct($product['product_id']);
                    $goodsWeight += $productInfo['weight'] * $product['quantity'];
				}

				$_Goods = '網路商品一批';
				$_SenderCellPhone = '';
				if (isset($ecpaylogisticSetting[$this->prefix . 'sender_cellphone']) && !empty($ecpaylogisticSetting[$this->prefix . 'sender_cellphone'])) {
					$_SenderCellPhone = $ecpaylogisticSetting[$this->prefix . 'sender_cellphone'];
				}

				// 回傳網址
				$server_reply_url = $this->url->link($this->extension_route . '/' . $this->module_name . $this->separator . 'response');
				$server_reply_url = str_replace("admin/", "", $server_reply_url) ;

				$apiLogisticInfo  = $this->helper->get_ecpay_logistic_api_info('create', $_LogisticsSubType, $ecpaylogisticSetting);

				$MerchantTradeNo = $this->helper->getMerchantTradeNo($order_id);

				if ($logisticsType === 'CVS') {
					$logistics_c2c_reply_url = $this->url->link($this->extension_route . '/' . $this->module_name . $this->separator . 'logistics_c2c_reply');
					$logistics_c2c_reply_url = str_replace("admin/", "", $logistics_c2c_reply_url);

					$inputLogisticOrder = array(
                        'MerchantID' => $apiLogisticInfo['merchantId'],
                        'MerchantTradeNo' => $MerchantTradeNo,
                        'MerchantTradeDate' => date('Y/m/d H:i:s'),
                        'LogisticsType' => $logisticsType,
                        'LogisticsSubType' => $_LogisticsSubType,
                        'GoodsAmount' => (int)ceil($order_info['total']),
                        'CollectionAmount' => $_CollectionAmount,
                        'IsCollection' => $_IsCollection,
                        'GoodsName' => $_Goods,
                        'SenderName' => $ecpaylogisticSetting[$this->prefix . 'sender_name'],
                        'SenderCellPhone' => $_SenderCellPhone,
                        'ReceiverName' => $order_info['shipping_firstname'] . $order_info['shipping_lastname'],
                        'ReceiverCellPhone' => $order_info['telephone'],
                        'ReceiverEmail' => $order_info['email'],
                        'ServerReplyURL' => $server_reply_url,
                        'LogisticsC2CReplyURL' => $logistics_c2c_reply_url,
                        'Remark' => 'ecpay_module_opencart',
                        'ReceiverStoreID' => $order_info['shipping_address_1'],
                        'ReturnStoreID' => $order_info['shipping_address_1']
                    );
				}
				else if ($logisticsType === 'HOME') {

					// 收件地址
                    $receiverAddress = $order_info['shipping_city'] . $order_info['shipping_address_1'] . $order_info['shipping_address_2'];

					// 取得訂單商品重量
					$ecpayOrderExtendQuery = $this->db->query('Select * from ' . DB_PREFIX . 'ecpay_order_extend where order_id=' . (int)$order_id);
					if (!empty($ecpayOrderExtendQuery->row['goods_weight'])) {
						$goodsWeight = $ecpayOrderExtendQuery->row['goods_weight'];
					}

                    $inputLogisticOrder = array(
                        'MerchantID' => $ecpaylogisticSetting[$this->prefix . 'mid'],
                        'MerchantTradeNo' => $MerchantTradeNo,
                        'MerchantTradeDate' => date('Y/m/d H:i:s'),
                        'LogisticsType' => $logisticsType,
                        'LogisticsSubType' => $_LogisticsSubType,
                        'GoodsAmount' => (int)ceil($order_info['total']),
                        'GoodsName' => $_Goods,
                        'GoodsWeight' => $goodsWeight,
                        'SenderName' => $ecpaylogisticSetting[$this->prefix . 'sender_name'],
                        'SenderCellPhone' => $_SenderCellPhone,
                        'SenderZipCode' => $ecpaylogisticSetting[$this->prefix . 'sender_zipcode'],
                        'SenderAddress' => $ecpaylogisticSetting[$this->prefix . 'sender_address'],
                        'ReceiverName' => $order_info['shipping_firstname'] . $order_info['shipping_lastname'],
                        'ReceiverCellPhone' => $order_info['telephone'],
                        'ReceiverZipCode' => $order_info['shipping_postcode'],
                        'ReceiverAddress' => $receiverAddress,
                        'ReceiverEmail' => $order_info['email'],
                        'Temperature' => '0001',
                        'Distance' => '00',
                        'Specification' => '0001',
                        'ScheduledPickupTime' => '4',
                        'ScheduledDeliveryTime' => '4',
                        'ServerReplyURL' => $server_reply_url,
                        'Remark' => 'ecpay_module_opencart',
                    );
                }

				try {
					$factory = new Factory([
						'hashKey'       => $apiLogisticInfo['hashKey'],
						'hashIv'        => $apiLogisticInfo['hashIv'],
						'hashMethod'    => 'md5',
					]);
					$postService = $factory->create('PostWithCmvEncodedStrResponseService');

					if ($_IsCollection == 'N') {
						unset($inputLogisticOrder['CollectionAmount']);
					}
					$Result = $postService->post($inputLogisticOrder, $apiLogisticInfo['action']);

					if (isset($Result['RtnCode']) && ($Result['RtnCode'] == 300 || $Result['RtnCode'] == 2001)) {

						// 記錄回傳資訊
						$this->saveResponse($order_id, $Result);

						$sComment = "建立綠界科技物流訂單<br>綠界科技物流訂單編號: " . $Result['1|AllPayLogisticsID'];
						if (isset($Result["CVSPaymentNo"]) && !empty($Result["CVSPaymentNo"])) {
							$sComment .= "<br>寄貨編號: " . $Result["CVSPaymentNo"];
						}

						if (isset($Result["CVSValidationNo"]) && !empty($Result["CVSValidationNo"])) {
							$sComment .= $Result["CVSValidationNo"];
						}

						// 新增訂單備註
						$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = 3, notify = '0', comment = '" . $this->db->escape($sComment) . "', date_added = NOW()");

						// 更改訂單狀態
						$this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = 3 WHERE order_id = ". (int) $order_id);

						$ajax_return['code'] = 9999;
						$ajax_return['rtn'] = '1|ok';
						$ajax_return['msg'] .= $Result['RtnMsg'] . "\n";

						foreach ($Result as $key => $value) {
							if ($key == 'CheckMacValue' || $key == 'RtnMsg') {
								continue;
							}
							$ajax_return['msg'] .= $key . '=' . $value . "\n";
						}

					} else {
						$ajax_return['code'] = 701;
						$ajax_return['rtn'] = '0|fail';
						$ajax_return['msg'] = print_r($Result , true) . "\n";

						// 新增訂單備註
						$sComment = "建立綠界科技物流訂單失敗: " . print_r($Result , true);
						$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int) $order_info['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sComment) . "', date_added = NOW()");
					}

				} catch(Exception $e) {
					$ajax_return['code'] = 701;
					$ajax_return['rtn'] = '0|fail';
					$ajax_return['msg'] = print_r($e->getMessage() , true) . "\n";
				}

			} else {
				echo $this->language->get('error_order_info');
			}
		} else {
			echo $this->language->get('error_shipping_order_exists');
		}

		$token = $this->session->data['user_token'];
		$order_view_url = $this->url->link(
			'sale/order.info',
			'user_token=' . $token . '&order_id=' . $order_id,
			$this->url_secure
		);

		$this->response->redirect($order_view_url);
	}

	// 判斷後台是否產生建立物流按鈕
	public function create_shipping_btn(&$route, &$data, &$output) {

		// Token
		$token = $this->session->data['user_token'];
		$create_shipping_flag = true ;
		$order_info = $this->model_sale_order->getOrder($data['order_id']);

		// 判斷物流方式
		$shipping_method_array = explode('.', $order_info['shipping_method']['code']);
		if ($shipping_method_array[0] !== 'ecpaylogistic') {
			$create_shipping_flag = false ;
		}

		// 物流類型
		$logisticSubType = $shipping_method_array[1];
		$logisticsType = $this->helper->get_ecpay_logistics_type($logisticSubType);

		// 判斷物流狀態
		$ecpaylogistic_query = $this->db->query('Select * from ' . DB_PREFIX.'ecpaylogistic_response where order_id='.(int)$data['order_id']);

		// 已經建立過物流訂單
		if ($ecpaylogistic_query->num_rows) {
			$create_shipping_flag = false ;
		}

		// 顯示建立按鈕
		if ($create_shipping_flag) {
			$create_shipping_order_url = $this->url->link(
				$this->extension_route .'/'. $this->module_name . $this->separator . 'create_shipping_order',
				'user_token=' . $token . '&order_id=' . $data['order_id'],
				$this->url_secure
			);

			// 建立物流訂單按鈕
			$data['shipping_method'] .= '<a href="' . $create_shipping_order_url . '" id="ecpaylogistic" class="btn btn-primary btn-xs mx-2">建立物流訂單</a>';

			if ($logisticSubType !== 'post' && $logisticSubType !== 'tcat') {
				// 變更門市按鈕
				$map_form = $this->express_map($data['order_id']);
				$data['shipping_method'] .= '<input type="button" onclick="changeStore()" class="btn btn-primary btn-xs mx-2" value="變更門市" />' . $map_form . '<script>function changeStore() {
				const ecpay_map_window = window.open("","ecpay_map_target",config="height=790px,width=1020px");
				document.getElementById("ecpay-form").submit();
				const ecpay_map_listener = setInterval(() => {
					if (ecpay_map_window && ecpay_map_window.closed){
						clearInterval(ecpay_map_listener);
						location.reload();
					}
				}, 500);}</script>';
			}
		}
	}

	// 判斷後台是否顯示物流單列印按鈕
	public function print_shipping_btn(&$route, &$data, &$output) {

		// Token
		$token = $this->session->data['user_token'];

		$print_logistic_flag = true ;
		$html = '' ;

        $orderInfo = $this->model_sale_order->getOrder($data['order_id']);

        // 判斷物流方式
		$shipping_method_array = explode('.', $orderInfo['shipping_method']['code']);
		if ($shipping_method_array[0] !== 'ecpaylogistic') {
        	$print_logistic_flag = false;
        }

        // 判斷物流狀態
        $ecpaylogistic_query = $this->db->query('Select * from ' . DB_PREFIX . 'ecpaylogistic_response where order_id=' . (int)$data['order_id']);

		 // 尚未建立過物流訂單
        if ($ecpaylogistic_query->num_rows === 0) {
        	$print_logistic_flag = false ;
        }

        // 顯示列印按鈕
        if ($print_logistic_flag) {
			$ecpaylogisticSetting = $this->get_logistic_settings();
			$inputPrint = array();

			$apiLogisticInfo  = $this->helper->get_ecpay_logistic_api_info('print', $ecpaylogistic_query->row['LogisticsSubType'], $ecpaylogisticSetting);

			$factory = new Factory([
				'hashKey'       => $apiLogisticInfo['hashKey'],
				'hashIv'        => $apiLogisticInfo['hashIv'],
				'hashMethod'    => 'md5',
			]);

			$inputPrint = array(
				'MerchantID' => $apiLogisticInfo['merchantId'],
				'AllPayLogisticsID' => $ecpaylogistic_query->row['AllPayLogisticsID'],
				'PlatformID' => ''
			);

			switch ($ecpaylogistic_query->row['LogisticsSubType']) {
				case 'FAMIC2C':
				case 'HILIFEC2C':
				case 'OKMARTC2C':
					try {
						$inputPrint['CVSPaymentNo'] = $ecpaylogistic_query->row['CVSPaymentNo'];
					} catch(Exception $e) {
						echo $e->getMessage();
					}
					break;
				case 'UNIMARTC2C':
					try {
						$inputPrint['CVSPaymentNo'] = $ecpaylogistic_query->row['CVSPaymentNo'];
						$inputPrint['CVSValidationNo'] = $ecpaylogistic_query->row['CVSValidationNo'];
					} catch(Exception $e) {
						echo $e->getMessage();
					}
					break;
				case 'FAMI':
				case 'UNIMART':
				case 'HILIFE':
					break;
			}

			$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');
			$form_print =  $autoSubmitFormService->generate($inputPrint, $apiLogisticInfo['action'], '_Blank','ecpay_print');

			$form_print =  str_replace('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>', '', $form_print);
			$form_print =  str_replace('</body></html>', '', $form_print);
			$form_print =  str_replace('<script type="text/javascript">document.getElementById("ecpay_print").submit();</script>', '', $form_print);

			$data['shipping_method'] .= "&nbsp;" . '<input type="button" id="ecpaylogistic_print" class="btn btn-primary btn-xs" onclick="document.getElementById(\'ecpay_print\').submit()" id="ecpaylogistic_print" value="列印物流單" />' . $form_print;
		}
	}

	// 電子地圖選擇門市
	public function express_map($order_id = null) {

		$this->load->model('sale/order');
		$order_id = is_null($order_id) ? $this->request->get['order_id'] : $order_id;
		$order_info = $this->model_sale_order->getOrder($order_id);

		$ecpaylogisticSetting = $this->get_logistic_settings();
		if ( $ecpaylogisticSetting[$this->prefix . 'type'] == 'C2C' ) {
			$shippingMethod = [
				'fami' => 'FAMIC2C',
				'fami_collection' => 'FAMIC2C',
				'unimart' => 'UNIMARTC2C',
				'unimart_collection' => 'UNIMARTC2C',
				'hilife' => 'HILIFEC2C',
				'hilife_collection' => 'HILIFEC2C',
				'okmart' => 'OKMARTC2C',
				'okmart_collection' => 'OKMARTC2C'
			];
		} else {
			$shippingMethod = [
				'fami' => 'FAMI',
				'fami_collection' => 'FAMI',
				'unimart' => 'UNIMART',
				'unimart_collection' => 'UNIMART',
				'hilife' => 'HILIFE',
				'hilife_collection' => 'HILIFE',
				'okmart' => 'OKMART',
				'okmart_collection' => 'OKMART'
			];
		}

		$logisticSubType = explode(".", $order_info['shipping_method']['code']);
		$apiLogisticInfo  = $this->helper->get_ecpay_logistic_api_info('map', $logisticSubType, $ecpaylogisticSetting);

		if (array_key_exists($logisticSubType[1], $shippingMethod)) {
			$al_subtype = $shippingMethod[$logisticSubType[1]];
		}

		if (!isset($al_subtype)) {
			exit;
		}

		$al_iscollection = 'N';

		// 因 samesite 問題，走前台 API
		$factory = new Factory([
			'hashKey' => $apiLogisticInfo['hashKey'],
			'hashIv'  => $apiLogisticInfo['hashIv']
		]);
		
		$aes_service = $factory->create(AesService::class);
		$encrypt_data = $aes_service->encrypt(['order_id' => $order_id]);

		$al_srvreply = $this->url->link(
			$this->extension_route .'/'. $this->module_name . $this->separator . 'response_map_admin',
			'oid=' . $encrypt_data,
			$this->url_secure
		);
		$al_srvreply = str_replace('admin/', '', $al_srvreply);

		try {
			$factory = new Factory([
				'hashKey' 	 => $apiLogisticInfo['hashKey'],
				'hashIv'  	 => $apiLogisticInfo['hashIv'],
				'hashMethod' => 'md5',
			]);
            $autoSubmitFormService = $factory->create('FormWithCmvService');

			$inputMap = array(
				'MerchantID' => $apiLogisticInfo['merchantId'],
                'LogisticsType'    => $this->helper->get_logistics_type($al_subtype),
				'MerchantTradeNo' => $this->helper->getMerchantTradeNo($order_id),
				'LogisticsSubType' => $al_subtype,
				'IsCollection' => $al_iscollection,
				'ServerReplyURL' => $al_srvreply,
				'ExtraData' => '',
			);

            $form_map = $autoSubmitFormService->generate($inputMap, $apiLogisticInfo['action'], 'ecpay_map_target');

		} catch (Exception $e) {
			echo $e->getMessage();
		}

		return $form_map;
	}

	// 儲存物流訂單回覆
    public function saveResponse($order_id = 0, $feedback = array()) {
        if (empty($order_id) === true) {
            return false;
        }

        $white_list = array(
			'MerchantID',
			'MerchantTradeNo',
			'RtnCode',
			'RtnMsg',
			'1|AllPayLogisticsID',
			'LogisticsType',
			'LogisticsSubType',
			'GoodsAmount',
			'UpdateStatusDate',
			'ReceiverName',
			'ReceiverPhone',
			'ReceiverCellPhone',
			'ReceiverEmail',
			'ReceiverAddress',
			'CVSPaymentNo',
			'CVSValidationNo',
			'BookingNote',
        );

        $inputs = $this->only($feedback, $white_list);

        $insert_sql = 'INSERT INTO `%s`';
        $insert_sql .= ' (`order_id`, `MerchantID`, `MerchantTradeNo`, `RtnCode`, `RtnMsg`, `AllPayLogisticsID`, `LogisticsType`, `LogisticsSubType`, `GoodsAmount`, `UpdateStatusDate`, `ReceiverName`, `ReceiverPhone`, `ReceiverCellPhone`, `ReceiverEmail`, `ReceiverAddress`, `CVSPaymentNo`, `CVSValidationNo`, `BookingNote`, `createdate`)';
        $insert_sql .= " VALUES (%d, '%s', '%s', %d, '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d)";
        $table = DB_PREFIX . 'ecpaylogistic_response';
        $now_time  = time() ;

        return $this->db->query(sprintf(
            $insert_sql,
            $table,
            (int)$order_id,
            $this->db->escape($inputs['MerchantID']),
            $this->db->escape($inputs['MerchantTradeNo']),
            $this->db->escape($inputs['RtnCode']),
            $this->db->escape($inputs['RtnMsg']),
            $this->db->escape($inputs['1|AllPayLogisticsID']),
            $this->db->escape($inputs['LogisticsType']),
            $this->db->escape($inputs['LogisticsSubType']),
            $this->db->escape($inputs['GoodsAmount']),
            $this->db->escape($inputs['UpdateStatusDate']),
            $this->db->escape($inputs['ReceiverName']),
            $this->db->escape($inputs['ReceiverPhone']),
            $this->db->escape($inputs['ReceiverCellPhone']),
            $this->db->escape(str_replace(' ', '+', $inputs['ReceiverEmail'])),
            $this->db->escape($inputs['ReceiverAddress']),
            $this->db->escape($inputs['CVSPaymentNo']),
            $this->db->escape($inputs['CVSValidationNo']),
            $this->db->escape($inputs['BookingNote']),
            $now_time
		));
    }

    /**
     * Filter the inputs
     * @param array $source Source data
     * @param array $whiteList White list
     * @return array
     */
    public function only($source = array(), $whiteList = array())
    {
        $variables = array();

        if (empty($whiteList) === true) {
            return $source;
        }

        foreach ($whiteList as $name) {
            if (isset($source[$name]) === true) {
                $variables[$name] = $source[$name];
            } else {
                $variables[$name] = '';
            }
        }
        return $variables;
    }

	public function get_logistic_settings() {
		$ecpaylogisticSetting = array();
		$sFieldName = 'code';
		$sFieldValue = 'shipping_' . $this->module_name;
		$get_ecpaylogistic_setting_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `" . $sFieldName . "` = '" . $sFieldValue . "'");
		$ecpaylogisticSetting = array();
		foreach($get_ecpaylogistic_setting_query->rows as $value) {
			$ecpaylogisticSetting[$value['key']] = $value['value'];
		}

		return $ecpaylogisticSetting;
	}
}
