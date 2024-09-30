<?php

namespace Opencart\Admin\Controller\Extension\Ecpay\Payment;

class EcpayPayment extends \Opencart\System\Engine\Controller {

    private $error = array();
    private $separator = '';
    private $module_name = 'ecpaypayment';
    private $module_code = '';
    private $lang_prefix = '';
    private $setting_prefix = '';
    private $name_prefix = '';
    private $id_prefix = '';
    private $module_path = '';
    private $extension_route = 'marketplace/extension';
    private $url_secure = true;
    private $validate_fields = array(
        'merchant_id',
        'hash_key',
        'hash_iv'
    );

    // Constructor
    public function __construct($registry) {
        parent::__construct($registry);

        if (VERSION >= '4.0.2.0') {
			$this->separator = '.';
		} else {
			$this->separator = '|';
		}

        // Set the variables
        $this->module_code = 'payment_' . $this->module_name;
        $this->lang_prefix = $this->module_name .'_';
        $this->setting_prefix = 'payment_' . $this->module_name . '_';
        $this->name_prefix = 'payment_' . $this->module_name;
        $this->id_prefix = 'payment-' . $this->module_name;
        $this->module_path = 'extension/ecpay/payment/' . $this->module_name;
    }

    // Back-end config index page
    public function index() {
        // Load the translation file
        $this->load->language($this->module_path);

        // Set the title
        $heading_title = $this->language->get('heading_title');
        $this->document->setTitle($heading_title);

        // Token
        $token = $this->session->data['user_token'];

        // Get the translations
        $data['heading_title']  = $heading_title;

        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['button_save']    = $this->language->get('button_save');
        $data['button_back']  = $this->language->get('button_back');

        // Get ECPay translations
        $translation_names = array(
            'text_edit',
            'text_enabled',
            'text_disabled',
            'text_credit',
            'text_credit_3',
            'text_credit_6',
            'text_credit_12',
            'text_credit_18',
            'text_credit_24',
            'text_webatm',
            'text_atm',
            'text_barcode',
            'text_cvs',
            'text_cod',
            'text_bnpl',
            'text_twqr',
            'text_applepay',
            'text_unionpay',

            'text_dca',
            'entry_dca_period_type',
            'entry_dca_frequency',
            'entry_dca_exec_times',

            'entry_status',
            'entry_merchant_id',
            'entry_hash_key',
            'entry_hash_iv',
            'entry_test_mode',
            'entry_test_mode_info',
            'entry_payment_methods',
            'entry_create_status',
            'entry_success_status',
            'entry_failed_status',
            'entry_geo_zone',
            'entry_sort_order',
        );
        foreach ($translation_names as $name) {
            $data[$name] = $this->language->get($this->lang_prefix . $name);
        }
        unset($translation_names);

        // Get the errors
        if (isset($this->error['error_warning'])) {
            $data['error_warning'] = $this->error['error_warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Get ECPay errors
        foreach ($this->validate_fields as $name) {
            $error_name = $name . '_error';
            if (isset($this->error[$name])) {
                $data[$error_name] = $this->error[$this->id_prefix . '-' . $name];
            } else {
                $data[$error_name] = '';
            }

            unset($field_name, $error_name);
        }
        unset($error_fields);

        // Set the breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $token, $this->url_secure)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get($this->lang_prefix . 'text_extension'),
            'href' => $this->url->link(
                $this->extension_route,
                'user_token=' . $token . '&type=payment',
                $this->url_secure
            )
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/ecpay/payment/' . $this->module_name,
                'user_token=' . $token,
                $this->url_secure
            )
        );

        // Set the form action
        $data['action'] = $this->url->link(
            $this->module_path . $this->separator . 'save',
            'user_token=' . $token,
            $this->url_secure
        );

        // Set the back button
        $data['back'] = $this->url->link(
            $this->extension_route,
            'user_token=' . $token . '&type=payment',
            $this->url_secure
        );

        // Get ECPay options
        $options = array(
            'status',
            'merchant_id',
            'hash_key',
            'hash_iv',
            'test_mode',
            'payment_methods',
            'dca_period_type',
            'create_status',
            'success_status',
            'failed_status',
            'geo_zone_id',
            'sort_order',
            'dca_period_type',
            'dca_frequency',
            'dca_exec_times',
        );
        foreach ($options as $name) {
            $option_name = $this->setting_prefix . $name;
            if (isset($this->request->post[$option_name])) {
                $data[$name] = $this->request->post[$option_name];
            } else {
                $data[$name] = $this->config->get($option_name);
            }
            unset($option_name);
        }
        unset($options);

        // Set module status
        $data['module_statuses'] = array(
            array(
                'value' => '1',
                'text' => $this->language->get($this->lang_prefix . 'text_enabled')
            ),
            array(
                'value' => '0',
                'text' => $this->language->get($this->lang_prefix . 'text_disabled')
            )
        );

        // DCA options
        $data['dca_period_types'] = ['Y', 'M', 'D'];

        // Get the order statuses
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Get the geo zones
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        // 當前語系ID
		$data['config_language_id'] = $this->config->get('config_language_id');

		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();
        $data['languages'] = $languages;

        // View's setting
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['name_prefix'] = $this->name_prefix;
        $data['id_prefix'] = $this->id_prefix;

        $this->response->setOutput($this->load->view($this->module_path, $data));
    }

    public function save(){
		$this->load->language('extension/ecpay/payment/ecpaypayment');
		$json = [];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->load->model('setting/setting');

			// Save the setting
            $this->model_setting_setting->editSetting(
                $this->module_code,
                $this->request->post
            );

			$json['success'] = $this->language->get($this->lang_prefix . 'text_success');
		} else {
			$json['error'] = $this->error;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    protected function validate() {
        // Premission validate
        if (!$this->user->hasPermission('modify', $this->module_path)) {
            $this->error['error_warning'] = $this->language->get($this->lang_prefix . 'error_permission');
        }

        // Required fields validate
        foreach ($this->validate_fields as $name) {
            $field_name = $this->setting_prefix . $name;
            if (empty($this->request->post[$field_name])) {
                $this->error[$this->id_prefix . '-' . $name] = $this->language->get($this->lang_prefix . 'error_' . $name);
            }
            unset($field_name);
        }

        // 定期定額欄位驗證
        $dca_frequency = $this->request->post[$this->setting_prefix . 'dca_frequency'];
        $dca_exec_times = $this->request->post[$this->setting_prefix . 'dca_exec_times'];
        if ($dca_frequency != '' && $dca_exec_times != '') {
            switch ($this->request->post[$this->setting_prefix . 'dca_period_type']) {
                case 'Y':
                    if ($dca_frequency != '1') {
                        $this->error[$this->id_prefix . '-dca-frequency'] = $this->language->get($this->lang_prefix . 'error_dca_frequency_y');
                    }
                    if ($dca_exec_times < 2 || $dca_exec_times > 9) {
                        $this->error[$this->id_prefix . '-dca-exec-times'] = $this->language->get($this->lang_prefix . 'error_dca_exec_times_y');
                    }
                    break;
                case 'M':
                    if ($dca_frequency < 1 || $dca_frequency > 12) {
                        $this->error[$this->id_prefix . '-dca-frequency'] = $this->language->get($this->lang_prefix . 'error_dca_frequency_m');
                    }
                    if ($dca_exec_times < 2 || $dca_exec_times > 99) {
                        $this->error[$this->id_prefix . '-dca-exec-times'] = $this->language->get($this->lang_prefix . 'error_dca_exec_times_m');
                    }
                    break;
                case 'D':
                    if ($dca_frequency < 1 || $dca_frequency > 365) {
                        $this->error[$this->id_prefix . '-dca-frequency'] = $this->language->get($this->lang_prefix . 'error_dca_frequency_d');
                    }
                    if ($dca_exec_times < 2 || $dca_exec_times > 999) {
                        $this->error[$this->id_prefix . '-dca-exec-times'] = $this->language->get($this->lang_prefix . 'error_dca_exec_times_d');
                    }
                    break;
            }
        }

        return !$this->error;
    }

    // install
    public function install() {
        $this->load->model('extension/ecpay/payment/ecpaypayment');
        $this->model_extension_ecpay_payment_ecpaypayment->install();
    }

    // uninstall
    public function uninstall() {
		$this->load->model('extension/ecpay/payment/ecpaypayment');

		$this->model_extension_ecpay_payment_ecpaypayment->uninstall();

        $this->load->model('setting/setting');
        $this->load->model('setting/extension');

        $this->model_setting_setting->deleteSetting($this->request->get['extension']);
        $this->model_setting_extension->uninstall($this->module_code, $this->request->get['extension']);
    }
}
