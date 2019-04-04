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
 * Class TendoPay_TendopayPayment_Model_Observer
 */
class TendoPay_TendopayPayment_Model_Observer
{
    public function lookforApiErrors()
    {
        if (Mage::app()->getRequest()->getRouteName() == 'checkout' &&
            Mage::app()->getRequest()->getControllerName() == 'cart' &&
            Mage::app()->getRequest()->getActionName() == 'index') {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
            $this->maybeAddPaymentFailedNotice($order);
            $this->maybeAddOutstandingBalanceNotice($order);
        }

        return;
    }

    /**
     * @param $order
     */
    public function maybeAddPaymentFailedNotice($order)
    {
        $tendopayHelperData = $this->helper();
        $paymentFailed = Mage::app()->getRequest()->getParam($tendopayHelperData->PaymentFailedQueryParam());
        if ($paymentFailed) {
            $paymentFailedNotice = 'The payment attempt with TendoPay has failed.
             Please try again or choose other payment method.';
            $tendopayHelperData->addTendopayError($paymentFailedNotice);
            if ($order->getId()) {
                $order->cancel()->save();
                $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
                if ($quote->getId()) {
                    $quote->setIsActive(1)->setReservedOrderId(NULL)->save();
                    $tendopayHelperData->getCheckoutSession()->replaceQuote($quote);
                }
            }
        }
    }

    /**
     * @return mixed
     */
    public function helper()
    {
        return Mage::helper('tendopay');
    }

    /**
     * @param $order
     */
    public function maybeAddOutstandingBalanceNotice($order)
    {
        $tendopayHelperData = $this->helper();
        $witherror = Mage::app()->getRequest()->getParam('witherror');
        if ($witherror) {
            $errors = explode(':', $witherror);
            $errors = is_array($errors) ? array_map('htmlspecialchars', $errors) : array();
            $error = isset($errors[0]) ? $errors[0] : '';
            $extra = isset($errors[1]) ? $errors[1] : '';

            if ($order->getId()) {
                $order->cancel()->save();
                $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
                if ($quote->getId()) {
                    $quote->setIsActive(1)->setReservedOrderId(NULL)->save();
                    $tendopayHelperData->getCheckoutSession()->replaceQuote($quote);
                }
            }

            switch ($error) {
                case 'outstanding_balance':
                    $notice = "Your account has an outstanding balance, 
                    please repay your payment so you make an additional purchase.";
                    $tendopayHelperData->addTendopayError($notice);
                    break;
                case 'minimum_purchase':
                case 'maximum_purchase':
                    $notice = __($extra);
                    $tendopayHelperData->addTendopayError($notice);
            }
        }
    }
}