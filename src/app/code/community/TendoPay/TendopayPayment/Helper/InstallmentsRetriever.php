<?php

/**
 * Class TendoPay_TendopayPayment_Helper_InstallmentsRetriever
 */
class TendoPay_TendopayPayment_Helper_InstallmentsRetriever extends Mage_Core_Helper_Abstract
{
	private $product_price;

	public function __construct( $product_price ) {
		$this->product_price = $product_price;
	}

    /**
     * @param $amount
     * @return mixed
     * @throws TendoPay_Integration_Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
	public function get_example_payment( $amount ) {
		$repayment_calculator = new TendoPay_TendopayPayment_Helper_RepaymentCalculatorEndpoint();
		$installment_amount   = $repayment_calculator->get_installment_amount( $amount );
		return $installment_amount;
	}
}
