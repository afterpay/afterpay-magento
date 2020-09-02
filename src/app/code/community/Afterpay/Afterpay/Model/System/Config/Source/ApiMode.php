<?php

/**
 * API Model configuration source model
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */
class Afterpay_Afterpay_Model_System_Config_Source_ApiMode
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
     * Get configured Afterpay environments from config.xml
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
            $websiteId = null;
        }

        if (Mage::app()->getWebsite($websiteId)->getConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT) == 'USD' ||
            Mage::app()->getWebsite($websiteId)->getConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT) == 'CAD') {
            $api = 'api_us_url';
            $web = 'web_us_url';
        } else {
            $api = 'api_url';
            $web = 'web_url';
        }

        $options = array();

        foreach (Mage::getConfig()->getNode('afterpay/environments')->children() as $environment) {
            $options[$environment->getName()] = array(
                self::KEY_NAME      => (string) $environment->name,
                self::KEY_API_URL   => (string) $environment->{$api},
                self::KEY_WEB_URL   => (string) $environment->{$web},
            );
        }

        return $options;
    }

    /**
     * Get currencyCode for the store
     *
     * @return array
     */
    public static function getCurrencyCode()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }
}
