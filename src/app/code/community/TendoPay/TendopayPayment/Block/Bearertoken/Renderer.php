<?php

class TendoPay_TendopayPayment_Block_Bearertoken_Renderer extends Mage_Adminhtml_Block_System_Config_Form_Field{
    protected function _getElementHtml($element) {
        $element->setDisabled('disabled');
        $element->setValue(unserialize($element->getValue()));
        return parent::_getElementHtml($element);
    }
}