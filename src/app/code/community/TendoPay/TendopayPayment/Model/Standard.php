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
 * Class TendoPay_TendopayPayment_Model_Standard
 */
class TendoPay_TendopayPayment_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @var string
     */
    protected $_code = TendoPay_TendopayPayment_Helper_Data::METHOD_WPS;

    /**
     * @var string
     */
    protected $_formBlockType = 'tendopay/standard_form';

    /**
     * @var string
     */
    protected $_infoBlockType = 'tendopay/payment_info';

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * @var bool
     */
    protected $_canUseForMultishipping = false;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = false;

    /**
     * @var bool
     */
    protected $_canCapturePartial = false;

    /**
     * @var bool
     */
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
    const PAYMENT_GATEWAY_TITLE = 'title';
    const API_MERCHANT_SECRET_CONFIG_FIELD = 'api_merchant_secret';
    const API_CLIENT_ID_CONFIG_FIELD = 'api_client_id';
    const API_CLIENT_SECRET_CONFIG_FIELD = 'api_client_secret';
    const OPTION_TENDOPAY_EXAMPLE_INSTALLMENTS_ENABLE = 'tendo_example_installments_enabled';

    /* Order payment statuses */
    const RESPONSE_STATUS_APPROVED = 'APPROVED';
    const RESPONSE_STATUS_PENDING = 'PENDING';
    const RESPONSE_STATUS_FAILED = 'FAILED';
    const RESPONSE_STATUS_DECLINED = 'DECLINED';
    const RESPONSE_STATUS_PROCESSING = 'processing';
    const TRUNCATE_SKU_LENGTH = 128;

    /**
     * @return mixed
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return mixed
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * @return mixed
     */
    protected function helper()
    {
        return Mage::helper('tendopay');
    }

    /**
     * @return mixed
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
    public static function getPaymentGatewayTitle()
    {
        return self::PAYMENT_GATEWAY_TITLE;
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

    /**
     * @return string
     */
    public static function getTendoExampleInstallmentsEnabled()
    {
        return self::OPTION_TENDOPAY_EXAMPLE_INSTALLMENTS_ENABLE;
    }

    /**
     * @param $authToken
     * @return array
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getStandardCheckoutFormFields($authToken)
    {
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        $tendopayHelperData = $this->helper();
        $merchantId = $tendopayHelperData->getConfigValues($this->getAPIMerchantIDConfigField());
        $store = Mage::app()->getStore();

        $data = array(
            $tendopayHelperData->getAmountParam() => (int)$order->getGrandTotal(),
            $tendopayHelperData->getAuthTokenParam() => $authToken,
            $tendopayHelperData->getTendopayCustomerReferenceOne() => (string)$orderIncrementId,
            $tendopayHelperData->getTendopayCustomerReferencetwo() => "magento1_order_" . $orderIncrementId,
            $tendopayHelperData->getRedirectUrlParam() => $tendopayHelperData->getRedirectUrl(),
            $tendopayHelperData->getVendorIdParam() => $merchantId,
            $tendopayHelperData->getVendorParam() => $store->getName()
        );
        $redirectArgsHash = $tendopayHelperData->calculate($data);
        $data[$tendopayHelperData->getHashParam()] = $redirectArgsHash;
        $data["er"] = $tendopayHelperData->getCheckoutUrl();
        return $data;
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    protected function getLastRealOrder()
    {
        $tendopayHelperData = $this->helper();
        $session = $tendopayHelperData->getCheckoutSession();
        $orderId = $session->getLastRealOrderId();
        $order = Mage::getModel('sales/order');
        if ($orderId) {
            $order->loadByIncrementId($orderId);
        }

        return $order;
    }

    /**
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('tendopay/standard/redirect', array('_secure' => true));
    }

    /**
     * @param $data
     */
    public function process($data)
    {
        if ($data['cancel'] == 1) {
            $order = $this->getLastRealOrder();
            $order->getPayment()
                ->setTransactionId(null)
                ->setParentTransactionId(Mage::getSingleton('core/date')->gmtDate())
                ->void();
            $message = 'Unable to process Payment';
            $order->registerCancellation($message)->save();
        }
    }

    /**
     * @return bool
     */
    public function resetTransactionToken()
    {
        $tendopayHelperData = $this->helper();
        Mage::getSingleton("checkout/session")->getQuote()->getPayment()->setData('tendopay_token', NULL)->save();
        if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
            $tendopayHelperData->storeCreditSessionUnset();
            $tendopayHelperData->giftCardsSessionUnset();
        }

        return true;
    }
}