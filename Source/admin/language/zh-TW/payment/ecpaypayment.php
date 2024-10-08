<?php
// Heading
$_['heading_title']  = '綠界金流模組';

// Text
$_['ecpaypayment_text_success']           = '成功: ' . $_['heading_title'] . '設定已修改!';
$_['ecpaypayment_text_extension']         = '擴充功能';
$_['ecpaypayment_text_edit']              = '編輯' . $_['heading_title'];
$_['ecpaypayment_text_enabled']           = '啟用';
$_['ecpaypayment_text_disabled']          = '停用';
$_['ecpaypayment_text_credit']            = '信用卡(一次付清)';
$_['ecpaypayment_text_credit_3']          = '信用卡(3期)';
$_['ecpaypayment_text_credit_6']          = '信用卡(6期)';
$_['ecpaypayment_text_credit_12']         = '信用卡(12期)';
$_['ecpaypayment_text_credit_18']         = '信用卡(18期)';
$_['ecpaypayment_text_credit_24']         = '信用卡(24期)';
$_['ecpaypayment_text_webatm']            = '網路ATM';
$_['ecpaypayment_text_atm']               = 'ATM';
$_['ecpaypayment_text_cvs']               = '超商代碼';
$_['ecpaypayment_text_barcode']           = '超商條碼';
$_['ecpaypayment_text_cod'] 		      = '貨到付款';
$_['ecpaypayment_text_bnpl'] 		      = '無卡分期';
$_['ecpaypayment_text_twqr'] 		      = '歐付寶TWQR';
$_['ecpaypayment_text_dca'] 		      = '定期定額';
$_['ecpaypayment_text_applepay']          = 'Apple Pay';
$_['ecpaypayment_text_unionpay']          = '銀聯卡';

// Entry
$_['ecpaypayment_entry_status']           = '狀態';
$_['ecpaypayment_entry_merchant_id']      = '商店代號';
$_['ecpaypayment_entry_hash_key']         = '金鑰';
$_['ecpaypayment_entry_hash_iv']          = '向量';
$_['ecpaypayment_entry_test_mode']        = '測試模式';
$_['ecpaypayment_entry_test_mode_info']   = '若在正式模式下切換為測試模式將會影響訂單接收綠界付款結果通知';
$_['ecpaypayment_entry_payment_methods']  = '付款方式';
$_['ecpaypayment_entry_create_status']    = '訂單建立狀態';
$_['ecpaypayment_entry_success_status']   = '付款完成狀態';
$_['ecpaypayment_entry_failed_status']    = '付款失敗狀態';
$_['ecpaypayment_entry_geo_zone']         = '適用地區';
$_['ecpaypayment_entry_sort_order']       = '排序次序';
$_['ecpaypayment_entry_dca_period_type']  = '定期定額週期種類';
$_['ecpaypayment_entry_dca_frequency']    = '定期定額執行頻率';
$_['ecpaypayment_entry_dca_exec_times']   = '定期定額執行次數';

// Error
$_['ecpaypayment_error_permission']       = '警告：您沒有修改綠界整合金流模組的權限！';
$_['ecpaypayment_error_merchant_id']      = '[商店代號] 不可為空！';
$_['ecpaypayment_error_hash_key']         = '[金鑰] 不可為空！';
$_['ecpaypayment_error_hash_iv']          = '[向量] 不可為空！';

// DCA Error
$_['ecpaypayment_error_dca_frequency_y']  = '當 PeriodType 設為 Y 時，只可設定值為 1 (年)';
$_['ecpaypayment_error_dca_exec_times_y'] = '當 PeriodType 設為 Y 時，最多可設 9 次';
$_['ecpaypayment_error_dca_frequency_m']  = '當 PeriodType 設為 M 時，可設定值為 1~12 (月)';
$_['ecpaypayment_error_dca_exec_times_m'] = '當 PeriodType 設為 M 時，最多可設 99 次';
$_['ecpaypayment_error_dca_frequency_d']  = '當 PeriodType 設為 D 時，可設定值為 1~365 (天)';
$_['ecpaypayment_error_dca_exec_times_d'] = '當 PeriodType 設為 D 時，最多可設 999 次';
