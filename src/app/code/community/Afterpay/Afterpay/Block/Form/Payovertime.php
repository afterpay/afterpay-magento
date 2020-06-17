<?php

/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */
class Afterpay_Afterpay_Block_Form_Payovertime extends Afterpay_Afterpay_Block_Form_Abstract
{
    const TITLE_TEMPLATE_SELECTOR_ID = 'afterpay_checkout_payovertime_headline';

    const DETAIL_TEMPLATE_SELECTOR_ID = 'afterpay_checkout_payovertime_form';

    const CONFIG_PATH_CHECKOUT_TITLE_TEMPLATE = 'afterpay/payovertime_checkout/checkout_headline_html_template';

    const CONFIG_PATH_CHECKOUT_DETAILS_TEMPLATE = 'afterpay/payovertime_checkout/checkout_details_html_template';

    const CONFIG_PATH_SHOW_DETAILS = 'afterpay/payovertime_checkout/show_checkout_details';

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

    public function getInstalmentAmountCreditUsed()
    {
        if (!$this->hasData('instalment_amount_credit_used')) {
            $formatted = Mage::helper('afterpay')->calculateInstalment(true);
            $this->setData('instalment_amount_credit_used', $formatted);
        }

        return $this->getData('instalment_amount_credit_used');
    }

    public function getInstalmentAmountLast()
    {
        if (!$this->hasData('instalment_amount_last')) {
            $formatted = Mage::helper('afterpay')->calculateInstalmentLast();
            $this->setData('instalment_amount_last', $formatted);
        }

        return $this->getData('instalment_amount_last');
    }

    public function getInstalmentAmountLastCreditUsed()
    {
        if (!$this->hasData('instalment_amount_last_credit_used')) {
            $formatted = Mage::helper('afterpay')->calculateInstalmentLast(true);
            $this->setData('instalment_amount_last_credit_used', $formatted);
        }

        return $this->getData('instalment_amount_last_credit_used');
    }

    public function getOrderTotal()
    {
        $total = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        return Mage::app()->getStore()->formatPrice($total, false);
    }

    public function getOrderTotalCreditUsed()
    {
        $total = Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
        $total = $total - Mage::helper('afterpay')->getCustomerBalance();
        if ($total < 0) {
            $total = 0;
        }
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

    private function _getCommonConfiguration()
    {
        return array(
            'afterpayLogoSubstitution' => '{afterpay_logo}',
            'afterpayLogo' => 'https://static.afterpay.com/integration/logo-afterpay-colour-72x15@2x.png',
            'orderAmountSubstitution' => '{order_amount}',
            'orderAmount' => $this->getOrderTotal(),
            'orderAmountCreditUsed' => $this->getOrderTotalCreditUsed(),
            'regionSpecificSubstitution' => '{region_specific_text}',
            'regionText' => $this->getRegionSpecificText(),
            'installmentAmountSubstitution' => '{instalment_amount}',
            'installmentAmount' => $this->getInstalmentAmount(),
            'installmentAmountCreditUsed' => $this->getInstalmentAmountCreditUsed(),
            'installmentAmountSubstitutionLast' => '{instalment_amount_last}',
            'installmentAmountLast' => $this->getInstalmentAmountLast(),
            'installmentAmountLastCreditUsed' => $this->getInstalmentAmountLastCreditUsed(),
            'imageCircleOneSubstitution' => '{img_circle_1}',
            'imageCircleOne' => 'https://static.afterpay.com/checkout/circle_1@2x.png',
            'imageCircleTwoSubstitution' => '{img_circle_2}',
            'imageCircleTwo' => 'https://static.afterpay.com/checkout/circle_2@2x.png',
            'imageCircleThreeSubstitution' => '{img_circle_3}',
            'imageCircleThree' => 'https://static.afterpay.com/checkout/circle_3@2x.png',
            'imageCircleFourSubstitution' => '{img_circle_4}',
            'imageCircleFour' => 'https://static.afterpay.com/checkout/Circle_4@2x.png',
            'creditUsedSelector' => '#use_customer_balance'
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
