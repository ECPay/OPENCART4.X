<?php
namespace Opencart\Catalog\Model\Extension\Ecpay\Module;

use Ecpay\Sdk\Factories\Factory;

class EcpayInvoice extends \Opencart\System\Engine\Model
{
    private $module_name = 'ecpayinvoice';
    private $lang_prefix = '';
    private $module_path = '';
    private $setting_prefix = '';
	private $helper = null;

	public function __construct($registry) {
		parent::__construct($registry);

		require_once DIR_EXTENSION . 'ecpay/system/library/EcpayInvoiceHelper.php';
        $this->helper = new \Opencart\System\Library\EcpayInvoiceHelper;

		// Set the variables
        $this->lang_prefix = $this->module_name .'_';
        $this->setting_prefix = 'module_' . $this->module_name . '_';
        $this->module_path = 'extension/ecpay/module/' . $this->module_name;
	}

	public function getMethod($address, $total) {
		$method_data = array();
		return $method_data;
	}

	// 判斷電子發票啟用狀態
	public function get_invoice_status() {
		$nInvoice_Status = $this->config->get($this->setting_prefix. 'status');
		return $nInvoice_Status;
	}

	// 判斷電子發票是否啟動自動開立
	public function get_invoice_autoissue() {
		$nInvoice_Autoissue = $this->config->get($this->setting_prefix. 'autoissue');
		return $nInvoice_Autoissue;
	}

	// 自動開立發票
	public function createInvoiceNo($orderId = 0) {
		// 1.參數初始化
		define('WEB_MESSAGE_NEW_LINE',	'|'); // 前端頁面訊息顯示換行標示語法

		$sMsg				= '' ;
		$sMsgP2 			= '' ;		      // 金額有差異提醒
		$bError 			= false ; 	      // 判斷各參數是否有錯誤，沒有錯誤才可以開發票

		// 2.取出開立相關參數

		// *連線資訊
		$ecpayinvoiceMid 	 = $this->config->get($this->setting_prefix . 'mid');		// 廠商代號
		$ecpayinvoiceHashkey = $this->config->get($this->setting_prefix . 'hashkey');	// 金鑰
		$ecpayinvoiceHashiv  = $this->config->get($this->setting_prefix . 'hashiv');	// 向量

		// *訂單資訊
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$orderId . "'" );
		$orderInfo = $query->rows[0] ;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$orderId . "'" );
		$orderProduct = $query->rows ;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$orderId . "'" );
		$orderTotal = $query->rows ;

		// *統編與愛心碼資訊
		$queryInvoice = $this->db->query("SELECT * FROM " . DB_PREFIX . "invoice_info WHERE order_id = '" . (int)$orderId . "'" );


		// 3.判斷資料正確性

		// *MID判斷是否有值
		if ($ecpayinvoiceMid == '') {
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫商店代號(Merchant ID)。';
		}

		// *HASHKEY判斷是否有值
		if ($ecpayinvoiceHashkey == '') {
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫金鑰(Hash Key)。';
		}

		// *HASHIV判斷是否有值
		if ($ecpayinvoiceHashiv == '') {
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '請填寫向量(Hash IV)。';
		}

		// 判斷是否開過發票
		if ($orderInfo['invoice_no'] != '0') {
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '已存在發票紀錄，無法再次開立。';
		}

		// 開立發票資訊
		if ($queryInvoice->num_rows == 0) {
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . '開立發票資訊不存在。';
		} else {
			$invoiceInfo = $queryInvoice->rows[0] ;
		}

		// 判斷商品是否存在
		if (count($orderProduct) < 0) {
			$bError = true ;
			$sMsg .= ( empty($sMsg) ? '' : WEB_MESSAGE_NEW_LINE ) . ' 該訂單編號不存在商品，不允許開立發票。';
		} else {
			// 判斷商品是否含小數點
			foreach ($orderProduct as $key => $value) {
				if (!strstr($value['price'], '.00')) {
					$sMsgP2 .= ( empty($sMsgP2) ? '' : WEB_MESSAGE_NEW_LINE ) . '提醒：商品 ' . $value['name'] . ' 金額存在小數點，將以無條件進位開立發票。';
				}
			}
		}

		if (!$bError) {
			$loveCode 				    = '';
			$isdonation					= '0';
			$isPrint					= '0';
			$customerIdentifier		    = '';
			$customerName               = $orderInfo['lastname'] . $orderInfo['firstname'];

			$carrierType 				= '';
			$carrierNum 				= '';

			switch ($invoiceInfo['invoice_type']) {
				// 個人
				case 1:
					switch ($invoiceInfo['carrier_type']) {
						// 紙本
						case 1:
							$carrierType = '';
							$isPrint     = '1';
							break;
						// 雲端發票
						case 2:
							$carrierType = '1';
							break;
						// 自然人憑證
						case 3:
							$carrierType = '2';
							$carrierNum  = $invoiceInfo['carrier_num'];
							break;
						// 手機條碼
						case 4:
							$carrierType = '3';
							$carrierNum  = $invoiceInfo['carrier_num'];
							break;
						default:
							$carrierType = '';
							$isPrint     = '1';
							break;
					}
					break;
				// 公司
				case 2:
					$isPrint               = '1';
					$customerIdentifier    = $invoiceInfo['company_write'];
					$customerName          = ($invoiceInfo['customer_company']) ?: $customerName;

					switch ($invoiceInfo['carrier_type']) {
						// 雲端發票
						case 2:
							$isPrint       = '0';
							$carrierType   = '1';
							break;
						// 手機條碼
						case 4:
							$carrierType   = '3';
							$carrierNum    = $invoiceInfo['carrier_num'];
							break;
					}
					break;
				// 捐贈
				case 3:
					$isdonation             = '1';
					$loveCode               = $invoiceInfo['love_code'];
					break;
			}

			// 4.送出參數
			try {
				// *算出商品各別金額
				$subTotalReal = 0 ;	// 實際無條進位小計

				foreach ($orderProduct as $key => $value) {
					$quantity 	= ceil($value['quantity']) ;
					$price		= ceil($value['price']) ;
					$total		= $quantity * $price	 ; 				// 各商品小計

					$subTotalReal = $subTotalReal + $total ;		// 計算發票總金額

				 	$productName  = $value['name'] ;
				 	$productNote  = $value['model'] . '-' . $value['product_id'] ;

				 	mb_internal_encoding('UTF-8');
				 	$stringLimit  = 10 ;
				 	$sourceLength = mb_strlen($productNote);

				 	if ($stringLimit < $sourceLength) {
						$stringLimit = $stringLimit - 3;

						if ($stringLimit > 0) {
							$productNote = mb_substr($productNote, 0, $stringLimit) . '...';
						}
					}

					$items[] = [
						'ItemName' 		=> $productName,
						'ItemCount' 	=> $quantity,
						'ItemWord' 		=> '批',
						'ItemPrice' 	=> $price,
						'ItemTaxType' 	=> '1',
						'ItemAmount' 	=> $total,
						'ItemRemark'    => $productNote
					];
				}

				// *找出total
				$total = 0 ;
				foreach ($orderTotal as $key2 => $value2) {
					if ($value2['code'] == 'total') {
						$total = (int) $value2['value'];
						break;
					}
				}

				// 其他項目計算
				foreach ($orderTotal as $key2 => $value2) {
					if ($value2['code'] != 'total' && $value2['code'] != 'sub_total') {
						$subTotalReal = $subTotalReal + (int) $value2['value'] ; // 計算發票總金額

						$items[] = [
							'ItemName'    => $value2['title'],
							'ItemCount'   => 1,
							'ItemWord'    => '批',
							'ItemPrice'   => (int) $value2['value'],
							'ItemTaxType' => 1,
							'ItemAmount'  => (int) $value2['value'],
							'ItemRemark'  => $value2['title']
						];
					}
				}

				// 無條件位後加總有差異
				if ($total != $subTotalReal) {
					$sMsgP2 .= ( empty($sMsgP2) ? '' : WEB_MESSAGE_NEW_LINE ) . '綠界科技電子發票開立，實際金額 $' . $total . '， 無條件進位後 $' . $subTotalReal;
				}

				// 買受人地址
				$customerAddr = '';
				if (empty($orderInfo['payment_country']) ||
					empty($orderInfo['payment_postcode']) ||
					empty($orderInfo['payment_city']) ||
					empty($orderInfo['payment_address_1']) ||
					empty($orderInfo['payment_address_2'])
				) {
					$customerAddr = $orderInfo['shipping_country'] . $orderInfo['shipping_postcode'] . $orderInfo['shipping_city'] . $orderInfo['shipping_address_1'] . $orderInfo['shipping_address_2'];
				} else {
					$customerAddr = $orderInfo['payment_country'] . $orderInfo['payment_postcode'] . $orderInfo['payment_city'] . $orderInfo['payment_address_1'] . $orderInfo['payment_address_2'];
				}

				// 特店自訂編號
				$relateNumber  = $this->helper->get_relate_number($orderId);

				// 記錄發票備註卡號末四碼
				$creditRemark  = '';
				$paymentMethod = json_decode($orderInfo['payment_method'], true);
				$paymentMethod = explode('.', $paymentMethod['code']);
				if (str_contains($paymentMethod[1], 'credit')) {
					$orderExtend  = $this->db->query('Select * from ' . DB_PREFIX . 'order_extend where order_id=' . $orderId);
					$creditRemark = ' 信用卡末四碼' . ($orderExtend->row['card_no4']) ?? '';
				}

				$factory = new Factory([
					'hashKey' => $ecpayinvoiceHashkey,
					'hashIv'  => $ecpayinvoiceHashiv,
				]);
				$postService = $factory->create('PostWithAesJsonResponseService');

				$data = [
					'MerchantID'         => $ecpayinvoiceMid,
					'RelateNumber'       => $relateNumber,
					'CustomerID'         => '',
					'CustomerIdentifier' => $customerIdentifier,
					'CustomerName'       => $customerName,
					'CustomerAddr'       => $customerAddr,
					'CustomerPhone'      => $orderInfo['telephone'],
					'CustomerEmail'      => $orderInfo['email'],
					'ClearanceMark'      => '',
					'Print'              => $isPrint,
					'Donation'           => $isdonation,
					'LoveCode'           => $loveCode,
					'CarrierType'        => $carrierType,
					'CarrierNum'         => $carrierNum,
					'TaxType'            => 1,
					'SalesAmount'        => $subTotalReal,
					'Items'              => $items,
					'InvType'            => '07',
					'vat'                => '',
					'InvoiceRemark'      => 'OC4_ECPayInvoice' . $creditRemark,
				];

				$input = [
					'MerchantID' => $ecpayinvoiceMid,
					'RqHeader' => [
						'Timestamp' => time(),
						'Revision' => '3.0.0',
					],
					'Data' => $data,
				];

				$apiInfo = $this->helper->get_ecpay_invoice_api_info('issue', $ecpayinvoiceMid);
				$returnInfo = $postService->post($input, $apiInfo['action']);
			} catch (Exception $e) {
				// 例外錯誤處理。
				$sMsg = $e->getMessage();
			}

			// 5.有錯誤訊息或回傳狀態RtnCode不等於1 則不寫入DB
			if ($sMsg != '' || !isset($returnInfo['Data']['RtnCode']) || $returnInfo['Data']['RtnCode'] != 1) {
				$sMsg .= '綠界科技電子發票自動開立訊息' ;
				$sMsg .= (isset($returnInfo)) ? print_r($returnInfo, true) : '' ;

				// A.寫入LOG
				$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$orderId . "', order_status_id = '" . (int)$orderInfo['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
			} else {
				// 無條件進位 金額有差異，寫入LOG提醒管理員
				if ($sMsgP2 != '') {
					$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$orderId . "', order_status_id = '" . (int)$orderInfo['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsgP2) . "', date_added = NOW()");
				}

				// A.更新發票號碼欄位
				$ecpayInvoiceNo = $returnInfo['Data']['InvoiceNo'];

				// B.整理發票號碼並寫入DB
				$invoiceNoPre 	= substr($ecpayInvoiceNo ,0 ,2) ;
				$invoiceNo 		= substr($ecpayInvoiceNo ,2) ;

				// C.回傳資訊轉陣列提供history資料寫入
				$sMsg .= '綠界科技電子發票自動開立訊息' ;
				$sMsg .= print_r($returnInfo, true);

				// D.更新發票資訊
				$this->updateInvoiceInfo($orderId, ['relate_number' => $relateNumber, 'random_number' => $returnInfo['Data']['RandomNumber'], 'invoice_process' => 1]);
				$this->db->query("UPDATE `" . DB_PREFIX . "order` SET invoice_no = '" . $invoiceNo . "', invoice_prefix = '" . $this->db->escape($invoiceNoPre) . "' WHERE order_id = '" . (int)$orderId . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$orderId . "', order_status_id = '" . (int)$orderInfo['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
			}
		} else {
			// A.寫入LOG
			$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$orderId . "', order_status_id = '" . (int)$orderInfo['order_status_id'] . "', notify = '0', comment = '" . $this->db->escape($sMsg) . "', date_added = NOW()");
		}
	}

	// 取得發票資訊
	public function getInvoiceInfo(int $order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "invoice_info` WHERE `order_id` = '" . (int)$order_id . "'");

		if ($query->num_rows) {
			return $query->row;
		} else {
			return [];
		}
	}

	// 更新發票資訊
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

    /**
     * 統一編號驗證
     *
     * @param  string $uniformNumbers
     * @return array  $result
     */
    public function checkUniformNumbers($uniformNumbers) {
        // 預設驗證結果
        $result = [
            'code' => '1',
            'msg'  => ''
        ];

        if ($uniformNumbers == '') {
            $result['code'] = '1010';
            $result['msg']  = 'error_uniform_numbers';
        } else {
            if (!preg_match('/^[0-9]{8}$/', $uniformNumbers)) {
                $result['code'] = '1011';
                $result['msg']  = 'error_uniform_numbers';
            }
        }

        return $result;
    }

	/**
     * 公司行號驗證
     *
     * @param  string $customerCompany
     * @return array  $result
     */
    public function checkCustomerCompany($customerCompany, $carrierType) {
        // 預設驗證結果
        $result = [
            'code' => '1',
            'msg'  => ''
        ];

		if ($carrierType == '1') {
			if ($customerCompany == '') {
				$result['code'] = '1010';
				$result['msg']  = 'error_customer_company';
			}
		}

        return $result;
    }

    /**
     * 捐贈碼驗證
     *
     * @param  string $loveCode
     * @param  bool   $switch
     * @return array  $result
     */
    public function checkLoveCode($loveCode, $switch) {
        // 預設驗證結果
        $result = [
            'code' => '1',
            'msg'  => ''
        ];

        if ($loveCode == '') {
            $result['code'] = '1020';
            $result['msg']  = 'error_love_code';
        } else {
            if (!preg_match('/^([xX]{1}[0-9]{2,6}|[0-9]{3,7})$/', $loveCode)) {
                $result['code'] = '1021';
                $result['msg']  = 'error_love_code';
            } else {
                // 呼叫 SDK 捐贈碼驗證
                if ($switch) {
                    $invoiceApiInfo = $this->helper->get_ecpay_invoice_api_info('check_Love_code', $this->config->get($this->setting_prefix . 'mid'));

                    try {
                        $factory = new Factory([
                            'hashKey' 	=> $this->config->get($this->setting_prefix . 'hashkey'),
                            'hashIv' 	=> $this->config->get($this->setting_prefix . 'hashiv'),
                        ]);

                        $postService = $factory->create('PostWithAesJsonResponseService');

                        $data = [
                            'MerchantID' 	=> $this->config->get($this->setting_prefix . 'mid'),
                            'LoveCode' 		=> $loveCode,
                        ];
                        $input = [
                            'MerchantID' => $this->config->get($this->setting_prefix . 'mid'),
                            'RqHeader' => [
                                'Timestamp' => time(),
                                'Revision' => '3.0.0',
                            ],
                            'Data' => $data,
                        ];

                        $response = $postService->post($input, $invoiceApiInfo['action']);

                        // 呼叫財政部API失敗
                        if (isset($response['Data']['RtnCode']) && $response['Data']['RtnCode'] == 9000001) {
                            $result['code'] = '1022';
                            $result['msg']  = 'error_ministry_of_finance';
                        }

                        // SDK 捐贈碼驗證失敗
                        if (!isset($response['Data']['RtnCode']) || $response['Data']['RtnCode'] != 1 || $response['Data']['IsExist'] == 'N') {
                            $result['code'] = '1023';
                            $result['msg']  = 'error_love_code';
                        }
                    } catch (RtnException $e) {
                        $result['code'] = '1029';
                        $result['msg']  = wp_kses_post('(' . $e->getCode() . ')' . $e->getMessage()) . PHP_EOL;
                        return $result;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 自然人憑證驗證
     *
     * @param  string $carrierNum
     * @return array  $result
     */
    public function checkCitizenDigitalCertificate($carrierNum) {
        // 預設驗證結果
        $result = [
            'code' => '1',
            'msg'  => ''
        ];

        if ($carrierNum == '') {
            $result['code'] = '1030';
            $result['msg'] = 'error_carrier_num';
        } else {
            if (!preg_match('/^[a-zA-Z]{2}\d{14}$/', $carrierNum)) {
                $result['code'] = '1031';
                $result['msg'] = 'error_carrier_num';
            }
        }

        return $result;
    }

    /**
     * 手機條碼驗證
     *
     * @param  string $carrierNum
     * @param  bool   $switch
     * @return array  $result
     */
    public function checkPhoneBarcode($carrierNum, $switch) {
        // 預設驗證結果
        $result = [
            'code' => '1',
            'msg'  => ''
        ];

        if ($carrierNum == '') {
            $result['code'] = '1040';
            $result['msg'] = 'error_carrier_num';
        } else {
            if (!preg_match('/^\/{1}[0-9a-zA-Z+-.]{7}$/', $carrierNum)) {
                $result['code'] = '1041';
                $result['msg'] = 'error_carrier_num';
            } else {
                // 呼叫 SDK 手機條碼驗證
                if ($switch) {
                    $invoiceApiInfo = $this->helper->get_ecpay_invoice_api_info('check_barcode', $this->config->get($this->setting_prefix . 'mid'));

                    try {
                        $factory = new Factory([
                            'hashKey' 	=> $this->config->get($this->setting_prefix . 'hashkey'),
                            'hashIv' 	=> $this->config->get($this->setting_prefix . 'hashiv'),
                        ]);

                        $postService = $factory->create('PostWithAesJsonResponseService');

                        $data = [
                            'MerchantID' 	=> $this->config->get($this->setting_prefix . 'mid'),
                            'BarCode' 		=> $carrierNum,
                        ];

                        $input = [
                            'MerchantID' => $this->config->get($this->setting_prefix . 'mid'),
                            'RqHeader' => [
                                'Timestamp' => time(),
                                'Revision' => '3.0.0',
                            ],
                            'Data' => $data,
                        ];

                        $response = $postService->post($input, $invoiceApiInfo['action']);

                        // 呼叫財政部API失敗
                        if (isset($response['Data']['RtnCode']) && $response['Data']['RtnCode'] == 9000001) {
                            $result['code'] = '1042';
                            $result['msg'] = 'error_ministry_of_finance';
                        }

                        // SDK 手機條碼驗證失敗
                        if (!isset($response['Data']['RtnCode']) || $response['Data']['RtnCode'] != 1 || $response['Data']['IsExist'] == 'N') {
                            $result['code'] = '1043';
                            $result['msg'] = 'error_carrier_num';
                        }
                    } catch (RtnException $e) {
                        $result['code'] = '1049';
                        $result['msg']  = wp_kses_post('(' . $e->getCode() . ')' . $e->getMessage()) . PHP_EOL;
                        return $result;
                    }
                }
            }
        }

        return $result;
    }
}
?>