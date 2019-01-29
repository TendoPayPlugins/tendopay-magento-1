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
 * Class TendoPay_TendopayPayment_Model_System_Config_Source_ApiMode
 */
class TendoPay_TendopayPayment_Model_System_Config_Source_ApiMode
{
    const KEY_NAME = 'name';
    const KEY_API_URL = 'api_url';
    const KEY_WEB_URL = 'web_url';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        $config = self::_getConfigSettings();
        foreach ($config as $name => $settings) {
            $options[$name] = $settings[self::KEY_NAME];
        }

        return $options;
    }

    /**
     * @return array
     */
    protected static function _getConfigSettings()
    {
        $api = 'api_url';
        $options = array();
        foreach (Mage::getConfig()->getNode('tendopay/environments')->children() as $environment) {
            $options[$environment->getName()] = array(
                self::KEY_NAME => (string)$environment->name,
                self::KEY_API_URL => (string)$environment->{$api}
            );
        }

        return $options;
    }
}
