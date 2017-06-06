<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */
class Afterpay_Afterpay_Block_Banner extends Mage_Core_Block_Template
{
    const XML_CONFIG_PREFIX = 'afterpay/banner/';

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'enabled');
    }

    /**
     * @return array
     */
    public function getCssSelector()
    {
        $selectors = Mage::getStoreConfig(self::XML_CONFIG_PREFIX . 'banner_block_selector');
        return explode("\n", $selectors);
    }

    /**
     * @return array
     */
    public function getJsConfig()
    {
        return array(
            'selector'  => $this->getCssSelector(),
            'className' => 'afterpay-banner'
        );
    }

    /**
     * @param string $scriptUrl
     * @param bool   $addModuleVersion
     *
     * @return string
     */
    public function getScriptHtml($scriptUrl, $addModuleVersion = true)
    {
        if ($addModuleVersion) {
            $scriptUrl .= "?v=" . $this->getModuleVersion();
        }

        return "document.write('<script src=\"" . $scriptUrl . "\">" . '<\/script>\');';
    }

    /**
     * @return string
     */
    public function getModuleVersion()
    {
        /** @var Mage_Core_Model_Config_Element $moduleConfig */
        $moduleConfig = Mage::getConfig()->getModuleConfig($this->getModuleName());
        return (string)$moduleConfig->version;
    }
}
