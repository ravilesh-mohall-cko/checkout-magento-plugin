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
    const CODE_CREDIT_CARD_KIT              = 'checkoutapikit';

    const JS_PATH_CARD_TOKEN                = 'https://cdn.checkout.com/sandbox/js/checkout.js';
    const JS_PATH_CARD_TOKEN_LIVE           = 'https://cdn3.checkout.com/js/checkout.js';
    const JS_PATH_CHECKOUT_KIT_LIVE         = 'https://sandbox.checkout.com/js/checkoutkit.js';
    const JS_PATH_CHECKOUT_KIT              = 'https://sandbox.checkout.com/js/checkoutkit.js';

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
     * Return js Path for Checkout JS
     *
     * @return string
     *
     * @version 20160202
     */
    public function getJsPathHtml() {
        $mode   = (string)$this->getConfigData(self::CODE_CREDIT_CARD_JS, 'mode');
        $jsUrl  = $mode === self::API_MODE_LIVE ? self::JS_PATH_CARD_TOKEN_LIVE : self::JS_PATH_CARD_TOKEN;

        return '<script src="' . $jsUrl . '" async></script>';
    }

    /**
     * Return js Path for Checkout Kit
     *
     * @return string
     *
     * @version 20160502
     */
    public function getKitJsPathHtml() {
        $mode   = (string)$this->getConfigData(self::CODE_CREDIT_CARD_KIT, 'mode');
        $jsUrl  = $mode === self::API_MODE_LIVE ? self::JS_PATH_CHECKOUT_KIT_LIVE : self::JS_PATH_CHECKOUT_KIT;

        return '<script src="' . $jsUrl . '" id="cko_script_tag" async></script>';
    }

    /**
     * Return current extension version
     *
     * @return string
     *
     * @version 20160510
     */
    public function getExtensionVersion() {
        return (string)Mage::getConfig()->getModuleConfig("CheckoutApi_ChargePayment")->version;
    }
}