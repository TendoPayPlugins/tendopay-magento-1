<?php

class TendoPay_TendopayPayment_Block_Payment_Info extends Mage_Payment_Block_Info
{
	protected function _prepareSpecificInformation($transport = null)
	{
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);
        $helper    = Mage::helper('tendopay');

        if (!$this->getIsSecureMode()) {
            /** @var Mage_Sales_Model_Order_Payment $info */
            $info  = $this->getInfo();
            $order = $info->getOrder();
            $txnId = $info->getLastTransId();
            if (!$txnId) { // if order doesn't have transaction (for instance: Pending Payment orders)
                $tendopay_order_id = $order->getData('tendopay_order_id');
                $tendopay_token = $order->getData('tendopay_token');
                $tendopay_disposition = $order->getData('tendopay_disposition');
                $tendopay_verification_token = $order->getData('tendopay_verification_token');
                $tendopay_fetched_at = $order->getData('tendopay_fetched_at');
                $transport->addData(array($helper->__('Tendopay Order ID') => $tendopay_order_id ? $tendopay_order_id : $helper->__('(none)')));
                $transport->addData(array($helper->__('Tendopay Order Token') => $tendopay_token ? $tendopay_token : $helper->__('(none)')));
                $transport->addData(array($helper->__('Tendopay Order Status') => $tendopay_disposition ? $tendopay_disposition : $helper->__('(none)')));
                $transport->addData(array($helper->__('Tendopay Verification Token') => $tendopay_verification_token ? $tendopay_verification_token : $helper->__('(none)')));
                $transport->addData(array($helper->__('Tendopay Token Fetched At') => $tendopay_fetched_at ? Mage::helper('core')->formatDate($tendopay_fetched_at, 'long', false) : $helper->__('(none)')));
            } else { // if order already has transaction
                $transport->addData(array($helper->__('Order ID') => $txnId));

                $lastTxn    = $info->getTransaction($txnId);
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