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
 * Class TendoPay_TendopayPayment_Block_Standard_Form
 */
class TendoPay_TendopayPayment_Block_Standard_Form extends Mage_Payment_Block_Form
{
    /**
     * Payment method code
     * @var string
     */
    protected $_methodCode = TendoPay_TendopayPayment_Helper_Data::METHOD_WPS;

    /**
     * Set template and redirect message
     */
    protected function _construct()
    {
        parent::_construct();

        $visibility = false;
        $tendopayHelperData = Mage::helper('tendopay');
        $base = Mage::getModel('tendopay/standard');
        $merchantId = $tendopayHelperData->getConfigValues($base->getAPIMerchantIDConfigField());

        if (!empty($tendopayHelperData->getConfigValues($base->getAPIMerchantIDConfigField())) &&
            !empty($tendopayHelperData->getConfigValues($base->getAPIMerchantSecretConfigField())) &&
            !empty($tendopayHelperData->getConfigValues($base->getAPIClientIdConfigField())) &&
            !empty($tendopayHelperData->getConfigValues($base->getAPIClientSecretConfigField()))
        ) {
            $visibility = true;
        }
        if($visibility) {
            $tendopayHelperData = Mage::helper('tendopay');
            // logic borrowed from Mage_Paypal_Block_Standard_form
            $block = Mage::getConfig()->getBlockClassName('core/template');
            $block = new $block;
            $block->setTemplateHelper($this);
            $block->setTemplate($tendopayHelperData->getTendopayCheckoutTitle());
            $this->setTemplate('tendopay/payment/redirect.phtml');
            $this->setMethodTitle('')->setMethodLabelAfterHtml($block->toHtml());
        }
    }

    /**
     * Payment method code getter
     * @return mixed
     */
    public function getMethodCode()
    {
        $tendopayHelperData = Mage::helper('tendopay');
        return $tendopayHelperData->getTendopayMethodCode();
    }

    /**
     * @return mixed
     */
    public function getRedirectMessage()
    {
        if ($this->hasData('redirect_message')) {
            return $this->getData('redirect_message');
        } else {
            return $this->getMethod()->getConfigData('message');
        }
    }
}