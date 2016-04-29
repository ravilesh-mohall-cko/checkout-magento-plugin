<?php
/**
 * Class CheckoutApi_ChargePayment_Helper_Data
 *
 * @version 20151002
 */
class CheckoutApi_ChargePayment_Helper_Data  extends Mage_Core_Helper_Abstract
{
    const CODE_CREDIT_CARD                  = 'checkoutapicard';
    const CODE_CREDIT_CARD_JS               = 'checkoutapijs';
    const JS_PATH_CARD_TOKEN                = 'https://cdn.checkout.com/sandbox/js/checkout.js';
    const JS_PATH_CARD_TOKEN_LIVE           = 'https://cdn3.checkout.com/js/checkout.js';

    const CREDIT_CARD_CHARGE_MODE_NOT_3D    = 1;
    const CREDIT_CARD_CHARGE_MODE_3D        = 2;
    const PAYMENT_ACTION_AUTHORIZE_CAPTURE  = 'authorize_capture';
    const API_MODE_LIVE                     = 'live';
    const API_MODE_SANDBOX                  = 'sandbox';

    /**
     * Return field from config by payment method and store ID
     *
     * @param $method
     * @param $field
     * @param null $storeId
     * @return mixed
     *
     * @version 20151006
     */
    public function getConfigData($method, $field, $storeId = NULL) {
        if (NULL === $storeId) {
            $storeId = Mage::app()->getStore();
        }

        $path = "payment/{$method}/" . $field;

        return Mage::getStoreConfig($path, $storeId);
    }

    /**
     * Retirn js Path for checkoutapijs
     *
     * @return string
     *
     * @version 20160202
     */
    public function getJsPathHtml() {
        $mode   = (string)$this->getConfigData(self::CODE_CREDIT_CARD_JS, 'mode');
        $jsUrl  = $mode === self::API_MODE_LIVE ? self::JS_PATH_CARD_TOKEN_LIVE : self::JS_PATH_CARD_TOKEN;

        return '<script src="' . $jsUrl . '" async ></script>';
    }
}