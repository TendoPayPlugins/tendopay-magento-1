<?php

class TendoPay_TendopayPayment_Model_Observer
{
    const TENDOPAY_PAYMENT_INITIATED_KEY = '_tendopay_payment_initiated';

    public function lookforApiErrors($observer)
    {
        if (Mage::app()->getRequest()->getRouteName() == 'checkout' && Mage::app()->getRequest()->getControllerName() == 'cart' && Mage::app()->getRequest()->getActionName() == 'index') {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);

            $this->maybe_add_payment_initiated_notice();
            $this->maybe_add_payment_failed_notice();
            $this->maybe_add_outstanding_balance_notice($order);
        }
        return;
    }

    private function maybe_add_payment_initiated_notice()
    {
        $tendopay_helper_data = $this->helper();
        $payment_initiated = false;
        if ($payment_initiated) {
            $payment_initiated_notice = "<strong>Warning!</strong><br><br>You've already initiated payment attempt with TendoPay once. If you continue you may end up finalizing two separate payments for single order.<br><br>Are you sure you want to continue?";
            $tendopay_helper_data->addTendopayError($payment_initiated_notice);
        }
    }

    public function maybe_add_payment_failed_notice()
    {
        $tendopay_helper_data = $this->helper();
        $payment_failed = Mage::app()->getRequest()->getParam($tendopay_helper_data->PaymentFailedQueryParam());
        if ($payment_failed) {
            $payment_failed_notice = 'The payment attempt with TendoPay has failed. Please try again or choose other payment method.';
            $tendopay_helper_data->addTendopayError($payment_failed_notice);
        }
    }

    /**
     * @return Mage_Core_Helper_Abstract
     */
    public function helper()
    {
        return Mage::helper('tendopay');
    }

    public function maybe_add_outstanding_balance_notice($order)
    {
        $tendopay_helper_data = $this->helper();
        $witherror = Mage::app()->getRequest()->getParam('witherror');
        if ($witherror) {
            $errors = explode(':', $witherror);
            $errors = is_array($errors) ? array_map('htmlspecialchars', $errors) : [];
            $error = isset($errors[0]) ? $errors[0] : '';
            $extra = isset($errors[1]) ? $errors[1] : '';

            if ($order->getId()) {
                $order->cancel()->save();
                $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
                if ($quote->getId()) {
                    $quote->setIsActive(1)->setReservedOrderId(NULL)->save();
                    $tendopay_helper_data->getCheckoutSession()->replaceQuote($quote);
                }
            }

            switch ($error) {
                case 'outstanding_balance':
                    $notice = "Your account has an outstanding balance, please repay your payment so you make an additional purchase.";
                    $tendopay_helper_data->addTendopayError($notice);
                    break;
                case 'minimum_purchase':
                case 'maximum_purchase':
                    $notice = __($extra);
                    $tendopay_helper_data->addTendopayError($notice);
                    Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
                    Mage::app()->getResponse()->sendResponse();
                    exit;
            }
        }
    }

    /**
     * Set the tenbdopay order token as part of the order body response
     *
     * @param $observer
     * @return $this
     */
    public function addTokenToOrderResponse($observer)
    {
        die(__METHOD__);
        $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        if ($order instanceof Mage_Sales_Model_Order) {
            $payment = $order->getPayment();
            var_dump($payment->getMethodInstance());
            if ($payment instanceof Mage_Payment_Model_Info && $payment->getMethodInstance() instanceof TendoPay_TendopayPayment_Model_Method_Base) {
                $response = Mage::app()->getResponse();
                $helper = Mage::helper('core');
                $responseBody = $helper->jsonDecode($response->getBody());

                $tenbdopayToken = $payment->getData('tenbdopay_token');

                $responseBody['tenbdopayToken'] = $tenbdopayToken;
                $response->setBody($helper->jsonEncode($responseBody));

                $this->helper()->log('Setting tenbdopay token to order (' . $order->getIncrementId() . ') response : ' . $tenbdopayToken, Zend_Log::DEBUG);
            }
        }
        return $this;
    }
}