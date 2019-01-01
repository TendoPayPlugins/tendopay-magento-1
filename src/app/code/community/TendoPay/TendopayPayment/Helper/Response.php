<?php

class TendoPay_TendopayPayment_Helper_Response extends Mage_Core_Helper_Abstract{
	/**
	 * @var string $body Response body
	 */
	private $body;
	/**
	 * @var string $code Response code
	 */
	private $code;

	/**
	 * Response constructor.
	 *
	 * @param $code Response code
	 * @param $body Response body
	 */
	public function __construct( $code, $body ) {
		$this->body = $body;
		$this->code = $code;
	}

	/**
	 * Returns body of the response from TendoPay API
	 *
	 * @return string the response body
	 */
	public function get_body() {
		return $this->body;
	}

	/**
	 * Returns response code from TendoPay API
	 *
	 * @return string the response code
	 */
	public function get_code() {
		return $this->code;
	}
}
