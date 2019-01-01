<?php

use GuzzleHttp\Client;
require_once(Mage::getModuleDir("tendo_vendor", "TendoPay_TendopayPayment") . DS . 'tendo_vendor/autoload.php');

class TendoPay_TendopayPayment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var string $bearer_token the bearer token requested in previous API calls. If it's null, it will be taken from
     * wordpress options. If it was null or expired in the options, it will be then requested from the API.
     */
    private static $bearer_token;
    /**
     * @var string
     */
    protected $logFileName = 'tendopay.log';

    /**
     * @var bool
     */
    protected $isDebugEnabled;

    /**
     * @var \GuzzleHttp\Client $client a http client to make the API calls
     */
    private $client;


    const PAYMANET_FAILED_QUERY_PARAM = 'tendopay_payment_failed';
    const METHOD_WPS = 'tendopay';
    const REDIRECT_URL_PATTERN = '^tendopay-result/?';
    const HASH_ALGORITHM = 'sha256';

    /**
     * Below constant names are used as live TP API
     */
    const BASE_API_URL = 'https://app.tendopay.ph/';
    const REDIRECT_URI = 'https://app.tendopay.ph/payments/authorise';
    const VIEW_URI_PATTERN = 'https://app.tendopay.ph/view/transaction/%s';
    const VERIFICATION_ENDPOINT_URI = 'payments/api/v1/verification';
    const AUTHORIZATION_ENDPOINT_URI = 'payments/api/v1/authTokenRequest';
    const DESCRIPTION_ENDPOINT_URI = 'payments/api/v1/paymentDescription';
    const BEARER_TOKEN_ENDPOINT_URI = 'oauth/token';

    /**
     * Below constant names are used as sandbox TP API
     */
    const SANDBOX_BASE_API_URL = 'https://sandbox.tendopay.ph/';
    const SANDBOX_REDIRECT_URI = 'https://sandbox.tendopay.ph/payments/authorise';
    const SANDBOX_VIEW_URI_PATTERN = 'https://sandbox.tendopay.ph/view/transaction/%s';
    const SANDBOX_VERIFICATION_ENDPOINT_URI = 'payments/api/v1/verification';
    const SANDBOX_AUTHORIZATION_ENDPOINT_URI = 'payments/api/v1/authTokenRequest';
    const SANDBOX_DESCRIPTION_ENDPOINT_URI = 'payments/api/v1/paymentDescription';
    const SANDBOX_BEARER_TOKEN_ENDPOINT_URI = 'oauth/token';

    const TENDOPAY_ICON = 'https://s3.ca-central-1.amazonaws.com/candydigital/images/tendopay/tp-icon-128x128.png';
    const TENDOPAY_FAQ = 'https://tendopay.ph/page-faq.html';

    /**
     * Below constant names are used as keys of data send to or received from TP API
     */
    const AMOUNT_PARAM = 'tendopay_amount';
    const AUTH_TOKEN_PARAM = 'tendopay_authorisation_token';
    const TENDOPAY_CUSTOMER_REFERENCE_1 = 'tendopay_customer_reference_1';
    const TENDOPAY_CUSTOMER_REFERENCE_2 = 'tendopay_customer_reference_2';
    const REDIRECT_URL_PARAM = 'tendopay_redirect_url';
    const VENDOR_ID_PARAM = 'tendopay_tendo_pay_vendor_id';
    const VENDOR_PARAM = 'tendopay_vendor';
    const HASH_PARAM = 'tendopay_hash';
    const DISPOSITION_PARAM = 'tendopay_disposition';
    const TRANSACTION_NO_PARAM = 'tendopay_transaction_number';
    const VERIFICATION_TOKEN_PARAM = 'tendopay_verification_token';
    const DESC_PARAM = 'tendopay_description';
    const STATUS_PARAM = 'tendopay_status';
    const USER_ID_PARAM = 'tendopay_user_id';

    /**
     * Below constants are the keys of description object that is being sent during request to Description Endpoint
     */
    const ITEMS_DESC_PROPNAME = 'items';
    const META_DESC_PROPNAME = 'meta';
    const ORDER_DESC_PROPNAME = 'order';

    /**
     * Below constants are the keys of description object's line items that are being sent during request to Description Endpoint
     */
    const TITLE_ITEM_PROPNAME = 'title';
    const DESC_ITEM_PROPNAME = 'description';
    const SKU_ITEM_PROPNAME = 'SKU';
    const PRICE_ITEM_PROPNAME = 'price';

    /**
     * Below constants are the keys of description object's meta info that is being sent during request to Description Endpoint
     */
    const CURRENCY_META_PROPNAME = 'currency';
    const THOUSAND_SEP_META_PROPNAME = 'thousand_separator';
    const DECIMAL_SEP_META_PROPNAME = 'decimal_separator';
    const VERSION_META_PROPNAME = 'version';

    /**
     * Below constants are the keys of description object's order details that are being sent during request to Description Endpoint
     */
    const ID_ORDER_PROPNAME = 'id';
    const SUBTOTAL_ORDER_PROPNAME = 'subtotal';
    const TOTAL_ORDER_PROPNAME = 'total';

    const TEMPLATE_OPTION_TITLE_CUSTOM = 'tendopay_front/payment/title.phtml';

    public static function getTendopayCheckoutTitle()
    {
        return self::TEMPLATE_OPTION_TITLE_CUSTOM;
    }

    public static function getTendopayMethodCode()
    {
        return self::METHOD_WPS;
    }

    /**
     * Gets the hash algorithm.
     *
     * @return string hash algorithm
     */
    public static function get_hash_algorithm()
    {
        return self::HASH_ALGORITHM;
    }

    /**
     * Gets the base api URL. It checks whether to use SANDBOX URL or Production URL.
     *
     * @return string the base api url
     */
    public static function get_base_api_url()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_BASE_API_URL : self::BASE_API_URL;
    }

    /**
     * Gets the redirect uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string redirect uri
     */
    public static function get_redirect_uri()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_REDIRECT_URI : self::REDIRECT_URI;
    }

    /**
     * Gets the view uri pattern. It checks whether to use SANDBOX pattern or Production pattern.
     *
     * @return string view uri pattern
     */
    public static function get_view_uri_pattern()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_VIEW_URI_PATTERN : self::VIEW_URI_PATTERN;
    }

    /**
     * Gets the verification endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string verification endpoint uri
     */
    public static function get_verification_endpoint_uri()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_VERIFICATION_ENDPOINT_URI : self::VERIFICATION_ENDPOINT_URI;
    }

    /**
     * Gets the authorization endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string authorization endpoint uri
     */
    public static function get_authorization_endpoint_uri()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_AUTHORIZATION_ENDPOINT_URI : self::AUTHORIZATION_ENDPOINT_URI;
    }

    /**
     * Gets the description endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string description endpoint uri
     */
    public static function get_description_endpoint_uri()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_DESCRIPTION_ENDPOINT_URI : self::DESCRIPTION_ENDPOINT_URI;
    }

    /**
     * Gets the bearer token endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string bearer token endpoint uri
     */
    public static function get_bearer_token_endpoint_uri()
    {
        return self::is_sandbox_enabled() ? self::SANDBOX_BEARER_TOKEN_ENDPOINT_URI : self::BEARER_TOKEN_ENDPOINT_URI;
    }

    /**
     *
     * @return bool true if sandbox is enabled
     */
    private static function is_sandbox_enabled()
    {
        return true;
    }

    /**
     * @return string
     */
    public static function getVendorIdParam()
    {
        return self::VENDOR_ID_PARAM;
    }

    /**
     * @return string
     */
    public static function getHashParam()
    {
        return self::HASH_PARAM;
    }

    /**
     * @return string
     */
    public static function getVendorParam()
    {
        return self::VENDOR_PARAM;
    }

    /**
     * @return string
     */
    public static function getRedirectUrlParam()
    {
        return self::REDIRECT_URL_PARAM;
    }

    /**
     * @return string
     */
    public static function getTendopayCustomerReferenceOne()
    {
        return self::TENDOPAY_CUSTOMER_REFERENCE_1;
    }

    /**
     * @return string
     */
    public static function getTendopayCustomerReferencetwo()
    {
        return self::TENDOPAY_CUSTOMER_REFERENCE_2;
    }

    /**
     * @return string
     */
    public static function getDispositionParam()
    {
        return self::DISPOSITION_PARAM;
    }

    /**
     * @return string
     */
    public static function getTransactionNoParam()
    {
        return self::TRANSACTION_NO_PARAM;
    }

    /**
     * @return string
     */
    public static function getVerificationTokenParam()
    {
        return self::VERIFICATION_TOKEN_PARAM;
    }

    /**
     * @return string
     */
    public static function getUserIDParam()
    {
        return self::USER_ID_PARAM;
    }

    /**
     * @return string
     */
    public static function getStatusIDParam()
    {
        return self::STATUS_PARAM;
    }

    /**
     * @return string
     */
    public static function getAuthTokenParam()
    {
        return self::AUTH_TOKEN_PARAM;
    }

    /**
     * @return string
     */
    public static function getAmountParam()
    {
        return self::AMOUNT_PARAM;
    }

    /**
     * @return string
     */
    public static function getDescParam()
    {
        return self::DESC_PARAM;
    }

    /**
     * @return string
     */
    public static function PaymentFailedQueryParam()
    {
        return self::PAYMANET_FAILED_QUERY_PARAM;
    }

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return Mage::getUrl('checkout/cart/index');
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return Mage::getUrl('tendopay/standard/success');
    }

    /**
     * @return string
     */
    public function getPaymentVerificationUrl()
    {
        return Mage::getUrl('tendopay/cart', array('action' => 'verifypayment'));
    }

    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    public function getTendopayStandardModel()
    {
        return Mage::getModel('tendopay/standard');
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @param $message
     * @throws Mage_Core_Exception
     */
    public function setException($message)
    {
        Mage::throwException($this->__($message));
    }

    public function throwException($message)
    {
        throw Mage::exception('TendoPay_TendopayPayment', $message);
    }

    /**
     * @param $message
     * @throws Mage_Core_Exception
     */
    public function addTendopayError($message)
    {
        Mage::getSingleton('core/session')->addError($message);
    }

    /**
     * Restore last active quote based on checkout session
     * @return bool True if quote restored successfully, false otherwise
     */
    public function restoreQuote()
    {
        $order = $this->getCheckoutSession()->getLastRealOrder();
        if ($order->getId()) {
            $quote = $this->_getQuote($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)
                    ->setReservedOrderId(null)
                    ->save();
                $this->getCheckoutSession()
                    ->replaceQuote($quote)
                    ->unsLastRealOrderId();
                return true;
            }
        }
        return false;
    }

    /**
     * @param $quoteId
     * @return Mage_Core_Model_Abstract
     */
    protected function _getQuote($quoteId)
    {
        return Mage::getModel('sales/quote')->load($quoteId);
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    public function getApiAdapter()
    {
        return Mage::getModel('tendopay/api_adapters_adapterv1');
    }

    /**
     * @param $field
     * @return mixed
     */
    public function getConfigValues($field)
    {
        $base = $this->getTendopayStandardModel();
        return $base->getConfigData($field);
    }

    public function getModuleVersion()
    {
        return (string)Mage::getConfig()->getModuleConfig('TendoPay_TendopayPayment')->version;
    }

    public function _cleanup_string($string)
    {
        $result = preg_replace("/[^a-zA-Z0-9]+/", "", $string);
        return $result;
    }


    /**
     * General logging method
     *
     * @param      $message
     * @param null $level
     */
    public function log($message, $level = null)
    {
        if ($this->isDebugMode() || $level != Zend_Log::DEBUG) {
            Mage::log($message, $level, $this->logFileName);
        }
    }

    /**
     * @return bool
     */
    public function isDebugMode()
    {
        if ($this->isDebugEnabled === null) {
            $this->isDebugEnabled = Mage::getStoreConfigFlag('payment/tendopay/debug');
        }

        return $this->isDebugEnabled;
    }

    public function calculate(array $data)
    {
        $base = $this->getTendopayStandardModel();
        $secret = $this->getConfigValues($base->getAPIMerchantSecretConfigField());

        $data = array_map(function ($value) {
            return trim($value);
        }, $data);

        $hash_keys_exclusion_list = [$this->getHashParam()];

        $exclusion_list = $hash_keys_exclusion_list;

        $data = array_filter($data, function ($value, $key) use ($exclusion_list) {
            return !in_array($key, $exclusion_list) && !empty($value);
        }, ARRAY_FILTER_USE_BOTH);

        ksort($data);
        $message = join("", $data);

        return hash_hmac($this->get_hash_algorithm(), $message, $secret, false);
    }

    /**
     * Performs the actual API call.
     *
     * @param string $url url of the endpoint
     * @param array $data data to be posted to the endpoint
     *
     * @return Response response from the API call
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doCall($url, array $data)
    {
        $base = $this->getTendopayStandardModel();
        $merchantId = $this->getConfigValues($base->getAPIMerchantIDConfigField());
        $data[$this->getVendorIdParam()] = $merchantId;
        $data[$this->getHashParam()] = $this->calculate($data);

        $headers = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_bearer_token(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Using' => 'TendoPay Magento1 Extension',
            ],
            'json' => $data
        ];


        $this->client = new Client([
            'base_uri' => $this->get_base_api_url()
        ]);
        $response = $this->client->request('POST', $url, $headers);

        return new TendoPay_TendopayPayment_Helper_Response( $response->getStatusCode(), $response->getBody());
    }

    private function get_bearer_token()
    {
        $base = $this->getTendopayStandardModel();
        if ( self::$bearer_token === null ) {
            $BearerTokenConfigField = $this->getConfigValues($base->getBearerTokenConfigField());
            self::$bearer_token = $BearerTokenConfigField;
        }

        $bearer_expiration_timestamp = - 1;
        if ( self::$bearer_token !== null && property_exists( self::$bearer_token, 'expiration_timestamp' ) ) {
            $bearer_expiration_timestamp = self::$bearer_token->expiration_timestamp;
        }

        $current_timestamp = Mage::getModel('core/date')->timestamp(time());

        if ( $bearer_expiration_timestamp <= $current_timestamp - 30 ) {
            $headers = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Using' => 'TendoPay Magento1 Extension'
                ],
                'json' => [
                    "grant_type" => "client_credentials",
                    "client_id" => $this->getConfigValues($base->getAPIClientIdConfigField()),
                    "client_secret" => $this->getConfigValues($base->getAPIClientSecretConfigField())
                ]
            ];

            $this->client = new Client([
                'base_uri' => $this->get_base_api_url()
            ]);
            $response = $this->client->request('POST', $this->get_bearer_token_endpoint_uri(), $headers);
            $response_body = (string)$response->getBody();
            $response_body = json_decode($response_body);



            self::$bearer_token = new \stdClass();
            self::$bearer_token->expiration_timestamp = $response_body->expires_in + $current_timestamp;
            self::$bearer_token->token = $response_body->access_token;



            Mage::getConfig()->saveConfig('payment/tendopay/bearer_token', serialize(self::$bearer_token), 'default', 0);
        }

        return self::$bearer_token->token;
    }
}
