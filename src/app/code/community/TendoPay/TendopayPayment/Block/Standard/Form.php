<?php

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
        $tendopay_helper_data = Mage::helper('tendopay');
        // logic borrowed from Mage_Paypal_Block_Standard_form
        $block = Mage::getConfig()->getBlockClassName('core/template');
        $block = new $block;
        $block->setTemplateHelper($this);
        $block->setTemplate($tendopay_helper_data->getTendopayCheckoutTitle());
        $this->setTemplate('tendopay_front/payment/redirect.phtml');
        $this->setMethodTitle('')->setMethodLabelAfterHtml($block->toHtml());
    }

    /**
     * Payment method code getter
     * @return string
     */
    public function getMethodCode()
    {
        $tendopay_helper_data = Mage::helper('tendopay');
        return $tendopay_helper_data->getTendopayMethodCode();
    }

    public function getRedirectMessage()
    {
        if ($this->hasData('redirect_message')) {
            return $this->getData('redirect_message');
        } else {
            return $this->getMethod()->getConfigData('message');
        }
    }
}