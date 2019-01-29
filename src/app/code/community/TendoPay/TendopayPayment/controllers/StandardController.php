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
 * Class TendoPay_TendopayPayment_StandardController
 */
class TendoPay_TendopayPayment_StandardController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get current Order model from session
     * @return mixed
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
     * @return mixed
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
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }

    /**
     * @param $authorizationToken
     * @param $order
     */
    public function setDescription($authorizationToken, $order)
    {
        $helper = $this->helper();
        $orderDetails = $helper->getApiAdapter()->buildOrderTokenRequest($order);
        if (!is_array($orderDetails) && !is_object($orderDetails)) {
            $helper->setException('Order details parameter must be either ARRAY or OBJECT');
        }

        if (empty($orderDetails)) {
            return;
        }

        $response = $helper->doCall(
            $helper->get_description_endpoint_uri(), array(
                $helper->getAuthTokenParam() => $authorizationToken,
                $helper->getTendopayCustomerReferenceOne() => (string)$order->getIncrementId(),
                $helper->getTendopayCustomerReferencetwo() => "magento1_order_" . $order->getIncrementId(),
                $helper->getDescParam() => json_encode($orderDetails),
            )
        );

        if ($response->get_code() !== 204) {
            $helper->setException('Got response code != 204 while sending products description');
        }
    }

    /**
     * @param $order
     * @return string
     */
    public function requestToken($order)
    {
        $helper = $this->helper();
        $data = array(
            $helper->getAmountParam() => (int)($order->getGrandTotal()),
            $helper->getTendopayCustomerReferenceOne() => (string)$order->getIncrementId(),
            $helper->getTendopayCustomerReferencetwo() => "magento1_order_" . $order->getIncrementId(),
        );
        $response = $helper->doCall($helper->get_authorization_endpoint_uri(), $data);
        $isValidResponse = $response->get_code() === 200 && !empty($response->get_body());

        if (!$isValidResponse) {
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
                $helper->log(
                    'Payment redirect request: Cannot get order from session, redirecting customer to shopping cart',
                    Zend_Log::WARN
                );
                $this->_redirect('checkout/cart');
                return;
            }

            $helper->log('Payment redirect request for order ' . $order->getIncrementId(), Zend_Log::INFO);
            $helper->log(
                'Redirecting customer to TendoPay website... order=' . $order->getIncrementId(),
                Zend_Log::INFO
            );
            try {
                $authToken = $this->requestToken($order);
                $this->setDescription($authToken, $order);
            } catch (\Exception $exception) {
                $helper->addTendopayError("Could not communicate with TendoPay.");
                $helper->addTendopayError($exception);
            }

            $session = $helper->getCheckoutSession();
            $session->setTendopayStandardQuoteId($session->getQuoteId());
            $this->getResponse()->setBody(
                $this->getLayout()->createBlock('tendopay/standard_redirect')->setData(
                    'auth_token', $authToken
                )->toHtml()
            );
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

        $postedData = $this->getRequest()->getParams();
        if (isset($postedData['action'])) {
            unset($postedData['action']);
        }

        $getTendopayCustomerReferenceOne = $helper->getTendopayCustomerReferenceOne();
        $order = Mage::getModel('sales/order')->loadByIncrementId($postedData[$getTendopayCustomerReferenceOne]);
        if ($order->getIncrementId()) {
            $orderKey = $postedData[$getTendopayCustomerReferenceOne];
            if ($order->getIncrementId() !== $orderKey) {
                $helper->addTendopayError("Wrong order key provided");
            } else {
                switch ($order->getStatus()) {
                    case TendoPay_TendopayPayment_Model_Standard::RESPONSE_STATUS_APPROVED:
                        $this->preVerifyPaymentAction($order, $postedData);
                        break;
                    case TendoPay_TendopayPayment_Model_Standard::RESPONSE_STATUS_PROCESSING:
                        $this->preVerifyPaymentAction($order, $postedData);
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
     * When TendoPay returns The order information at this point is in POST variables.
     * However, you don't want to "process" the order until you get validation from the IPN.
     *
     * @param $order
     * @param $postedData
     */
    public function preVerifyPaymentAction($order, $postedData)
    {
        $this->perform_verification($order, $postedData);
    }

    /**
     * @param $order
     * @param $postedData
     * @return bool
     */
    public function perform_verification($order, $postedData)
    {
        $helper = $this->helper();
        $base = $helper->getTendopayStandardModel();
        $tendoPayMerchantId = $postedData[$helper->getVendorIdParam()];
        $localTendoPayMerchantId = $helper->getConfigValues($base->getAPIMerchantIDConfigField());

        if ($tendoPayMerchantId !== $localTendoPayMerchantId) {
            $helper->addTendopayError("Malformed payload");
        }

        try {
            $transactionVerified = $this->verify_payment($order, $postedData);
        } catch (\Exception $exception) {
            $helper->addTendopayError("Could not communicate with TendoPay properly");
        }

        if ($transactionVerified) {
            return true;
        } else {
            $order->sendNewOrderEmail()->addStatusHistoryComment(
                'Could not get with TendoPay transaction verification properly'
            )
                ->setIsCustomerNotified(false)
                ->save();
            $this->_forward('error');
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * @param $order
     * @param array $data
     * @return bool
     */
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
        $tendoPayTransactionNumber = $data[$helper->getTransactionNoParam()];
        $verificationToken = $data[$helper->getVerificationTokenParam()];
        $tendoPayUserId = $data[$helper->getUserIDParam()];

        $verificationData = array(
            $helper->getTendopayCustomerReferenceOne() => (string)$order->getIncrementId(),
            $helper->getTendopayCustomerReferencetwo() => "magento1_order_" . $order->getIncrementId(),
            $helper->getDispositionParam() => $disposition,
            $helper->getVendorIdParam() => (string)$helper->getConfigValues($base->getAPIMerchantIDConfigField()),
            $helper->getTransactionNoParam() => (string)$tendoPayTransactionNumber,
            $helper->getVerificationTokenParam() => $verificationToken,
            $helper->getUserIDParam() => $tendoPayUserId,
        );

        $response = $helper->doCall($helper->get_verification_endpoint_uri(), $verificationData);
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
        $order->setData('tendopay_fetched_at', Mage::getSingleton('core/date')->gmtDate());
        $order->save();

        return $json->{$helper->getStatusIDParam()} === 'success';
    }
}