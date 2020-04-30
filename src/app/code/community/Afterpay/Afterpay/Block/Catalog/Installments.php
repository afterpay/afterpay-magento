<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 *
 * @method string getPageType()
 * @method Afterpay_Afterpay_Block_Catalog_Installments setPageType(string $pageType)
 */
class Afterpay_Afterpay_Block_Catalog_Installments extends Mage_Core_Block_Template
{
    const XML_CONFIG_PREFIX = 'afterpay/payovertime_installments/';

    /**
     * Retrieve product
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        $product = $this->_getData('product');
        if (!$product) {
            $product = Mage::registry('product');
        }
        return $product;
    }

    public function isEnabled()
    {
        $product = $this->getProduct();
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'enable_' . $this->getPageType())
            && Mage::getModel('afterpay/method_payovertime')->canUseForProduct($product)
            && !$product->isGrouped();
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

    public function getStoreConfigEnabled()
    {
        if (Mage::getStoreConfig('payment/afterpaypayovertime/' . Afterpay_Afterpay_Model_Method_Base::API_ENABLED_FIELD)) {
            // plugin enabled / disabled
            return 1;
        } else {
            return 0;
        }
    }

    public function getInstallmentsAmount()
    {
        return (int)Mage::getStoreConfig('payment/afterpaypayovertime/installments_amount');
    }

    public function getRegionSpecificText()
    {
        if(Mage::app()->getStore()->getCurrentCurrencyCode() == 'USD') {
            return 'bi-weekly with';
        } elseif(Mage::app()->getStore()->getCurrentCurrencyCode() == 'NZD') {
            return 'fortnightly with';
        } elseif(Mage::app()->getStore()->getCurrentCurrencyCode() == 'AUD') {
            return 'fortnightly with';
        }
    }

    public function getJsConfig()
    {
        $helper = Mage::helper('afterpay');
        return array(
            'selectors'          => $this->getCssSelectors(),
            'template'           => $this->getHtmlTemplate(),
            'priceSubstitution'  => '{price_here}',
            'regionSpecific'     => '{region_specific_text}',
            'regionText'         => $this->getRegionSpecificText(),
            'minPriceLimit'      => $helper->getMinPriceLimit(),
            'maxPriceLimit'      => $helper->getMaxPriceLimit(),
            'installmentsAmount' => $this->getInstallmentsAmount(),
            'afterpayEnabled'    => $this->getStoreConfigEnabled(),
            'priceFormat'        => Mage::app()->getLocale()->getJsPriceFormat(),
            'currencySymbol'     => Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol(),
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
