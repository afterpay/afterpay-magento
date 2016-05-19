<?php

/**
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2015 VEN Commerce Ltd (http://www.ven.com)
 *
 * @method string getPageType()
 * @method Afterpay_Afterpay_Block_Catalog_Installments setPageType(string $pageType)
 */
class Afterpay_Afterpay_Block_Catalog_Installments extends Mage_Core_Block_Template
{
    const XML_CONFIG_PREFIX = 'afterpay/payovertime_installments/';

    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'enable_' . $this->getPageType());

    }

    public function getCssSelectors()
    {
        $selectors = Mage::getStoreConfig(self::XML_CONFIG_PREFIX . $this->getPageType() . '_price_block_selectors');
        return explode("\n", $selectors);
    }

    public function getHtmlTemplate()
    {
        $result = Mage::getStoreConfig(self::XML_CONFIG_PREFIX . $this->getPageType() . '_html_template');
        $result = str_replace(
            '{skin_url}',
            Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN),
            $result
        );
        return $result;
    }

    public function getMinPriceLimit()
    {
        if (Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'check_order_total_limits')) {
            // min order total limit for Afterpay Pay Over Time payment method
            return (int)Mage::getStoreConfig('payment/afterpaypayovertime/min_order_total');
        } else {
            return 0;
        }
    }

    public function getMaxPriceLimit()
    {
        if (Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'check_order_total_limits')) {
            // max order total limit for Afterpay Pay Over Time payment method
            return (int)Mage::getStoreConfig('payment/afterpaypayovertime/max_order_total');
        } else {
            return 0;
        }
    }

    public function getInstallmentsAmount()
    {
        return (int)Mage::getStoreConfig('payment/afterpaypayovertime/installments_amount');
    }

    public function getJsConfig()
    {
        return array(
            'selectors'          => $this->getCssSelectors(),
            'template'           => $this->getHtmlTemplate(),
            'priceSubstitution'  => '{price_here}',
            'minPriceLimit'      => $this->getMinPriceLimit(),
            'maxPriceLimit'      => $this->getMaxPriceLimit(),
            'installmentsAmount' => $this->getInstallmentsAmount(),
            'priceFormat'        => Mage::app()->getLocale()->getJsPriceFormat(),
            'className'          => 'afterpay-installments-amount'
        );
    }

    protected function _toHtml()
    {
        if (!$this->isEnabled()) {
            return '';
        } else {
            return parent::_toHtml();
        }
    }

}