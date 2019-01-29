<?php
/**
 * TendoPay
 *
 * Do not edit or add to this file if you wish to upgrade to newer versions in the future.
 * If you wish to customize this module for your needs.
 *
 * @category   TendoPay
 * @package    TendoPay_TendopayPayment
 * @license    http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Class TendoPay_TendopayPayment_Helper_Response
 */
class TendoPay_TendopayPayment_Helper_Response extends Mage_Core_Helper_Abstract
{
    /**
     * @var Response
     */
    private $_body;

    /**
     * @var Response
     */
    private $_code;

    /**
     * TendoPay_TendopayPayment_Helper_Response constructor.
     * @param $code
     * @param $body
     */
    public function __construct($code, $body)
    {
        $this->_body = $body;
        $this->_code = $code;
    }

    /**
     * @return Response
     */
    public function get_body()
    {
        return $this->_body;
    }

    /**
     * @return Response
     */
    public function get_code()
    {
        return $this->_code;
    }
}