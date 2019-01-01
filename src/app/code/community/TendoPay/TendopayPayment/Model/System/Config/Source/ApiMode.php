<?php

class TendoPay_TendopayPayment_Model_System_Config_Source_ApiMode
{
    const KEY_NAME      = 'name';
    const KEY_API_URL   = 'api_url';
    const KEY_WEB_URL   = 'web_url';

    /**
     * Convert to option array
     *
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
     * Get config prefix for selected API mode
     *
     * @param string $environment
     * @return null|string
     */
    public static function getEnvironmentSettings($environment)
    {
        $settings = self::_getConfigSettings();

        if (isset($settings[$environment])) {
            return $settings[$environment];
        }

        return null;
    }

    /**
     * Get configured TendoPay environments from config.xml
     *
     * @return array
     */
    protected static function _getConfigSettings()
    {
        if(Mage::app()->getStore()->isAdmin()) {
            $websiteCode = Mage::app()->getRequest()->getParam('website');

            if ($websiteCode) {
                $website = Mage::getModel('core/website')->load($websiteCode);
                $websiteId = $website->getId();
            } else {
                $order_id = Mage::app()->getRequest()->getParam('order_id');

                if($order_id) {
                    $websiteId = Mage::getModel('core/store')->load(Mage::getModel('sales/order')->load($order_id)->getStoreId())->getWebsiteId();
                } else {
                    $websiteId = 0;
                }
            }
        } else {
            $websiteId = '';
        }

        $api = 'api_url';
        $web = 'web_url';

        $options = array();

        foreach (Mage::getConfig()->getNode('tendopay/environments')->children() as $environment) {
            $options[$environment->getName()] = array(
                self::KEY_NAME      => (string) $environment->name,
                self::KEY_API_URL   => (string) $environment->{$api}
            );
        }

        return $options;
    }
}
