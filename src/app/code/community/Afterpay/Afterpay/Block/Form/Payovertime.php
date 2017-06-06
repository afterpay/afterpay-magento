<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay <steven.gunarso@touchcorp.com>
 * @copyright Copyright (c) 2016 Afterpay (http://www.afterpay.com.au/)
 */
class Afterpay_Afterpay_Block_Form_Payovertime extends Afterpay_Afterpay_Block_Form_Abstract
{
    const TITLE_TEMPLATE_SELECTOR_ID = 'afterpay_checkout_payovertime_headline';

    const DETAIL_TEMPLATE_SELECTOR_ID = 'afterpay_checkout_payovertime_form';

    const CONFIG_PATH_CHECKOUT_TITLE_TEMPLATE = 'afterpay/payovertime_checkout/checkout_headline_html_template';

    const CONFIG_PATH_CHECKOUT_DETAILS_TEMPLATE = 'afterpay/payovertime_checkout/checkout_details_html_template';

    const CONFIG_PATH_SHOW_DETAILS = 'afterpay/payovertime_checkout/show_checkout_details';

    const TEMPLATE_OPTION_TITLE_DEFAULT = 'afterpay/checkout/title.phtml';

    const TEMPLATE_OPTION_DETAILS_DEFAULT = 'afterpay/form/payovertime.phtml';

    const TEMPLATE_OPTION_TITLE_CUSTOM = 'afterpay/checkout/title_custom.phtml';

    const TEMPLATE_OPTION_DETAILS_CUSTOM = 'afterpay/form/payovertime_custom.phtml';

    protected function _construct()
    {
        parent::_construct();

        // logic borrowed from Mage_Paypal_Block_Standard_form
        $block = Mage::getConfig()->getBlockClassName('core/template');
        $block = new $block;
        $block->setTemplateHelper($this);
        $block->setTemplate(self::TEMPLATE_OPTION_TITLE_CUSTOM);

        if (Mage::getStoreConfigFlag(self::CONFIG_PATH_SHOW_DETAILS)) {
            $this->setTemplate(self::TEMPLATE_OPTION_DETAILS_CUSTOM);
        } else {
            $this->setTemplate('');
        }
        $this->setMethodTitle('')
            ->setMethodLabelAfterHtml($block->toHtml());
    }

    public function getInstalmentAmount()
    {
        if (!$this->hasData('instalment_amount')) {
            $formatted = Mage::helper('afterpay')->calculateInstalment();
            $this->setData('instalment_amount', $formatted);
        }

        return $this->getData('instalment_amount');
    }

    public function getOrderTotal()
    {
        $total = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        return Mage::app()->getStore()->formatPrice($total, false);
    }

    public function getDetailsConfiguration()
    {
        $config = $this->_getCommonConfiguration();
        $config['template'] = $this->_getCustomDetailTemplate();
        $config['cssSelector'] = '#' . $this->getDetailsTemplateId();
        return $config;
    }

    public function getTitleConfiguration()
    {
        $config = $this->_getCommonConfiguration();
        $config['template'] = $this->_getCustomTitleTemplate();
        $config['cssSelector'] = '#' . $this->getTitleTemplateId();
        return $config;
    }

    public function getDetailsTemplateId()
    {
        return self::DETAIL_TEMPLATE_SELECTOR_ID;
    }

    public function getTitleTemplateId()
    {
        return self::TITLE_TEMPLATE_SELECTOR_ID;
    }

    private function _getCommonConfiguration()
    {
        return array(
            'afterpayLogoSubstitution' => '{afterpay_logo}',
            'afterpayLogo' => $this->getSkinUrl('afterpay/images/ap-logo-152x31.png'),
            'orderAmountSubstitution' => '{order_amount}',
            'orderAmount' => $this->getOrderTotal(),
            'installmentAmountSubstitution' => '{instalment_amount}',
            'installmentAmount' => $this->getInstalmentAmount(),
            'imageCircleOneSubstitution' => '{img_circle_1}',
            'imageCircleOne' => $this->getSkinUrl('afterpay/images/checkout/circle_1@2x.png'),
            'imageCircleTwoSubstitution' => '{img_circle_2}',
            'imageCircleTwo' => $this->getSkinUrl('afterpay/images/checkout/circle_2@2x.png'),
            'imageCircleThreeSubstitution' => '{img_circle_3}',
            'imageCircleThree' => $this->getSkinUrl('afterpay/images/checkout/circle_3@2x.png'),
            'imageCircleFourSubstitution' => '{img_circle_4}',
            'imageCircleFour' => $this->getSkinUrl('afterpay/images/checkout/circle_4@2x.png')
        );
    }

    private function _getCustomDetailTemplate()
    {
        return Mage::getStoreConfig(self::CONFIG_PATH_CHECKOUT_DETAILS_TEMPLATE);
    }

    private function _getCustomTitleTemplate()
    {
        return Mage::getStoreConfig(self::CONFIG_PATH_CHECKOUT_TITLE_TEMPLATE);
    }
}