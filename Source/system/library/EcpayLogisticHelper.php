<?php
namespace Opencart\System\Library;

require_once DIR_EXTENSION . 'ecpay/system/library/ModuleHelper.php';
use Opencart\System\Library\ModuleHelper;

class EcpayLogisticHelper extends ModuleHelper
{
	private $prefix = 'shipping_ecpaylogistic_';
    private $ecpay_logistic_module_name = 'ecpaylogistic';

    /**
     * EcpayLogisticHelper constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function get_logistics_type($shipping_sub_type) {
        $shipping_type = 'CVS';

        switch ($shipping_sub_type) {
            case 'FAMIC2C':
            case 'UNIMARTC2C':
            case 'HILIFEC2C':
            case 'OKMARTC2C':
            case 'FAMI':
            case 'UNIMART':
            case 'HILIFE':
            case 'OKMART':
                $shipping_type = 'CVS';
                break;
            case 'HOMETCAT':
            case 'HOMEECAN':
            case 'HOMEPOST':
                $shipping_type = 'HOME';
                break;
        }

        return $shipping_type;
    }

    public function get_logistics_sub_type($shipping_method, $shipping_type) {
        switch ($shipping_method) {
            case 'fami':
            case 'fami_collection':
                $shipping_sub_type = 'FAMI';
                break;
            case 'unimart':
            case 'unimart_collection':
                $shipping_sub_type = 'UNIMART';
                break;
            case 'hilife':
            case 'hilife_collection':
                $shipping_sub_type = 'HILIFE';
                break;
            case 'okmart':
            case 'okmart_collection':
                $shipping_sub_type = 'OKMART';
                break;
        }

        if ($shipping_type == 'C2C') {
            $shipping_sub_type = $shipping_sub_type . 'C2C';
        }

        return $shipping_sub_type;
    }

    public function get_ecpay_logistic_api_info($action = '', $shipping_method = '', $ecpaylogisticSetting = [])
    {
        $api_info = [
            'action' => '',
        ];

        // URL位置判斷
        if ($ecpaylogisticSetting[$this->prefix . 'test_mode']) {
            if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'B2C') {
                $api_info['merchantId'] = '2000132';
                $api_info['hashKey'] = '5294y06JbISpM5x9';
                $api_info['hashIv'] = 'v77hoKGq4kWxNNIS';
            }
            else {
                $api_info['merchantId'] = '2000933';
                $api_info['hashKey'] = 'XBERn1YOvpM9nfZc';
                $api_info['hashIv'] = 'h1ONHk4P4yqbl5LK';
            }

            switch ($action) {
                case 'map':
                    $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/Express/map';
                    break;
                case 'create':
                    $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/Express/Create';
                    break;
                case 'print':
                    if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'C2C') {
                        switch ($shipping_method) {
                            case 'UNIMARTC2C':
                                $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/Express/PrintUniMartC2COrderInfo';
                                break;
                            case 'FAMIC2C':
                                $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/Express/PrintFAMIC2COrderInfo';
                                break;
                            case 'HILIFEC2C':
                                $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/Express/PrintHILIFEC2COrderInfo';
                                break;
                            case 'OKMARTC2C':
                                $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/Express/PrintOKMARTC2COrderInfo';
                                break;
                            case 'POST':
                            case 'TCAT':
                                $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/helper/printTradeDocument';
                                break;
                            default:
                                $api_info['action'] = '';
                                break;
                        }

                    } else if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'B2C') {
                        switch ($shipping_method) {
                            case 'UNIMART':
                            case 'FAMI':
                            case 'HILIFE':
                            case 'TCAT':
                            case 'POST':
                                $api_info['action'] = 'https://logistics-stage.ecpay.com.tw/helper/printTradeDocument';
                                break;
                            default:
                                $api_info['action'] = '';
                                break;
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        else {
            $api_info['merchantId'] = $ecpaylogisticSetting[$this->prefix . 'mid'];
            $api_info['hashKey'] = $ecpaylogisticSetting[$this->prefix . 'hashkey'];
            $api_info['hashIv'] = $ecpaylogisticSetting[$this->prefix . 'hashiv'];

            switch ($action) {
                case 'map':
                    $api_info['action'] = 'https://logistics.ecpay.com.tw/Express/map';
                    break;
                case 'create':
                    $api_info['action'] = 'https://logistics.ecpay.com.tw/Express/Create';
                    break;
                case 'print':
                    if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'C2C') {
                        switch ($shipping_method) {
                            case 'UNIMART':
                                $api_info['action'] = 'https://logistics.ecpay.com.tw/Express/PrintUniMartC2COrderInfo';
                                break;
                            case 'FAMI':
                                $api_info['action'] = 'https://logistics.ecpay.com.tw/Express/PrintFAMIC2COrderInfo';
                                break;
                            case 'HILIFE':
                                $api_info['action'] = 'https://logistics.ecpay.com.tw/Express/PrintHILIFEC2COrderInfo';
                                break;
                            case 'OKMARTC2C':
                                $api_info['action'] = 'https://logistics.ecpay.com.tw/Express/PrintOKMARTC2COrderInfo';
                                break;
                            case 'POST':
                                $api_info['action'] = 'https://logistics.ecpay.com.tw/helper/printTradeDocument';
                                break;
                            default:
                                $api_info['action'] = '';
                                break;
                        }
                    }
                    else if ($ecpaylogisticSetting[$this->prefix . 'type'] == 'B2C') {
                        switch ($shipping_method) {
                            case 'UNIMART':
                            case 'FAMI':
                            case 'HILIFE':
                            case 'OKMART':
                            case 'POST':
                                $api_info['action'] = 'https://logistics.ecpay.com.tw/helper/printTradeDocument';
                                break;
                            default:
                                $api_info['action'] = '';
                                break;
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $api_info;
    }

    /**
     * 計算中華郵政重量運費
     * @param $weight_class 1.KG 2.Gram 3.Pound 4.Ounce
     * @return string
     */
    public function cal_home_post_shipping_cost($weight, $weight_class)
    {
        // 判斷重量單位
        $weight_diff = 1;
        switch ($weight_class) {
            case '2':
                $weight_diff = 0.001;
                break;
            case '3':
                $weight_diff = 0.45359237;
                break;
            case '4':
                $weight_diff = 0.0283495231;
                break;
        }

        // 轉換重量為公斤制
        $weight = $weight * $weight_diff;

        if ($weight <= 5) {
            return '1';
        } else if ($weight > 5 && $weight <= 10) {
            return '2';
        } else if ($weight > 10 && $weight <= 15) {
            return '3';
        } else if ($weight > 15 && $weight <= 20) {
            return '4';
        } else  {
			return '5';
		}
    }

    /**
     * 取得綠界物流
     * @return array $ecpayAllLogistics
     */
    public function get_ecpay_all_logistics()
    {
        $ecpayAllLogistics = array_merge($this->get_ecpay_cvs_logistics(), $this->get_ecpay_home_logistics());
        return $ecpayAllLogistics;
    }

    /**
     * 取得綠界宅配物流
     * @return array
     */
    public function get_ecpay_home_logistics()
    {
        return [
            'tcat',
            'post',
        ];
    }

    /**
     * 取得綠界超商物流
     * @return array
     */
    public function get_ecpay_cvs_logistics()
    {
        return [
            'unimart',
            'unimart_collection',
            'fami',
            'fami_collection',
            'hilife',
            'hilife_collection',
            'okmart',
            'okmart_collection'
        ];
    }

    /**
     * 取得綠界物流類型
     * @return string|bool
     */
    public function get_ecpay_logistics_type(string $shippingMethod)
    {
        if ($this->is_ecpay_home_logistics($shippingMethod)) {
            return 'HOME';
        } else if ($this->is_ecpay_cvs_logistics($shippingMethod)) {
            return 'CVS';
        } else {
            return false;
        }
    }

    /**
     * 判斷是否為綠界物流
     * @param  string $shippingMethod
     * @return bool
     */
    public function is_ecpay_logistics(string $shippingMethod)
    {
        return in_array($shippingMethod, $this->get_ecpay_all_logistics());
    }

    /**
     * 判斷是否為綠界宅配物流
     * @param  string $shippingMethod
     * @return bool
     */
    public function is_ecpay_home_logistics(string $shippingMethod)
    {
        return in_array($shippingMethod, $this->get_ecpay_home_logistics());
    }

    /**
     * 判斷是否為綠界超商物流
     * @param  string $shippingMethod
     * @return bool
     */
    public function is_ecpay_cvs_logistics(string $shippingMethod)
    {
        return in_array($shippingMethod, $this->get_ecpay_cvs_logistics());
    }


    public function bite_str($string, $start, $len, $byte = 3) {
		$str = "";
		$count = 0;
		$str_len = strlen($string);
		for ($i = 0; $i < $str_len; $i++) {
			if (($count + 1 - $start) > $len) {
				$str  .= "...";
				break;
			} elseif ((ord(substr($string, $i, 1)) <= 128) && ($count < $start)) {
				$count++;
			} elseif ((ord(substr($string, $i, 1)) > 128) && ($count < $start)) {
				$count = $count + 2;
				$i = $i + $byte - 1;
			} elseif ((ord(substr($string, $i, 1)) <= 128) && ($count >= $start)) {
				$str .= substr($string, $i, 1);
				$count++;
			} elseif ((ord(substr($string, $i, 1)) > 128) && ($count >= $start)) {
				$str .= substr($string, $i, $byte);
				$count = $count + 2;
				$i = $i + $byte - 1;
			}
		}
		return $str;
	}
}
