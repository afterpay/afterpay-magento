<?php

class Afterpay_Afterpay_Block_Modal extends Mage_Core_Block_Template
{
    /**
     * Get the Modal Redirection URL
     *
     * @return string
     */
    public function getModalRedirection()
    {
        $url = '';

        switch (Mage::app()->getStore()->getCurrentCurrencyCode())
        {
            case 'USD':
                $url = 'https://www.afterpay.com/purchase-payment-agreement';
                break;
            default:
                $url = 'https://www.afterpay.com/terms';
                break;
        }

        return $url;
    }

    /**
     * Get the Desktop Modal Assets
     *
     * @return string
     */
    public function getDesktopModalAssets()
    {
        $src = '';
        $srcset = '';

        switch (Mage::app()->getStore()->getCurrentCurrencyCode())
        {
            case 'USD':
                $src     =  'https://static.afterpay.com/us-popup-medium.png';
                $srcset  =  'https://static.afterpay.com/us-popup-medium.png 1x,';
                $srcset .= ' https://static.afterpay.com/us-popup-medium@2x.png 2x';
                break;
            default:
                $src     =  'https://static.afterpay.com/lightbox-desktop.png';
                $srcset  =  'https://static.afterpay.com/lightbox-desktop.png 1x,';
                $srcset .= ' https://static.afterpay.com/lightbox-desktop@2x.png 2x,';
                $srcset .= ' https://static.afterpay.com/lightbox-desktop@3x.png 3x';
                break;
        }

        $img = '<img class="afterpay-modal-image" src="'.$src.'" srcset="'.$srcset.'" alt="Afterpay" />';

        return $img;
    }

    /**
     * Get the Mobile Modal Assets
     *
     * @return string
     */
    public function getMobileModalAssets()
    {
        $src = '';
        $srcset = '';

        switch (Mage::app()->getStore()->getCurrentCurrencyCode())
        {
            case 'USD':
                $src     =  'https://static.afterpay.com/us-popup-small.png';
                $srcset  =  'https://static.afterpay.com/us-popup-small.png 1x,';
                $srcset .= ' https://static.afterpay.com/us-popup-small@2x.png 2x';
                break;
            default:
                $src     =  'https://static.afterpay.com/lightbox-mobile.png';
                $srcset  =  'https://static.afterpay.com/lightbox-mobile.png 1x,';
                $srcset .= ' https://static.afterpay.com/lightbox-mobile@2x.png 2x,';
                $srcset .= ' https://static.afterpay.com/lightbox-mobile@3x.png 3x';
                break;
        }

        $img = '<img class="afterpay-modal-image-mobile" src="'.$src.'" srcset="'.$srcset.'" alt="Afterpay" />';

        return $img;
    }


}
