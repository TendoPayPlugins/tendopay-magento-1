<?php

require_once(Mage::getBaseDir('lib') . DS . 'TendoPay' . DS . 'vendor' . DS . 'autoload.php');
/**
 * Class TendoPay_TendopayPayment_Helper_RepaymentCalculatorEndpoint
 */
class TendoPay_TendopayPayment_Helper_RepaymentCalculatorEndpoint extends Mage_Core_Helper_Abstract
{
    /**
     * @return mixed
     */
    public function helper()
    {
        return Mage::helper('tendopay');
    }
	/**
	 * @param $amount
	 *
	 * @throws TendoPay_Integration_Exception
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function get_installment_amount( $amount ) {
        $amount = (double) $amount;
        $helper = $this->helper();
        $hash = $helper->calculate([ $amount ]);
        $url = sprintf( $helper->getRepaymentScheduleApiEndpointUri(), $amount );

        $this->client = new GuzzleHttp\Client(
            array(
                'base_uri' => $helper->get_base_api_url()
            )
        );
        $response = $this->client->request("GET", $url, [
            "headers" => $helper->get_default_headers()
        ]);

        if ( $response->getStatusCode() !== 200 ) {
            $helper->setException('Got response code != 200 while requesting for payment calculation');
        }
        $json = json_decode( (string) $response->getBody() );
        return $json->data->{$helper->getRepaymentCalculatorInstallmentAmount()};
	}
}