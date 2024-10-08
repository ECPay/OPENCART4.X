<?php

namespace Opencart\System\Library;

class ModuleHelper
{
    /**
     * @var string SDK class name(required)
     */
    protected $sdkClassName = '';

    /**
     * @var string SDK file path(required)
     */
    // protected $sdkFilePath = '';
    protected $sdkFilePath = DIR_EXTENSION . 'ecpay/system/library/ecpay/vendor/autoload.php';

    /**
     * @var bool|null|object SDK object
     */
    protected $sdk = null;

    /**
     * @var array Stage merchant ids
     */
    private $stageMerchantIds = array('2000132');

    /**
     * @var string Merchant Id
     */
    private $merchantId = '';

    /**
     * @var string Merchant order number prefix
     */
    private $merchantOrderPrefix = '';

    /**
     * @var string Log directorygetMerchantTradeNo
     */
    private $logDirPath = '';

    /**
     * @var string Log file name
     */
    private $logFileName = '';

    /**
     * ModuleHelper constructor.
     */
    public function __construct()
    {
        $this->setLogDir('');
        $this->setLogFileName('');
        $this->sdk = $this->factory();
        $this->merchantOrderPrefix = $this->getDateTime('ymdHi', '');
    }

    /**
     * Create SDK
     * @return object|bool
     */
    private function factory()
    {
        require_once($this->sdkFilePath);
    }

    /**
     * Set the exist property value
     * @param $name
     * @param $value
     * @return bool
     */
    private function set($name, $value)
    {
        if (property_exists($this, $name) === true) {
            $this->{$name} = $value;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check test mode by merchant id
     * @param string $merchantId Merchant ID
     * @return bool
     */
    protected function isTestMode($merchantId = '')
    {
        return in_array($merchantId, $this->stageMerchantIds);
    }

    /**
     * Set stage merchant ids
     * @param array $merchantIds
     * @return bool
     */
    protected function setStageMerchantIds($merchantIds = array())
    {
        return $this->set('stageMerchantIds', $merchantIds);
    }

    /**
     * Set merchant id
     * @param string $merchantId
     * @return bool
     */
    public function setMerchantId($merchantId = '')
    {
        return $this->set('merchantId', $merchantId);
    }

    /**
     * Get merchant id
     * @return string
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * Chang the value to integer
     * @param  mixed $value Value
     * @return integer
     */
    public function toInt($value = 0)
    {
        return intval($value, 10);
    }

    /**
     * Set log directory
     * @param string $path Log directory path
     * @return bool
     */
    public function setLogDir($path = '')
    {
        $defaultDirPath = '.';
        if (empty($path) === false) {
            if (file_exists($path) === true) {
                return $this->set('logDirPath', $path);
            } else {
                return $this->set('logDirPath', $defaultDirPath);
            }
        } else {
            return $this->set('logDirPath', $defaultDirPath);
        }
    }

    /**
     * Get the log directory
     * @return string
     */
    public function getLogDir()
    {
        return $this->logDirPath;
    }

    /**
     * Set the log file name
     * @param string $fileName
     * @return bool
     */
    public function setLogFileName($fileName = '')
    {
        $format = 'debug_log_%s.txt';
        $dateString = $this->getDateTime('ymd', '');

        if (empty($fileName) === true) {
            return $this->set('logFileName', sprintf($format, $dateString));
        } else {
            return $this->set('logFileName', $fileName);
        }
    }

    /**
     * Get the log file name
     * @return string
     */
    public function getLogFileName()
    {
        return $this->logFileName;
    }

    /**
     * Get the full log path
     * @return string
     */
    public function getFullLogPath()
    {
        $format = '%s/%s';
        return sprintf($format, $this->getLogDir(), $this->getLogFileName());
    }

    /**
     * Get the log content
     * @param string $content Log content
     * @return string
     */
    public function getLogContent($content = '')
    {
        $format = '%s %s';
        $parseList = array('array', 'object');
        $logDate = $this->getDateTime('Y-m-d H:i:s', '');
        $dataType = gettype($content);
        if (in_array($dataType, $parseList) === true) {
            $logContent = print_r($content, true);
        } else {
            $logContent = $content;
        }
        return sprintf($format, $logDate, $logContent) . PHP_EOL;
    }

    /**
     * Save debug log
     * @param  string $content Log content
     * @return integer
     */
    public function saveDebugLog($content = '')
    {
        // Save log
        $logPath = $this->getFullLogPath();
        $logContent = $this->getLogContent($content);
        return file_put_contents($logPath, $logContent, FILE_APPEND);
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

        // Return empty array when do not set white list
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

    /**
     * Check if has empty data
     * @param array $data
     * @return bool
     */
    public function hasEmpty($data = array())
    {
        foreach ($data as $value) {
            if (empty($value) === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Echo the parameters in json format and exit
     * @param array $parameters Parameters
     */
    public function echoJson($parameters = array())
    {
        $json = json_encode($parameters);
        $this->echoAndExit($json);
    }

    /**
     * Echo and exit
     * @param string $message
     */
    public function echoAndExit($message = '')
    {
        echo $message;
        exit;
    }

    /**
     * Set merchant order number prefix
     * @param string $prefix
     * @return bool
     */
    public function setMerchantOrderPrefix($prefix = '')
    {
        return $this->set('merchantOrderPrefix', $prefix);
    }

    /**
     * Get merchant order number prefix
     * @return string
     */
    public function getMerchantOrderPrefix()
    {
        return $this->merchantOrderPrefix;
    }

    /**
     * Get merchant trade number
     * @param  integer $orderId Order id
     * @return string
     */
    public function getMerchantTradeNo($orderId = 0, $order_prefix  = '')
    {
        $trade_no = $order_prefix . substr(str_pad($orderId, 8, '0', STR_PAD_LEFT), 0, 8) . 'SN' . substr(hash('sha256', (string) time()), -5);
        return substr($trade_no, 0, 20);
    }

    /**
     * Get the length of merchant order number prefix
     * @return int
     */
    public function getMerchantOrderPrefixLength()
    {
        return strlen($this->getMerchantOrderPrefix());
    }

    /**
     * 取得 Order Id
     * @param  array $info
     * @param  string $order_prefix
     * @return int|string|false
     */
    public function getOrderIdByMerchantTradeNo($info, $order_prefix = '')
    {
        if (isset($info['MerchantTradeNo'])) {
            $order_id = substr($info['MerchantTradeNo'], strlen($order_prefix), strrpos($info['MerchantTradeNo'], 'SN'));
            $order_id = (int)$order_id;
            if ($order_id > 0) {
                return $order_id;
            }
        }

        return false;
    }

    /**
     * Get the unixtime
     * @param  string $dateString Date string
     * @return integer
     */
    public function getUnixTime($dateString = '')
    {
        return strtotime($dateString);
    }

    /**
     * Get date time
     * @param  string $pattern    Date time pattern
     * @param  string $dateString Date string
     * @return string
     */
    public function getDateTime($pattern = 'Y-m-d H:i:s', $dateString = '')
    {
        if ($dateString !== '') {
            return date($pattern, $this->getUnixTime($dateString));
        } else {
            return date($pattern);
        }
    }

    /**
     * Get the amount
     * @param  mixed $amount Amount
     * @return integer
     */
    public function getAmount($amount = 0)
    {
        return round($amount, 0);
    }

    /**
     * Validate the amounts
     * @param  mixed $source Source amount
     * @param  mixed $target Target amount
     * @return boolean
     */
    public function validAmount($source = 0, $target = 0)
    {
        return ($this->getAmount($source) === $this->getAmount($target));
    }

}