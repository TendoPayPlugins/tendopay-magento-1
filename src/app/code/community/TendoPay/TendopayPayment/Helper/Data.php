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

require_once(Mage::getBaseDir('lib') . DS . 'TendoPay' . DS . 'vendor' . DS . 'autoload.php');

/**
 * Class TendoPay_TendopayPayment_Helper_Data
 */
class TendoPay_TendopayPayment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var string $bearerToken the bearer token requested in previous API calls. If it's null, it will be taken from
     * wordpress options. If it was null or expired in the options, it will be then requested from the API.
     */
    private static $_bearerToken;

    /**
     * @var string
     */
    protected $_logFileName = 'tendopay.log';

    /**
     * @var
     */
    protected $_isDebugEnabled;

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
     * Below constants are the keys of description object's line items that
     * are being sent during request to Description Endpoint
     */
    const TITLE_ITEM_PROPNAME = 'title';
    const DESC_ITEM_PROPNAME = 'description';
    const SKU_ITEM_PROPNAME = 'SKU';
    const PRICE_ITEM_PROPNAME = 'price';

    /**
     * Below constants are the keys of description object's meta info that
     * is being sent during request to Description Endpoint
     */
    const CURRENCY_META_PROPNAME = 'currency';
    const THOUSAND_SEP_META_PROPNAME = 'thousand_separator';
    const DECIMAL_SEP_META_PROPNAME = 'decimal_separator';
    const VERSION_META_PROPNAME = 'version';

    /**
     * Below constants are the keys of description object's order details that
     * are being sent during request to Description Endpoint
     */
    const ID_ORDER_PROPNAME = 'id';
    const SUBTOTAL_ORDER_PROPNAME = 'subtotal';
    const TOTAL_ORDER_PROPNAME = 'total';

    const TEMPLATE_OPTION_TITLE_CUSTOM = 'tendopay/payment/title.phtml';


    /**
     * Marketing label constants
     */
    const TENDOPAY_LOGO_BLUE = 'https://s3-ap-southeast-1.amazonaws.com/tendo-static/logo/tp-logo-example-payments.png';
    const TENDOPAY_MARKETING = 'https://app.tendopay.ph/register';
    const REPAYMENT_SCHEDULE_API_ENDPOINT_URI = "payments/api/v1/repayment-calculator?tendopay_amount=%s";
    const REPAYMENT_CALCULATOR_INSTALLMENT_AMOUNT = 'installment_amount';

    /**
     * @return string
     */
    public function getRepaymentCalculatorInstallmentAmount()
    {
        return self::REPAYMENT_CALCULATOR_INSTALLMENT_AMOUNT;
    }

    /**
     * @return string
     */
    public function getRepaymentScheduleApiEndpointUri()
    {
        return self::REPAYMENT_SCHEDULE_API_ENDPOINT_URI;
    }

    /**
     * @return string
     */
    public function getTendopayCheckoutTitle()
    {
        return self::TEMPLATE_OPTION_TITLE_CUSTOM;
    }

    /**
     * @return string
     */
    public function getTendopayMethodCode()
    {
        return self::METHOD_WPS;
    }

    /**
     * Gets the hash algorithm.
     *
     * @return string hash algorithm
     */
    public function get_hash_algorithm()
    {
        return self::HASH_ALGORITHM;
    }

    /**
     * Gets the base api URL. It checks whether to use SANDBOX URL or Production URL.
     *
     * @return string the base api url
     */
    public function get_base_api_url()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_BASE_API_URL : self::BASE_API_URL;
    }

    /**
     * Gets the redirect uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string redirect uri
     */
    public function get_redirect_uri()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_REDIRECT_URI : self::REDIRECT_URI;
    }

    /**
     * Gets the view uri pattern. It checks whether to use SANDBOX pattern or Production pattern.
     *
     * @return string view uri pattern
     */
    public function get_view_uri_pattern()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_VIEW_URI_PATTERN : self::VIEW_URI_PATTERN;
    }

    /**
     * Gets the verification endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string verification endpoint uri
     */
    public function get_verification_endpoint_uri()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_VERIFICATION_ENDPOINT_URI : self::VERIFICATION_ENDPOINT_URI;
    }

    /**
     * Gets the authorization endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string authorization endpoint uri
     */
    public function get_authorization_endpoint_uri()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_AUTHORIZATION_ENDPOINT_URI : self::AUTHORIZATION_ENDPOINT_URI;
    }

    /**
     * Gets the description endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string description endpoint uri
     */
    public function get_description_endpoint_uri()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_DESCRIPTION_ENDPOINT_URI : self::DESCRIPTION_ENDPOINT_URI;
    }

    /**
     * Gets the bearer token endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string bearer token endpoint uri
     */
    public function get_bearer_token_endpoint_uri()
    {
        return $this->isSandboxEnabled() ? self::SANDBOX_BEARER_TOKEN_ENDPOINT_URI : self::BEARER_TOKEN_ENDPOINT_URI;
    }

    /**
     * Gets the bearer token endpoint uri. It checks whether to use SANDBOX URI or Production URI.
     *
     * @return string bearer token endpoint uri
     */
    public function get_repayment_calculator_api_endpoint_url()
    {
        $base_url = $this->isSandboxEnabled() ? self::SANDBOX_BASE_API_URL : self::BASE_API_URL;
        return $base_url . "/" . self::REPAYMENT_SCHEDULE_API_ENDPOINT_URI;
    }

    /**
     *
     * @return bool true if sandbox is enabled
     */
    public function isSandboxEnabled()
    {
        $base = $this->getTendopayStandardModel();
        $isSanboxEnabled = $this->getConfigValues($base->getAPIModeConfigField());
        if ($isSanboxEnabled == "sandbox") {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getVendorIdParam()
    {
        return self::VENDOR_ID_PARAM;
    }

    /**
     * @return string
     */
    public function getHashParam()
    {
        return self::HASH_PARAM;
    }

    /**
     * @return string
     */
    public function getVendorParam()
    {
        return self::VENDOR_PARAM;
    }

    /**
     * @return string
     */
    public function getRedirectUrlParam()
    {
        return self::REDIRECT_URL_PARAM;
    }

    /**
     * @return string
     */
    public function getTendopayCustomerReferenceOne()
    {
        return self::TENDOPAY_CUSTOMER_REFERENCE_1;
    }

    /**
     * @return string
     */
    public function getTendopayCustomerReferencetwo()
    {
        return self::TENDOPAY_CUSTOMER_REFERENCE_2;
    }

    /**
     * @return string
     */
    public function getDispositionParam()
    {
        return self::DISPOSITION_PARAM;
    }

    /**
     * @return string
     */
    public function getTransactionNoParam()
    {
        return self::TRANSACTION_NO_PARAM;
    }

    /**
     * @return string
     */
    public function getVerificationTokenParam()
    {
        return self::VERIFICATION_TOKEN_PARAM;
    }

    /**
     * @return string
     */
    public function getUserIDParam()
    {
        return self::USER_ID_PARAM;
    }

    /**
     * @return string
     */
    public function getStatusIDParam()
    {
        return self::STATUS_PARAM;
    }

    /**
     * @return string
     */
    public function getAuthTokenParam()
    {
        return self::AUTH_TOKEN_PARAM;
    }

    /**
     * @return string
     */
    public function getAmountParam()
    {
        return self::AMOUNT_PARAM;
    }

    /**
     * @return string
     */
    public function getDescParam()
    {
        return self::DESC_PARAM;
    }

    /**
     * @return string
     */
    public function PaymentFailedQueryParam()
    {
        return self::PAYMANET_FAILED_QUERY_PARAM;
    }

    /**
     * @return string
     */
    public function getTendopayLogoBlue()
    {
        return self::TENDOPAY_LOGO_BLUE;
    }

    /**
     * @return string
     */
    public function getTendopayMarketing()
    {
        return self::TENDOPAY_MARKETING;
    }

    /**
     * @return mixed
     */
    public function getCheckoutUrl()
    {
        return Mage::getUrl('checkout/cart/index');
    }

    /**
     * @return mixed
     */
    public function getRedirectUrl()
    {
        return Mage::getUrl('tendopay/standard/success');
    }

    /**
     * @return mixed
     */
    public function getPaymentVerificationUrl()
    {
        return Mage::getUrl('tendopay/cart', array('action' => 'verifypayment'));
    }

    /**
     * @return mixed
     */
    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * @return mixed
     */
    public function getTendopayStandardModel()
    {
        return Mage::getModel('tendopay/standard');
    }

    /**
     * @return mixed
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @param $message
     */
    public function setException($message)
    {
        Mage::throwException($this->__($message));
    }

    /**
     * @param $message
     */
    public function throwException($message)
    {
        throw Mage::exception('Mage_Core', $message);
    }

    /**
     * @param $message
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
     * @return mixed
     */
    protected function _getQuote($quoteId)
    {
        return Mage::getModel('sales/quote')->load($quoteId);
    }

    /**
     * @return mixed
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

    /**
     * @return string
     */
    public function getModuleVersion()
    {
        return (string)Mage::getConfig()->getModuleConfig('TendoPay_TendopayPayment')->version;
    }

    /**
     * @param $string
     * @return null|string|string[]
     */
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
            Mage::log($message, $level, $this->_logFileName);
        }
    }

    /**
     * @return bool
     */
    public function isDebugMode()
    {
        if ($this->_isDebugEnabled === null) {
            $this->_isDebugEnabled = Mage::getStoreConfigFlag('payment/tendopay/debug');
        }

        return $this->_isDebugEnabled;
    }

    /**
     * @param array $data
     * @return string
     */
    public function calculate(array $data)
    {
        $base = $this->getTendopayStandardModel();
        $secret = $this->getConfigValues($base->getAPIMerchantSecretConfigField());

        $data = array_map(
            function ($value) {
                return trim($value);
            }, $data
        );

        $hashKeysExclusionList = array($this->getHashParam());
        $exclusionList = $hashKeysExclusionList;
        $data = $this->arrayFilterKeys($data, $exclusionList);

        ksort($data);
        $message = join("", $data);
        return hash_hmac($this->get_hash_algorithm(), $message, $secret, false);
    }

    public function arrayFilterKeys($array, $exclusionList)
    {
        $newArray=array();
          foreach ($array as $key=>$value) {
              if (!in_array($key, $exclusionList) && !empty($value)) {
                  $newArray[$key]=$value;
              }
          }

          return $newArray;
    }


/**
     * Performs the actual API call.
     * @param $url $url url of the endpoint
     * @param array $data data to be posted to the endpoint
     *
     * @return TendoPay_TendopayPayment_Helper_Response response from the API call
     */
    public function doCall($url, array $data)
    {
        $base = $this->getTendopayStandardModel();
        $merchantId = $this->getConfigValues($base->getAPIMerchantIDConfigField());
        $data[$this->getVendorIdParam()] = $merchantId;
        $data[$this->getHashParam()] = $this->calculate($data);

        $headers = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_bearer_token(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Using' => 'TendoPay Magento1 Extension',
            ),
            'json' => $data
        );

        $this->client = new GuzzleHttp\Client(
            array(
                'base_uri' => $this->get_base_api_url()
            )
        );
        $response = $this->client->request("POST", $url, $headers);
        return new TendoPay_TendopayPayment_Helper_Response($response->getStatusCode(), $response->getBody());
    }

    /**
     * @return mixed
     */
    private function get_bearer_token()
    {
        $base = $this->getTendopayStandardModel();
        if (self::$_bearerToken === null) {
            $bearerTokenConfigField = $this->getConfigValues($base->getBearerTokenConfigField());
            self::$_bearerToken = $bearerTokenConfigField;
        }

        $bearerExpirationTimestamp = -1;
        if (self::$_bearerToken !== null && property_exists(self::$_bearerToken, 'expiration_timestamp')) {
            $bearerExpirationTimestamp = self::$_bearerToken->expiration_timestamp;
        }

        $currentTimestamp = Mage::getSingleton('core/date')->gmtTimestamp();
        if ($bearerExpirationTimestamp <= $currentTimestamp - 30) {
            $headers = array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Using' => 'TendoPay Magento1 Extension'
                ),
                'json' => array(
                    "grant_type" => "client_credentials",
                    "client_id" => $this->getConfigValues($base->getAPIClientIdConfigField()),
                    "client_secret" => $this->getConfigValues($base->getAPIClientSecretConfigField())
                )
            );

            $this->client = new GuzzleHttp\Client(
                array(
                    'base_uri' => $this->get_base_api_url()
                )
            );
            $response = $this->client->request('POST', $this->get_bearer_token_endpoint_uri(), $headers);
            $responseBody = (string)$response->getBody();
            $responseBody = json_decode($responseBody);

            self::$_bearerToken = new \stdClass();
            self::$_bearerToken->expiration_timestamp = $responseBody->expires_in + $currentTimestamp;
            self::$_bearerToken->token = $responseBody->access_token;

            $obj = new Zend_Serializer_Adapter_PhpSerialize();

            Mage::getConfig()->saveConfig(
                'payment/tendopay/bearer_token',
                $obj->serialize(self::$_bearerToken), 'default', 0
            );

        }

        return self::$_bearerToken->token;
    }


    public function get_default_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->get_bearer_token(),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'X-Using'       => 'TendoPay Magento1 Plugin',
        ];
    }
}