<?php

namespace Opencart\Catalog\Model\Extension\Ecpay\Payment;

class EcpayPayment extends \Opencart\System\Engine\Model {

    private $module_name = 'ecpaypayment';
    private $lang_prefix = '';
    private $module_path = '';
    private $setting_prefix = '';
	private $helper = null;
    private $extend_table_name = 'order_extend';

    // Constructor
    public function __construct($registry) {
        parent::__construct($registry);

        // Set the variables
        $this->lang_prefix = $this->module_name .'_';
        $this->setting_prefix = 'payment_' . $this->module_name . '_';
        $this->module_path = 'extension/ecpay/payment/' . $this->module_name;

		require_once DIR_EXTENSION . 'ecpay/system/library/EcpayPaymentHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayPaymentHelper;
    }

    /**
     * getMethods
     *
     * @param  mixed $address
     * @return array
     */
    public function getMethods(array $address = []) {
        // Load the translation file
        $this->load->language($this->module_path);

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->cart->hasShipping()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get($this->setting_prefix . 'geo_zone_id')) {
            $status = true;
        } else {
            // getting payment data using zeo zone
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get($this->config->get($this->setting_prefix . 'geo_zone_id')) . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
            // if the rows found the status set to True
            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = array();
        if ($status) {
            $options = array();
            $payment_methods = $this->config->get('payment_ecpaypayment_payment_methods');
            if (is_array($payment_methods) && count($payment_methods) > 0) {
                foreach ($payment_methods as $key => $payment) {
                    $options[$payment] = [
                        'code' => $this->module_name . '.' . $payment,
                        'name' => $this->language->get($this->lang_prefix . 'text_' . $payment)
                    ];
                }

                $method_data = [
                    'code'       => $this->module_name,
                    'name'       => $this->language->get($this->lang_prefix . 'text_title'),
                    'option'     => $options,
                    'sort_order' => $this->config->get($this->setting_prefix . 'sort_order')
                ];

                $cart_total = (int)$this->cart->getTotal() + (int)$this->session->data['shipping_method']['cost'];
                // 判斷是否可選無卡分期
                if ($cart_total < 3000) {
                    unset($method_data['option']['bnpl']);
                }
                // 判斷是否可選TWQR
                if (6 > $cart_total || $cart_total > 49999) {
                    unset($method_data['option']['twqr']);
                }

                // 判斷是否可選定期定額
                $dca_period_type = $this->config->get($this->setting_prefix . 'dca_period_type');
                $dca_frequency = $this->config->get($this->setting_prefix . 'dca_frequency');
                $dca_exec_times = $this->config->get($this->setting_prefix . 'dca_exec_times');
                if (!in_array($dca_period_type, ['Y', 'M', 'D']) || $dca_frequency == '' || $dca_exec_times == '') {
                    unset($method_data['option']['dca']);
                }

                // 判斷是否可選貨到付款，運送方式必須是綠界物流
                $shipping_method_code = $this->session->data['shipping_method']['code'];
                $shipping_method_code = explode('.', $shipping_method_code);
                if ($shipping_method_code[0] != 'ecpaylogistic') {
                    unset($method_data['option']['cod']);
                }
            }
        }

        return $method_data;
    }

	public function getMethod($address, $total) {
        // Condition check
        $ecpay_geo_zone_id = $this->config->get($this->setting_prefix . 'geo_zone_id');
        $sql = 'SELECT * FROM `' . DB_PREFIX . 'zone_to_geo_zone`';
        $sql .= ' WHERE geo_zone_id = "' . (int)$ecpay_geo_zone_id . '"';
        $sql .= ' AND country_id = "' . (int)$address['country_id'] . '"';
        $sql .= ' AND (zone_id = "' . (int)$address['zone_id'] . '" OR zone_id = "0")';
        $query = $this->db->query($sql);
        unset($sql);

        $status = false;
        if ($total <= 0) {
            $status = false;
        } elseif (!$ecpay_geo_zone_id) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        // Set the payment method parameters
        $this->load->language($this->module_path);
        $method_data = array();
        if ($status === true) {
            $method_data = array(
                'code' => $this->module_name,
                'title' => $this->language->get($this->lang_prefix . 'text_title'),
                'terms' => '',
                'sort_order' => $this->config->get($this->setting_prefix . 'sort_order')
            );
        }
        return $method_data;
    }

    // Check if AIO responsed
    public function isResponsed($order_id = 0) {
        if (empty($order_id) === true) {
            return false;
        }
        $select_sql = 'SELECT order_id FROM `%s`';
        $select_sql .= ' WHERE order_id = %d';
        $select_sql .= ' LIMIT 1';
        $table = DB_PREFIX . $this->extend_table_name;
        $result = $this->db->query(sprintf(
            $select_sql,
            $table,
            (int)$order_id
        ));

        return ($result->num_rows > 0);
    }

    // Save AIO response
    public function saveResponse($order_id = 0, $feedback = array()) {
        if (empty($order_id) === true) {
            return false;
        }

        $white_list = array('card4no');
        $inputs = $this->helper->only($feedback, $white_list);

        if (empty($inputs['card4no']) === false) {
            $card_4 = $inputs['card4no'];
        } else {
            $card_4 = '';
        }
        $insert_sql = 'INSERT INTO `%s`';
        $insert_sql .= ' (`order_id`, `card_no4`, `response_count`, `createdate`)';
        $insert_sql .= ' VALUES (%d, %d, %d, %d)';
        $table = DB_PREFIX . $this->extend_table_name;
        $response_count = 1;
        $now_time  = time() ;
        return $this->db->query(sprintf(
            $insert_sql,
            $table,
            (int)$order_id,
            $this->db->escape($card_4) ,
            $response_count,
            $now_time
        ));
    }

    // 新增綠界訂單額外資訊
    public function insertEcpayOrderExtend($order_id, $inputs)
    {
        if (empty($order_id) === true) {
            return false;
        }

        $insert_sql = 'INSERT INTO `%s`';
        $insert_sql .= ' (`order_id`, `goods_weight`, `createdate`)';
        $insert_sql .= " VALUES (%d, %.3f, %d)";
        $table = DB_PREFIX . 'ecpay_order_extend';
        $now_time  = time() ;

        return $this->db->query(sprintf(
            $insert_sql,
            $table,
            (int)$order_id,
            $this->db->escape($inputs['goodsWeight']),
            $now_time
        ));
    }
}
