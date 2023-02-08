<?php

class ControllerExtensionPaymentMWarrior extends Controller {
	private $error = [];

	public function index() {
		$this->load->language('extension/payment/mwarrior');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'href'      => HTTPS_SERVER . 'index.php?route=common/dashboard&user_token=' . $this->session->data['user_token'],
			'text'      => $this->language->get('text_home'),
			'separator' => false
		];
		$data['breadcrumbs'][] = [
			'href'      => HTTPS_SERVER . 'index.php?route=payment/mwarrior&user_token=' . $this->session->data['user_token'],
			'text'      => $this->language->get('heading_title'),
			'separator' => false
		];

		// Edit form
		$this->load->model('setting/setting');

		if(($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_mwarrior', $this->request->post);
			$data['success'] = $this->language->get('text_success');
			$this->response->redirect( HTTPS_SERVER . 'index.php?route=extension/payment/mwarrior&user_token=' . $this->session->data['user_token']);
		}

		// Set the error msg
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		if (isset($this->error['merchant_id'])) {
			$data['error_merchant_id'] = $this->error['merchant_id'];
		} else {
			$data['error_merchant_id'] = '';
		}

		if(isset($this->error['api_key'])) {
			$data['error_api_key'] = $this->error['api_key'];
		} else {
			$data['error_api_key'] = '';
		}

		if(isset($this->error['api_passphrase'])) {
			$data['error_api_passphrase'] = $this->error['api_passphrase'];
		} else {
			$data['error_api_passphrase'] = '';
		}

		if (isset($this->error['currency'])) {
			$data['error_currency'] = $this->error['currency'];
		} else {
			$data['error_currency'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit']     = $this->language->get('text_edit');
		$data['text_enabled']  = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_live']     = $this->language->get('text_live');
		$data['text_test']     = $this->language->get('text_test');

		$data['entry_merchant_id']           = $this->language->get('entry_merchant_id');
		$data['entry_api_key']               = $this->language->get('entry_api_key');
		$data['entry_api_passphrase']        = $this->language->get('entry_api_passphrase');
		$data['entry_currency']              = $this->language->get('entry_currency');
		$data['entry_order_status']          = $this->language->get('entry_order_status');
		$data['entry_order_status_declined'] = $this->language->get('entry_order_status_declined');

		$data['entry_status']       = $this->language->get('entry_status');
		$data['entry_sort_order']   = $this->language->get('entry_sort_order');
		$data['entry_gateway_mode'] = $this->language->get('entry_gateway_mode');

		$data['button_save']   = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = HTTPS_SERVER . 'index.php?route=extension/payment/mwarrior&user_token=' . $this->session->data['user_token'];

		$data['cancel'] = HTTPS_SERVER . 'index.php?route=extension/payment&user_token=' . $this->session->data['user_token'];
		
		if(isset($this->request->post['payment_mwarrior_use_hosted_payments'])) {
			$data['payment_mwarrior_use_hosted_payments'] = $this->request->post['payment_mwarrior_use_hosted_payments'];
		} else {
			$data['payment_mwarrior_use_hosted_payments'] = $this->config->get('payment_mwarrior_use_hosted_payments');
		}

		// set the value
		if(isset($this->request->post['payment_mwarrior_merchant_id'])) {
			$data['payment_mwarrior_merchant_id'] = $this->request->post['payment_mwarrior_merchant_id'];
		} else {
			$data['payment_mwarrior_merchant_id'] = $this->config->get('payment_mwarrior_merchant_id');
		}

		if(isset($this->request->post['payment_mwarrior_api_key'])) {
			$data['payment_mwarrior_api_key'] = $this->request->post['payment_mwarrior_api_key'];
		} else {
			$data['payment_mwarrior_api_key'] = $this->config->get('payment_mwarrior_api_key');
		}

		if(isset($this->request->post['payment_mwarrior_api_passphrase'])) {
			$data['payment_mwarrior_api_passphrase'] = $this->request->post['payment_mwarrior_api_passphrase'];
		} else {
			$data['payment_mwarrior_api_passphrase'] = $this->config->get('payment_mwarrior_api_passphrase');
		}

		if (isset($this->request->post['payment_mwarrior_currency'])) {
			$data['payment_mwarrior_currency'] = $this->request->post['payment_mwarrior_currency'];
		} else {
			$data['payment_mwarrior_currency'] = $this->config->get('payment_mwarrior_currency');
		}

		if (isset($this->request->post['payment_mwarrior_gateway_mode'])) {
			$data['payment_mwarrior_gateway_mode'] = $this->request->post['payment_mwarrior_gateway_mode'];
		} else {
			$data['payment_mwarrior_gateway_mode'] = $this->config->get('payment_mwarrior_gateway_mode');
		}

		if(isset($this->request->post['payment_mwarrior_order_status_id'])) {
			$data['payment_mwarrior_order_status_id'] = $this->request->post['payment_mwarrior_order_status_id'];
		} else {
			$data['payment_mwarrior_order_status_id'] = $this->config->get('payment_mwarrior_order_status_id');
		}

		if(isset($this->request->post['payment_mwarrior_order_status_declined_id'])) {
			$data['payment_mwarrior_order_status_declined_id'] = $this->request->post['payment_mwarrior_order_status_declined_id'];
		} else {
			$data['payment_mwarrior_order_status_declined_id'] = $this->config->get('payment_mwarrior_order_status_declined_id');
		}

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if(isset($this->request->post['payment_mwarrior_status'])) {
			$data['payment_mwarrior_status'] = $this->request->post['payment_mwarrior_status'];
		} else {
			$data['payment_mwarrior_status'] = $this->config->get('payment_mwarrior_status');
		}

		if(isset($this->request->post['payment_mwarrior_sort_order'])) {
			$data['payment_mwarrior_sort_order'] = $this->request->post['payment_mwarrior_sort_order'];
		} else {
			$data['payment_mwarrior_sort_order'] = $this->config->get('payment_mwarrior_sort_order');
		}

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/mwarrior', $data));
	}

	protected function validate() {
		if(!$this->request->post['payment_mwarrior_merchant_id']) {
			$this->error['merchant_id'] = $this->language->get('error_merchant_id');
		}

		if(!$this->request->post['payment_mwarrior_api_key']) {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}

		if(!$this->request->post['payment_mwarrior_api_passphrase']) {
			$this->error['api_passphrase'] = $this->language->get('error_api_passphrase');
		}

		if (!$this->request->post['payment_mwarrior_currency']) {
			$this->error['currency'] = $this->language->get('error_currency');
		}

		if(!$this->error) {
			return true;
		} else {
			return false;
		}
	}
}
