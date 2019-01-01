<?php

class TendoPay_TendopayPayment_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = TendoPay_TendopayPayment_Helper_Data::METHOD_WPS;
    protected $_formBlockType = 'tendopay/standard_form';
    protected $_infoBlockType = 'tendopay/payment_info';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;

    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;

    /* Configuration fields */
    const API_ENABLED_FIELD = 'active';
    const API_MODE_CONFIG_FIELD = 'api_mode';
    const API_MIN_ORDER_TOTAL_FIELD = 'min_order_total';
    const API_MAX_ORDER_TOTAL_FIELD = 'max_order_total';
    const API_URL_CONFIG_PATH_PATTERN = 'tendopay/api/{prefix}_api_url';
    const WEB_URL_CONFIG_PATH_PATTERN = 'tendopay/api/{prefix}_web_url';
    const API_MERCHANT_ID_CONFIG_FIELD = 'api_merchant_id';
    const API_BEARER_TOKEN_FIELD = 'bearer_token';
    const API_MERCHANT_SECRET_CONFIG_FIELD = 'api_merchant_secret';
    const API_CLIENT_ID_CONFIG_FIELD = 'api_client_id';
    const API_CLIENT_SECRET_CONFIG_FIELD = 'api_client_secret';

    /* Order payment statuses */
    const RESPONSE_STATUS_APPROVED = 'APPROVED';
    const RESPONSE_STATUS_PENDING = 'PENDING';
    const RESPONSE_STATUS_FAILED = 'FAILED';
    const RESPONSE_STATUS_DECLINED = 'DECLINED';
    const RESPONSE_STATUS_PROCESSING = 'processing';
    const TRUNCATE_SKU_LENGTH = 128;

    /**
     * Get checkout session namespace
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * @return Mage_Core_Helper_Abstract
     */
    protected function helper()
    {
        return Mage::helper('tendopay');
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    public function getApiAdapter()
    {
        return Mage::getModel('tendopay/api_adapters_adapterv1');
    }

    /**
     * @return string
     */
    public static function getAPIEnabledField()
    {
        return self::API_ENABLED_FIELD;
    }

    /**
     * @return string
     */
    public static function getMinOrderTotalField()
    {
        return self::API_MIN_ORDER_TOTAL_FIELD;
    }

    /**
     * @return string
     */
    public static function getMaxOrderTotalField()
    {
        return self::API_MAX_ORDER_TOTAL_FIELD;
    }

    /**
     * @return string
     */
    public static function getAPIModeConfigField()
    {
        return self::API_MODE_CONFIG_FIELD;
    }

    /**
     * @return string
     */
    public static function getAPIMerchantIDConfigField()
    {
        return self::API_MERCHANT_ID_CONFIG_FIELD;
    }

    /**
     * @return string
     */
    public static function getBearerTokenConfigField()
    {
        return self::API_BEARER_TOKEN_FIELD;
    }

    /**
     * @return string
     */
    public static function getAPIMerchantSecretConfigField()
    {
        return self::API_MERCHANT_SECRET_CONFIG_FIELD;
    }

    /**
     * @return string
     */
    public static function getAPIClientIdConfigField()
    {
        return self::API_CLIENT_ID_CONFIG_FIELD;
    }

    /**
     * @return string
     */
    public static function getAPIClientSecretConfigField()
    {
        return self::API_CLIENT_SECRET_CONFIG_FIELD;
    }

    public function getStandardCheckoutFormFields($auth_token)
    {
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        //$quoteId = $order['quote_id'];

        $tendopay_helper_data = $this->helper();
        $merchantId = $tendopay_helper_data->getConfigValues($this->getAPIMerchantIDConfigField());
        $store = Mage::app()->getStore();

        $data = [
            $tendopay_helper_data->getAmountParam() => (int)$order->getGrandTotal(),
            $tendopay_helper_data->getAuthTokenParam() => $auth_token,
            $tendopay_helper_data->getTendopayCustomerReferenceOne() => (string)$orderIncrementId,
            $tendopay_helper_data->getTendopayCustomerReferencetwo() => "magento1_order_" . $orderIncrementId,
            $tendopay_helper_data->getRedirectUrlParam() => $tendopay_helper_data->getRedirectUrl(),
            $tendopay_helper_data->getVendorIdParam() => $merchantId,
            $tendopay_helper_data->getVendorParam() => $store->getName(),
            //"er" => $tendopay_helper_data->getCheckoutUrl()
        ];
        $redirect_args_hash = $tendopay_helper_data->calculate($data);
        $data[$tendopay_helper_data->getHashParam()] = $redirect_args_hash;
        $data["er"] = $tendopay_helper_data->getCheckoutUrl();
        return $data;
    }

    /**
     * Get current Order model from session
     *
     * @return Mage_Sales_Model_Order
     */
    protected function getLastRealOrder()
    {
        $tendopay_helper_data = $this->helper();
        $session = $tendopay_helper_data->getCheckoutSession();
        $orderId = $session->getLastRealOrderId();
        $order = Mage::getModel('sales/order');
        if ($orderId) {
            $order->loadByIncrementId($orderId);
        }
        return $order;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('tendopay/standard/redirect', array('_secure' => true));
    }

    public function process($data)
    {
        if ($data['cancel'] == 1) {
            $order = $this->getLastRealOrder();
            $order->getPayment()
                ->setTransactionId(null)
                ->setParentTransactionId(time())
                ->void();
            $message = 'Unable to process Payment';
            $order->registerCancellation($message)->save();
        }
    }

    /**
     * Resetting the token the session
     * @return bool
     */
    public function resetTransactionToken() {
        $tendopay_helper_data = $this->helper();
        Mage::getSingleton("checkout/session")->getQuote()->getPayment()->setData('tendopay_token', NULL)->save();
        if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
            $tendopay_helper_data->storeCreditSessionUnset();
            $tendopay_helper_data->giftCardsSessionUnset();
        }
        return true;
    }
}