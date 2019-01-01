<?php

class TendoPay_TendopayPayment_StandardController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get current Order model from session
     * @return Mage_Sales_Model_Order
     */
    public function getLastRealOrder()
    {
        $helper = $this->helper();
        $session = $helper->getCheckoutSession();
        $orderId = $session->getLastRealOrderId();
        $order = Mage::getModel('sales/order');
        if ($orderId) {
            $order->loadByIncrementId($orderId);
        }
        return $order;
    }

    /**
     * @return TendoPay_TendopayPayment_Helper_Data
     */
    public function helper()
    {
        return Mage::helper('tendopay');
    }

    public function tendopaySuccessAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPaypalStandardQuoteId(true));
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }

    public function setDescription($authorization_token, $order)
    {
        $helper = $this->helper();
        $order_details = $helper->getApiAdapter()->buildOrderTokenRequest($order);
        if (!is_array($order_details) && !is_object($order_details)) {
            $helper->setException('Order details parameter must be either ARRAY or OBJECT');
        }
        if (empty($order_details)) {
            // nothing to send to TP, exiting
            return;
        }

        $response = $helper->doCall($helper->get_description_endpoint_uri(), [
            $helper->getAuthTokenParam() => $authorization_token,
            $helper->getTendopayCustomerReferenceOne() => (string)$order->getIncrementId(),
            $helper->getTendopayCustomerReferencetwo() => "magento1_order_" . $order->getIncrementId(),
            $helper->getDescParam() => json_encode($order_details),
        ]);

        if ($response->get_code() !== 204) {
            $helper->setException('Got response code != 204 while sending products description');
        }
    }

    public function requestToken($order)
    {
        $helper = $this->helper();
        $data = [
            $helper->getAmountParam() => (int)($order->getGrandTotal()),
            $helper->getTendopayCustomerReferenceOne() => (string)$order->getIncrementId(),
            $helper->getTendopayCustomerReferencetwo() => "magento1_order_" . $order->getIncrementId(),
        ];
        $response = $helper->doCall($helper->get_authorization_endpoint_uri(), $data);
        $is_valid_response = $response->get_code() === 200 && !empty($response->get_body());

        if (!$is_valid_response) {
            $helper->setException('Got return code != 200 or empty body while requesting authorization token from TP');
        }
        return trim((string)$response->get_body(), "\"");
    }

    /**
     * When a customer chooses Tendopay on Checkout/Payment page
     */
    public function redirectAction()
    {
        $order = $this->getLastRealOrder();
        $helper = $this->helper();
        try {
            if (!$order->getIncrementId()) {
                $helper->log('Payment redirect request: Cannot get order from session, redirecting customer to shopping cart', Zend_Log::WARN);
                $this->_redirect('checkout/cart');
                return;
            }

            $helper->log('Payment redirect request for order ' . $order->getIncrementId(), Zend_Log::INFO);
            // render block with redirecting JavaScript code
            $helper->log('Redirecting customer to TendoPay website... order=' . $order->getIncrementId(), Zend_Log::INFO);

            try {
                $auth_token = $this->requestToken($order);
                $this->setDescription($auth_token, $order);
            } catch (\Exception $exception) {
                error_log($exception);
                $helper->addTendopayError("Could not communicate with TendoPay.");
                $helper->addTendopayError($exception);
            }
            $session = $helper->getCheckoutSession();
            $session->setTendopayStandardQuoteId($session->getQuoteId());
            $this->getResponse()->setBody($this->getLayout()->createBlock('tendopay/standard_redirect')->setData('auth_token', $auth_token)->toHtml());
            $session->unsQuoteId();
            $session->unsRedirectUrl();

        } catch (Mage_Core_Exception $e) {
            // log error and notify customer about incident
            $helper->log('Exception on processing payment redirect request: ' . $e->getMessage(), Zend_Log::ERR);
            Mage::logException($e);
            $helper->addTendopayError("TendoPay: Error processing payment request.");
            $this->cancelAction();
        }
    }

    /**
     * When a customer cancel payment from TendoPay.
     */
    public function cancelAction()
    {
        $helper = $this->helper();
        $session = $helper->getCheckoutSession();

        $session->setQuoteId($session->getTendopayStandardQuoteId(true));
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            $helper->restoreQuote();
        }
        $this->_redirect('checkout/cart');
    }

    public function successAction()
    {
        $helper = $this->helper();
        $base = $helper->getTendopayStandardModel();

        $posted_data = $this->getRequest()->getParams();
        if (isset($posted_data['action'])) {
            unset($posted_data['action']);
        }

        $getTendopayCustomerReferenceOne = $helper->getTendopayCustomerReferenceOne();

        $order = Mage::getModel('sales/order')->loadByIncrementId($posted_data[$getTendopayCustomerReferenceOne]);
        if ($order->getIncrementId()) {
            $order_key = $posted_data[$getTendopayCustomerReferenceOne];
            if ($order->getIncrementId() !== $order_key) {
                $helper->addTendopayError("Wrong order key provided");
            }
            else{

                switch ($order->getStatus()) {
                    case TendoPay_TendopayPayment_Model_Standard::RESPONSE_STATUS_APPROVED:
                        $this->preVerifyPaymentAction($order, $posted_data);
                        break;
                    case TendoPay_TendopayPayment_Model_Standard::RESPONSE_STATUS_PROCESSING:
                        $this->preVerifyPaymentAction($order, $posted_data);
                        break;
                    case TendoPay_TendopayPayment_Model_Standard::RESPONSE_STATUS_DECLINED:
                        $this->cancelAction();
                        $base->resetTransactionToken();
                        $helper->setException('TendoPay payment has been declined. Please use other payment method.');
                        break;
                    default:
                        $this->cancelAction();
                        $base->resetTransactionToken();
                        $helper->setException('Cannot find TendoPay payment. Please contact administrator.');
                        break;
                }
                $this->tendopaySuccessAction();
            }
        }
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }

    /**
     * when TendoPay returns The order information at this point is in POST variables.  However, you don't want to "process" the order until you get validation from the IPN.
     */
    public function preVerifyPaymentAction($order, $posted_data)
    {
        $this->perform_verification($order, $posted_data);
    }

    private function perform_verification($order, $posted_data)
    {
        $helper = $this->helper();
        $base = $helper->getTendopayStandardModel();
        $tendo_pay_merchant_id = $posted_data[$helper->getVendorIdParam()];
        $local_tendo_pay_merchant_id = $helper->getConfigValues($base->getAPIMerchantIDConfigField());



        if ($tendo_pay_merchant_id !== $local_tendo_pay_merchant_id) {
            $helper->addTendopayError("Malformed payload");
        }

        try {
            $transaction_verified = $this->verify_payment($order, $posted_data);
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
            error_log($exception->getTraceAsString());
            $helper->addTendopayError("Could not communicate with TendoPay properly");
        }



        if ($transaction_verified) {
            return true;
        } else {
            $comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Could not get with TendoPay transaction verification properly')
                ->setIsCustomerNotified(false)
                ->save();
            $this->_forward('error');
            $this->_redirect('checkout/cart');
        }
    }

    public function verify_payment($order, array $data)
    {
        ksort($data);
        $helper = $this->helper();
        $base = $helper->getTendopayStandardModel();
        $hash = $data[$helper->getHashParam()];
        if ($hash !== $helper->calculate($data)) {
            throw new InvalidArgumentException(__("Hash doesn't match", "tendopay"));
        }

        $disposition = $data[$helper->getDispositionParam()];
        $tendo_pay_transaction_number = $data[$helper->getTransactionNoParam()];
        $verification_token = $data[$helper->getVerificationTokenParam()];
        $tendo_pay_user_id = $data[$helper->getUserIDParam()];

        $verification_data = [
            $helper->getTendopayCustomerReferenceOne() => (string)$order->getIncrementId(),
            $helper->getTendopayCustomerReferencetwo() => "magento1_order_" . $order->getIncrementId(),
            $helper->getDispositionParam() => $disposition,
            $helper->getVendorIdParam() => (string)$helper->getConfigValues($base->getAPIMerchantIDConfigField()),
            $helper->getTransactionNoParam() => (string)$tendo_pay_transaction_number,
            $helper->getVerificationTokenParam() => $verification_token,
            $helper->getUserIDParam() => $tendo_pay_user_id,
        ];

        $response = $helper->doCall($helper->get_verification_endpoint_uri(), $verification_data);
        if ($response->get_code() !== 200) {
            Mage::throwException(
                $helper->__('Received error: [%s] while trying to verify the transaction', $response->get_code())
            );
        }
        $json = json_decode($response->get_body());

        $session = $helper->getCheckoutSession();
        $quote = $session->getQuote();
        $quote->setData('tendopay_order_id', $order->getTendopayOrderId())->save();
        $quote->setData('tendopay_token', $order->getTendopayToken())->save();

        $order->setData('tendopay_token', $json->tendopay_hash);
        $order->setData('tendopay_order_id', $json->tendopay_transaction_number);
        $order->setData('tendopay_disposition', $this->getRequest()->getParam('tendopay_disposition'));
        $order->setData('tendopay_verification_token', $this->getRequest()->getParam('tendopay_verification_token'));
        $order->setData('tendopay_fetched_at', $current_timestamp = Mage::getModel('core/date')->timestamp(time()));
        $order->save();

        return $json->{$helper->getStatusIDParam()} === 'success';
    }
}