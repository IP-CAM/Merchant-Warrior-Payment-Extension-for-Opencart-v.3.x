<?php

class ModelExtensionPaymentMWarrior extends Model {
	public function getMethod($address, $total) {
		$this->language->load('extension/payment/mwarrior');

		if ($this->config->get('payment_mwarrior_status')) {
			$status = TRUE;
		} else {
			$status = FALSE;
		}

		$method_data = [];

		if ($status) {
			$method_data = [
				'code'       => 'mwarrior',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_mwarrior_sort_order')
			];
		}

		return $method_data;
	}
}
