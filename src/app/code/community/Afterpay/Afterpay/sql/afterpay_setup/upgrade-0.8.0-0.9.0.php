<?php
/**
 * @package   Afterpay_Afterpay
 * @author    Afterpay
 * @copyright 2016-2018 Afterpay https://www.afterpay.com
 */

/* @var $installer Mage_Sales_Model_Resource_Setup */

$installer = $this;

$installer->startSetup();

$blockData = array(
    'identifier' => 'afterpay_video_banner',
    'title'      => 'Afterpay Video Banner',
    'content'    => '<div class="afterpay-banner">
    <a href="#" class="youtube-video" data-id="6nhNMv5TYDM"><img src="{{skin url=\'afterpay/images/banner_images/468-68.jpg\'}}" alt="AfterPay"></a>
</div>

<script type="text/javascript">
    jQuery(function ($) {
        $(".youtube-video").afterpayYoutube();
    });
   jQuery.noConflict();
</script>

<style>
.afterpay-banner {
    text-align: center;
    margin: 15px 20px;
}

.afterpay-banner img {
    max-width: 100%;
    max-height: 100%;
    text-align: center;
}
</style>
',
    'is_active'  => 1,
    'stores'     => array(0)
);

/** @var $block Mage_Cms_Model_Block */
$block = Mage::getModel('cms/block');

$block->load($blockData['identifier'], 'identifier');

if (!$block->isObjectNew()) {
    unset($blockData['identifier']);
}
$block->addData($blockData)->save();

$installer->endSetup();
