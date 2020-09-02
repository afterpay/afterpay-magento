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
            && Mage::helper('afterpay/checkout')->noConflict()
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
        ob_start();
?>
<div style="position: relative; font-style: italic; line-height: 1.4;" class="afterpay-installments">
    or 4 interest-free payments of {price_here} with<br/>
    <img src="https://static.afterpay.com/integration/logo-afterpay-colour-72x15@2x.png" style="width: 76px; vertical-align: middle; display: inline;" />
    <a href="#afterpay-what-is-modal" class="afterpay-what-is-modal-trigger">Learn more</a>
</div>
<style type="text/css">.price-box.ciq_price_box .ciq_view_shipping{margin-top:35px}</style>
<?php
        return ob_get_clean();
    }

    public function getMinPriceLimit()
    {
        if (Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'check_order_total_limits')) {
            // min order total limit for Afterpay Pay Over Time payment method
            return (float)Mage::getStoreConfig('payment/afterpaypayovertime/min_order_total');
        } else {
            return 0;
        }
    }

    public function getMaxPriceLimit()
    {
        if (Mage::getStoreConfigFlag(self::XML_CONFIG_PREFIX . 'check_order_total_limits')) {
            // max order total limit for Afterpay Pay Over Time payment method
            return (float)Mage::getStoreConfig('payment/afterpaypayovertime/max_order_total');
        } else {
            return 0;
        }
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

    public function getJsConfig()
    {
        return array(
            'selectors'          => $this->getCssSelectors(),
            'template'           => $this->getHtmlTemplate(),
            'priceSubstitution'  => '{price_here}',
            'minPriceLimit'      => $this->getMinPriceLimit(),
            'maxPriceLimit'      => $this->getMaxPriceLimit(),
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
