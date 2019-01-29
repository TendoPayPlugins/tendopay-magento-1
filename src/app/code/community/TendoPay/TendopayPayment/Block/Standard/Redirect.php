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
 * Class TendoPay_TendopayPayment_Block_Standard_Redirect
 */
class TendoPay_TendopayPayment_Block_Standard_Redirect extends Mage_Core_Block_Abstract
{
    /**
     * @return string
     */
    protected function _toHtml()
    {
        $standard = Mage::getModel('tendopay/standard');
        $tendopayHelperData = Mage::helper('tendopay');
        if ($getLastRealOrderId = Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($getLastRealOrderId);
            $base = Mage::getModel('tendopay/standard');
            $merchantId = $tendopayHelperData->getConfigValues($base->getAPIMerchantIDConfigField());
            $store = Mage::app()->getStore();

            $redirectArgs = array(
                $tendopayHelperData->getAmountParam() => (int)$order->getGrandTotal(),
                $tendopayHelperData->getAuthTokenParam() => $this->auth_token,
                $tendopayHelperData->getTendopayCustomerReferenceOne() => (string)$getLastRealOrderId,
                $tendopayHelperData->getTendopayCustomerReferencetwo() => "magento1_order_" . $getLastRealOrderId,
                $tendopayHelperData->getRedirectUrlParam() => $tendopayHelperData->getRedirectUrl(),
                $tendopayHelperData->getVendorIdParam() => $merchantId,
                $tendopayHelperData->getVendorParam() => $store->getName()
            );
            $redirectArgsHash = $tendopayHelperData->calculate($redirectArgs);
            $redirectArgs[$tendopayHelperData->getHashParam()] = $redirectArgsHash;

            $redirectUrl = $tendopayHelperData->get_redirect_uri();
            $redirectUrl .= '?' . http_build_query($redirectArgs);
            $redirectUrl .= '&er=' . urlencode($tendopayHelperData->getCheckoutUrl());

            $form = new Varien_Data_Form();
            $form->setAction($redirectUrl)
                ->setId('tendopay_standard_checkout')
                ->setName('tendopay_standard_checkout')
                ->setMethod('GET')
                ->setUseContainer(true);
            foreach ($standard->getStandardCheckoutFormFields($this->auth_token) as $field => $value) {
                $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
            }

            $idSuffix = Mage::helper('core')->uniqHash();
            $submitButton = new Varien_Data_Form_Element_Submit(
                array(
                    'value' => $this->__('Click here if you are not redirected within 10 seconds...'),
                )
            );
            $id = "submit_to_tendopay_button_{$idSuffix}";
            $submitButton->setId($id);
            $form->addElement($submitButton);
            $html = '<html><body>';
            $html .= $this->__('You will be redirected to the Tendopay website in a few seconds.');
            $html .= $form->toHtml();
            $html .= '<script type="text/javascript">
document.getElementById("tendopay_standard_checkout").submit();</script>';
            $html .= '</body></html>';
            return $html;
        }
    }
}