<?php

namespace Opencart\System\Library;

require_once DIR_EXTENSION . 'ecpay/system/library/ModuleHelper.php';
use Opencart\System\Library\ModuleHelper;

class EcpayPaymentHelper extends ModuleHelper
{
    private $module_name = 'ecpaypayment';
    private $setting_prefix;

    /**
     * EcpayPaymentHelper constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setting_prefix = 'payment_' . $this->module_name . '_';
    }

    /**
     * 依付款方式新增額外資訊
     * @param  array $input
     * @param  string $choosePayment
     * @return array|false
     */
    public function add_type_info($input, $choosePayment)
    {
        if (empty($input['ChoosePayment']) === true || empty($choosePayment) === true) {
            return false;
        }

        $choosePaymentArray = explode('_', $choosePayment);
        switch ($choosePaymentArray[0]) {
            case 'credit':
                // 信用卡分期
                if (isset($choosePaymentArray[1]) === true && in_array($choosePaymentArray[1], ['3', '6', '12', '18', '24', '30'])) {
                    $input['CreditInstallment'] = ($choosePaymentArray[1] == '30') ? '30N' : $choosePaymentArray[1];
                }
                // 關閉銀聯
                $input['UnionPay'] = 2;
                break;

            case 'atm':
                $input['ExpireDate'] = 3;
                break;

            case 'barcode':
                $input['StoreExpireDate'] = 3;
                break;

            case 'cvs':
                $input['StoreExpireDate'] = 10080;
                break;

            case 'dca':
                $input['PeriodAmount'] = $input['TotalAmount'];
                break;

            case 'unionpay':
                $input['UnionPay'] = 1;
                break;
        }

        return $input;
    }

    /**
     * 取得 API URL
     * @param  string $action
     * @param  string $mid
     * @return string|false
     */
    public function get_ecpay_payment_api_info($action = '', $test_mode = '')
    {
        $api_payment_info = [
            'action'        => '',
        ];

        // URL位置判斷
        if ($test_mode) {
            $api_payment_info['merchantId'] = '3002607';
            $api_payment_info['hashKey'] = 'pwFHCqoQZGmho4w6';
            $api_payment_info['hashIv'] = 'EkRm7iFT261dpevs';

            switch ($action) {
                case 'QueryTradeInfo':
                    $api_payment_info['action'] = 'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5';
                    break;

                case 'AioCheckOut':
                    $api_payment_info['action'] = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';
                    break;

                default:
                    break;
            }
        }
        else {
            $api_payment_info['merchantId'] = $this->config->get($this->setting_prefix . 'merchant_id');
            $api_payment_info['hashKey'] = $this->config->get($this->setting_prefix . 'hash_key');
            $api_payment_info['hashIv'] = $this->config->get($this->setting_prefix . 'hash_iv');

            switch ($action) {
                case 'QueryTradeInfo':
                    $api_payment_info['action'] = 'https://payment.ecpay.com.tw/Cashier/QueryTradeInfo/V5';
                    break;

                case 'AioCheckOut':
                    $api_payment_info['action'] = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';
                    break;

                default:
                    break;
            }
        }

        return $api_payment_info;
    }

    /**
     * Get comment
     * @param  string $pattern  Message pattern
     * @param  array  $feedback AIO feedback
     * @param  int    $type     Pattern type　0:payment result, 1:get code result
     * @return string
     */
    public function getComment($pattern = '', $feedback = array(), $type = 0)
    {
        // Filter inputs
        $undefinedMessage = 'undefined';
        if (empty($pattern) === true) {
            return $undefinedMessage;
        }

        $list = array(
            'PaymentType',
            'RtnCode',
            'RtnMsg',
            'BankCode',
            'vAccount',
            'ExpireDate',
            'PaymentNo',
            'Barcode1',
            'Barcode2',
            'Barcode3',
            'BNPLTradeNo',
            'BNPLInstallment',
        );
        $inputs = $this->only($feedback, $list);

        if ($type === 0) {
            return sprintf(
                $pattern,
                $inputs['PaymentType'],
                $inputs['RtnCode'],
                $inputs['RtnMsg']
            );
        } elseif ($type === 1) {
            $paymentTypeArray = explode('_', $inputs['PaymentType']);
            switch($paymentTypeArray[0]) {
                case 'Credit':
                case 'TWQR':
                case 'ApplePay':
                case 'UnionPay':
                    return sprintf(
                        $pattern,
                        $inputs['PaymentType'],
                        $inputs['RtnCode'],
                        $inputs['RtnMsg']
                    );
                    break;
                case 'ATM':
                    return sprintf(
                        $pattern,
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                        $inputs['BankCode'],
                        $inputs['vAccount'],
                        $inputs['ExpireDate']
                    );
                    break;
                case 'WebATM':
                    return sprintf(
                        $pattern,
                        $inputs['PaymentType'],
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                    );
                    break;
                case 'CVS':
                    return sprintf(
                        $pattern,
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                        $inputs['PaymentNo'],
                        $inputs['ExpireDate']
                    );
                    break;
                case 'BARCODE':
                    return sprintf(
                        $pattern,
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                        $inputs['ExpireDate'],
                        $inputs['Barcode1'],
                        $inputs['Barcode2'],
                        $inputs['Barcode3']
                    );
                    break;
                case 'BNPL':
                    return sprintf(
                        $pattern,
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                        $inputs['BNPLTradeNo'],
                        $inputs['BNPLInstallment']
                    );
                    break;
                case 'DCA':
                    return sprintf(
                        $pattern,
                        $inputs['PaymentType'],
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                        $inputs['PeriodAmount'],
                        $inputs['PeriodType'],
                        $inputs['Frequency'],
                        $inputs['ExecTimes']
                    );

                    break;
                default:
                    break;
            }

        }
        return $undefinedMessage;
    }

    /**
     * 取得對應的 SDK 付款方式
     * @param  string $choose_payment
     * @return string|false
     */
    public function getSdkPayment ($choose_payment) {
        if (empty($choose_payment) === true) {
            return false;
        }

        $sdkPayment = '';
        $choosePaymentArray = explode('_', $choose_payment);
        switch ($choosePaymentArray[0]) {
            case 'credit':
            case 'dca':
            case 'unionpay':
                $sdkPayment = 'Credit';
                break;
            case 'webatm':
                $sdkPayment = 'WebATM';
                break;
            case 'atm':
                $sdkPayment = 'ATM';
                break;
            case 'cvs':
                $sdkPayment = 'CVS';
                break;
            case 'barcode':
                $sdkPayment = 'BARCODE';
                break;
            case 'bnpl':
                $sdkPayment = 'BNPL';
                break;
            case 'twqr':
                $sdkPayment = 'TWQR';
                break;
            case 'applepay':
                $sdkPayment = 'ApplePay';
                break;
        }

        return $sdkPayment;
    }

}
