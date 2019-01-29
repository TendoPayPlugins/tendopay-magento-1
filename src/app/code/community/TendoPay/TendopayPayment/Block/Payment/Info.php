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
 * Class TendoPay_TendopayPayment_Block_Payment_Info
 */
class TendoPay_TendopayPayment_Block_Payment_Info extends Mage_Payment_Block_Info
{
    /**
     * @param null $transport
     * @return null
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);
        $helper = Mage::helper('tendopay');

        if (!$this->getIsSecureMode()) {
            /** @var Mage_Sales_Model_Order_Payment $info */
            $info = $this->getInfo();
            $order = $info->getOrder();
            $txnId = $info->getLastTransId();
            if (!$txnId) { // if order doesn't have transaction (for instance: Pending Payment orders)
                $tendopayOrderId = $order->getData('tendopay_order_id');
                $tendopayToken = $order->getData('tendopay_token');
                $tendopayDisposition = $order->getData('tendopay_disposition');
                $tendopayVerificationToken = $order->getData('tendopay_verification_token');
                $tendopayFetchedAt = $order->getData('tendopay_fetched_at');
                $transport->addData(
                    array($helper->__('Tendopay Order ID') =>
                        $tendopayOrderId ? $tendopayOrderId : $helper->__('(none)'))
                );
                $transport->addData(
                    array($helper->__('Tendopay Order Token') =>
                        $tendopayToken ? $tendopayToken : $helper->__('(none)'))
                );
                $transport->addData(
                    array($helper->__('Tendopay Order Status') =>
                        $tendopayDisposition ? $tendopayDisposition : $helper->__('(none)'))
                );
                $transport->addData(
                    array($helper->__('Tendopay Verification Token') =>
                        $tendopayVerificationToken ? $tendopayVerificationToken : $helper->__('(none)'))
                );
                $transport->addData(
                    array($helper->__('Tendopay Token Fetched At') =>
                        $tendopayFetchedAt ? Mage::helper('core')->formatDate($tendopayFetchedAt, 'long', false) :
                            $helper->__('(none)'))
                );
            } else { // if order already has transaction
                $transport->addData(array($helper->__('Order ID') => $txnId));

                $lastTxn = $info->getTransaction($txnId);
                $rawDetails = $lastTxn instanceof Mage_Sales_Model_Order_Payment_Transaction ?
                    $lastTxn->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS) : false;

                if (is_array($rawDetails)) {
                    if (isset($rawDetails['paymentType'])) {
                        $transport->addData(array($helper->__('Payment Type') => $rawDetails['paymentType']));
                    }

                    if (isset($rawDetails['status'])) {
                        $transport->addData(array($helper->__('Payment Status') => $rawDetails['status']));
                    }

                    if (isset($rawDetails['consumerName'])) {
                        $transport->addData(array($helper->__('Consumer Name') => $rawDetails['consumerName']));
                    }

                    if (isset($rawDetails['consumerEmail'])) {
                        $transport->addData(array($helper->__('Consumer Email') => $rawDetails['consumerEmail']));
                    }

                    if (isset($rawDetails['consumerTelephone'])) {
                        $transport->addData(array($helper->__('Consumer Tel.') => $rawDetails['consumerTelephone']));
                    }
                }
            }
        }

        return $transport;
    }
}