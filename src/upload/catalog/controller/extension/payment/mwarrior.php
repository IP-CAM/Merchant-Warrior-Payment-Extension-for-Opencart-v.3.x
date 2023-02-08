<?php

class ControllerExtensionPaymentMWarrior extends Controller {
	public function index() {
		
		$this->load->language('extension/payment/mwarrior');
		
		$data['text_credit_card']     = $this->language->get('text_credit_card');
		$data['text_wait']            = $this->language->get('text_wait');
		$data['entry_cc_owner']       = $this->language->get('entry_cc_owner');
		$data['entry_cc_number']      = $this->language->get('entry_cc_number');
		$data['entry_cc_expire_date'] = $this->language->get('entry_cc_expire_date');
		$data['entry_cc_cvv2']        = $this->language->get('entry_cc_cvv2');
		$data['button_confirm']       = $this->language->get('button_confirm');
		$data['button_back']          = $this->language->get('button_back');
		$data['months']               = [];
		for ($i = 1; $i <= 12; $i++) {
			$data['months'][] = [
				'text'  => strftime('%B', mktime(0, 0, 0, $i, 1, 2000)),
				'value' => sprintf('%02d', $i)
			];
		}
		$today = getdate();

		$data['year_expire'] = [];
		for ($i = $today['year']; $i < $today['year'] + 11; $i++) {
			$data['year_expire'][] = [
				'text'  => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
				'value' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
			];
		}
		
		if (!empty($this->config->get('payment_mwarrior_use_hosted_payments'))) {
			return $this->load->view('extension/payment/mwarrior_hosted_payments', $data);
		} else { // std
			return $this->load->view('extension/payment/mwarrior', $data);
		}

	}
	
	public function hosted_payments_return() {
		
		$error = '';
		
		$this->load->language('extension/payment/mwarrior');
		$this->load->model('checkout/order');
		
		$result           = $this->request->get;
		$order_id         = $this->request->get['order_id'] ?? 0;
		$session_order_id = $this->session->data['order_id'] ?? '';
		
		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		if (!$order_id) {
			$error = $this->language->get('text_hosted_payments_unknown_error');
			$this->log->write('empty order_id');
		} elseif ($order_id != $session_order_id) {
			$error = $this->language->get('text_hosted_payments_unknown_error');
			$this->log->write('order_id '.$order_id.' is not equal to the session order_id '.$session_order_id);
		} elseif (!$order_info) {
			$error = $this->language->get('text_hosted_payments_unknown_error');
			$this->log->write('order not found '.$order_id);
		} else {
			
			$status = $result['status'] ?? '';
			
			$hash_salt = $this->getHashSaltByOrderInfo($order_info);
			$hash      = $this->getResponseHashByResultAndSalt($result, $hash_salt);
			
			if ($hash && ($hash != ($result['hash'] ?? ''))) {
				$error = $this->language->get('text_hosted_payments_unknown_error');
				$this->log->write('wrong hash, expected: '.$hash);
			} elseif (strtolower($status) != 'approved') {
				$error = $this->language->get('text_hosted_payments_error').' : '.$status.' - '.($result['message'] ?? '' );
			}
			
		}
		
		if ($error) {
			$this->session->data['error'] = $error;
			$this->response->redirect($this->url->link('checkout/checkout'));
		} else { // success
			$order_history_comment = $this->language->get('text_payment_successful').'. ';
			if (!empty($result['message'])) {
				$order_history_comment .= ' '.$result['message'];
			} else {
				$order_history_comment .= ' '.($result['status'] ?? '');
			}
			$order_history_comment .= ' '.($result['paymentCardNumber'] ?? '');
			$order_history_comment .= ' '.($result['reference'] ?? '');
			$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_mwarrior_order_status_id'), '', true);
			
			$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_mwarrior_order_status_id'), $order_history_comment, false);
			
			$this->response->redirect($this->url->link('checkout/success'));
		}
		
	}
	
	// it looks like the payment gate send nothing to 'notify' in case of hosted payments, so the function does nothing now
	public function hosted_payments_notify() {
		
	}
	
	public function getHashSaltByOrderInfo($order_info) {
		
		$data = [
			'order_id' => $order_info['order_id'],
			'date'     => md5($order_info['date_added']),
			'total'    => $this->getOrderAmountByOrderInfo($order_info),
		];
		
		$salt = base64_encode(json_encode($data));
		
		return $salt;
	}
	
	protected function getOrderAmountByOrderInfo($order_info) {
		return number_format($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false), 2,'.','');
	}
	
	public function hosted_payments_form() {
		
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		// Get the Customer's IP Address (This is only at best a guess)
		if(!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			$remoteIP = $this->request->server['HTTP_X_FORWARDED_FOR'];
		} else if (!empty($this->request->server['REMOTE_ADDR'])) {
			$remoteIP = $this->request->server['REMOTE_ADDR'];
		} else {
			$remoteIP = '';
		}
		$amount = $this->getOrderAmountByOrderInfo($order_info);
		
		$customer_country_code = '';
		if (!empty($order_info['payment_country_id'])) {
			$this->load->model('localisation/country');
			$country               = $this->model_localisation_country->getCountry($order_info['payment_country_id']);
			$customer_country_code = trim($country['iso_code_2']) ?? '';
		}
		
		$url_return = $this->url->link('extension/payment/mwarrior/hosted_payments_return', 'order_id='.$order_info['order_id']);
		$url_notify = $this->url->link('extension/payment/mwarrior/hosted_payments_notify', 'order_id='.$order_info['order_id']);
		
		$requestData = [
			'method'              => 'processCard',
			'merchantUUID'        => $this->config->get('payment_mwarrior_merchant_id'),
			'apiKey'              => $this->config->get('payment_mwarrior_api_key'),
			'transactionAmount'   => $amount,
			'transactionCurrency' => trim($order_info['currency_code']),
			'transactionProduct'  => 'ORDER ID '.$order_info['order_id'],
			
			'returnURL' => $url_return,
			'notifyURL' => $url_notify,
			
			'customerName' => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
			
			'customerCountry'        => $customer_country_code,
			'customerState'          => $order_info['payment_zone_code'],
			'customerCity'           => $order_info['payment_city'],
			'customerAddress'        => $order_info['payment_address_1'],
			'customerPostCode'       => $order_info['payment_postcode'],
			'customerPhone'          => $order_info['telephone'],
			'customerEmail'          => $order_info['email'],
			'transactionReferenceID' => $order_info['order_id'].' :: '.date($this->language->get('datetime_format')),
			//'custom1'     => 'cust 1',
			//'custom2'     => 'cust 2',
			//'custom3'     => 'cust 3',
			
		];
		
		$requestData['hashSalt'] = $this->getHashSaltByOrderInfo($order_info);
		$requestData['urlHash']  = $this->getURLHashByRequestData($requestData);
		$requestData['hash']     = $this->getTransactionHashByRequestData($requestData);
		
		if ($this->config->get('payment_mwarrior_gateway_mode') == '1') {
			$url = 'https://secure.merchantwarrior.com/';
		} else {
			$url = 'https://securetest.merchantwarrior.com/';
		}
		
		$data['request_data'] = $requestData;
		$data['action']       = $url;
		
		$json = ['success' => $this->load->view('extension/payment/mwarrior_hosted_payments_form', $data)];
		$this->response->setOutput(json_encode($json));
		
	}
	
	protected function getURLHashByRequestData($requestData) {
		return md5(strtolower(md5($this->config->get('payment_mwarrior_api_passphrase')) . $requestData['merchantUUID'] . $requestData['returnURL'] . $requestData['notifyURL']));
	}
	
	protected function getResponseHashByResultAndSalt($result, $hash_salt) {
		// md5( strtolower( md5(apiPassphrase) + hashSalt + merchantUUID + status + transactionID ) )
		return md5(strtolower(md5($this->config->get('payment_mwarrior_api_passphrase')) . $hash_salt . $this->config->get('payment_mwarrior_merchant_id') . ($result['status'] ?? '') . ($result['reference'] ?? '') ));
	}

	protected function getTransactionHashByRequestData($requestData) {
		return md5(strtolower(md5($this->config->get('payment_mwarrior_api_passphrase')) . $requestData['merchantUUID'] . $requestData['transactionAmount'] . $requestData['transactionCurrency']));
	}
	
	protected function requestCURL($url, $requestData) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($requestData, '', '&'));
		$response = curl_exec($curl);
		return (object)['response' => $response, 'curl' => $curl];
	}
	
	public function send() {
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();
		foreach ($products as $product) {
			$productName = $product['name'];
		}
		// Get the Customer's IP Address (This is only at best a guess)
		if(!empty($this->request->server['HTTP_X_FORWARDED_FOR']))
		{
			$remoteIP = $this->request->server['HTTP_X_FORWARDED_FOR'];
		} else if (!empty($this->request->server['REMOTE_ADDR'])) {
			$remoteIP = $this->request->server['REMOTE_ADDR'];
		} else {
			$remoteIP = '';
		}
		$amount = $this->getOrderAmountByOrderInfo($order_info);
		
		$customer_country_code = '';
		if (!empty($order_info['payment_country_id'])) {
			$this->load->model('localisation/country');
			$country               = $this->model_localisation_country->getCountry($order_info['payment_country_id']);
			$customer_country_code = trim($country['iso_code_2']) ?? '';
		}
		
		$requestData = [
			'method'              => 'processCard',
			'merchantUUID'        => $this->config->get('payment_mwarrior_merchant_id'),
			'apiKey'              => $this->config->get('payment_mwarrior_api_key'),
			'transactionAmount'   => $amount,
			'transactionCurrency' => $this->config->get('payment_mwarrior_currency'),
			'transactionProduct'  => $productName,
			'customerName'        => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
			'customerCountry'     => $customer_country_code,
			'customerState'       => $order_info['payment_zone_code'],
			'customerCity'        => $order_info['payment_city'],
			'customerAddress'     => $order_info['payment_address_1'],
			'customerPostCode'    => $order_info['payment_postcode'],
			'customerPhone'       => $order_info['telephone'],
			'customerEmail'       => $order_info['email'],
			'customerIP'          => $remoteIP,
			'paymentCardNumber'   => str_replace(' ', '', $this->request->post['mwarrior_cc_number']),
			'paymentCardExpiry'   => $this->request->post['mwarrior_cc_expire_month'] . substr($this->request->post['mwarrior_cc_expire_year'], 2),
			'paymentCardCSC'      => $this->request->post['mwarrior_cc_cvv2'],
			'paymentCardName'     => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
		];
		$requestData['hash'] = $this->getTransactionHashByRequestData($requestData);
		
		if ($this->config->get('payment_mwarrior_gateway_mode') == '1') {
			$url = 'https://api.merchantwarrior.com/post/';
		} else {
			$url = 'https://base.merchantwarrior.com/post/';
		}
		
		$curl_data = $this->requestCURL($url, $requestData);
		$response  = $curl_data->response;
		
		$json = [];
		// Check for any errors
		if (curl_error($curl_data->curl)) {
			$json['error'] = 'Failed to connect to Merchant Warrior gateway:  ' . curl_error($curl_data->curl) . '(' . curl_errno($curl_data->curl) . ')';
		} else {
			// Close the object because we no longer need it
			curl_close($curl_data->curl);
			// Parse the xml
			$xml = simplexml_load_string($response);
			
			// Convert the result from a simpleXMLObject into an array
			$xml = json_decode(json_encode($xml), TRUE);
			// If the response was invalid, log it
			if (!isset($xml['responseCode']) || strlen($xml['responseCode']) < 1) {
				$json['error'] = 'Invalid response received from Merchant Warrior gateway: ';
			}
			if (isset($xml['responseCode']) && strlen($xml['responseCode']) > 0) {
				// Validate the response - the only successful code is 0
				$success = ((int)$xml['responseCode'] === 0 ) ? true : false;
				// Store the response data
				$responseCode = $xml['responseCode'];
				if (isset($xml['responseMessage'])) {
					$responseMessage = $xml['responseMessage'];
				}
				if (isset($xml['transactionID'])) {
					$responseTransaction = $xml['transactionID'];
				}
				if (isset($xml['authCode'])) {
					$responseAuth = $xml['authCode'];
				}
				// Set the message depending on the transaction status
				if ($success == true) {
					$message         = 'Payment Successful. Transaction ID: ' . $responseTransaction .'<br> Authorization Code: ' . $responseAuth . '<br>response Message: ' . $xml['responseMessage'];
					$json['success'] = $this->url->link('checkout/success', '');
				} else {
					$message = $xml['responseMessage'];
					// $json['error'] = 'Payment Declined. Response Message: ' . $xml['responseMessage'];
					$json['error'] = $xml['responseMessage'];
				}
			} else {
				$success       = false;
				$message       = 'Payment Error: Could not parse XML response';
				$json['error'] = 'Payment Error: Could not parse XML response';
			}
			if($success) {
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_mwarrior_order_status_id'), $message, true);
			} else {
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_mwarrior_order_status_declined_id'), $message, true);
			}
			$json['redirect'] = $this->url->link('checkout/success');
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
