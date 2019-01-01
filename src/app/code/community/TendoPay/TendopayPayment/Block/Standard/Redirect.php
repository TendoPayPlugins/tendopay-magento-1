<?php

class TendoPay_TendopayPayment_Block_Standard_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $standard = Mage::getModel('tendopay/standard');
        $tendopay_helper_data = Mage::helper('tendopay');
        if($getLastRealOrderId = Mage::getSingleton('checkout/session')->getLastRealOrderId()){
            $order = Mage::getModel('sales/order')->loadByIncrementId($getLastRealOrderId);
            $base = Mage::getModel('tendopay/standard');
            $merchantId = $tendopay_helper_data->getConfigValues($base->getAPIMerchantIDConfigField());
            $store = Mage::app()->getStore();

            $redirect_args = [
                $tendopay_helper_data->getAmountParam() => (int)$order->getGrandTotal(),
                $tendopay_helper_data->getAuthTokenParam() => $this->auth_token,
                $tendopay_helper_data->getTendopayCustomerReferenceOne() => (string)$getLastRealOrderId,
                $tendopay_helper_data->getTendopayCustomerReferencetwo() => "magento1_order_" . $getLastRealOrderId,
                $tendopay_helper_data->getRedirectUrlParam() => $tendopay_helper_data->getRedirectUrl(),
                $tendopay_helper_data->getVendorIdParam() => $merchantId,
                $tendopay_helper_data->getVendorParam() => $store->getName()
            ];
            $redirect_args_hash = $tendopay_helper_data->calculate($redirect_args);
            $redirect_args[ $tendopay_helper_data->getHashParam()] = $redirect_args_hash;

            $redirect_url = $tendopay_helper_data->get_redirect_uri();
            $redirect_url .= '?' . http_build_query($redirect_args);
            $redirect_url .= '&er=' . urlencode( $tendopay_helper_data->getCheckoutUrl() );

            $form = new Varien_Data_Form();
            $form->setAction($redirect_url)
                ->setId('tendopay_standard_checkout')
                ->setName('tendopay_standard_checkout')
                ->setMethod('GET')
                ->setUseContainer(true);
            foreach ($standard->getStandardCheckoutFormFields($this->auth_token) as $field => $value) {
                $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
            }
            $idSuffix = Mage::helper('core')->uniqHash();
            $submitButton = new Varien_Data_Form_Element_Submit(array(
                'value'    => $this->__('Click here if you are not redirected within 10 seconds...'),
            ));
            $id = "submit_to_tendopay_button_{$idSuffix}";
            $submitButton->setId($id);
            $form->addElement($submitButton);
            $html = '<html><body>';
            $html.= $this->__('You will be redirected to the Tendopay website in a few seconds.');
            $html.= $form->toHtml();
            $html.= '<script type="text/javascript">document.getElementById("tendopay_standard_checkout").submit();</script>';
            $html.= '</body></html>';
            return $html;
        }
    }
}