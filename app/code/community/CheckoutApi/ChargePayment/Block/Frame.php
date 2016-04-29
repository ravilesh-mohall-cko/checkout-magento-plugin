<?php
/**
 * Frame for js API
 *
 * Class CheckoutApi_ChargePayment_Block_Frame
 *
 * @version 20160203
 */
class CheckoutApi_ChargePayment_Block_Frame  extends Mage_Core_Block_Template
{
    /**
     * Return TRUE if is JS API
     *
     * @return bool
     *
     * @version 20160203
     */
    public function isJsApiPaymentMethod() {
       $paymentMethod = (string)Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethod();

        return $paymentMethod === CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_JS ? true : false;
    }

    /**
     * Return shade for overlayShade param
     *
     * @return mixed
     *
     * @version 20160203
     */
    public function getOverlayShade() {
        return Mage::helper('chargepayment')->getConfigData($this->_paymentCode, 'overlay_shade');
    }

    /**
     * Return opacity for overlayOpacity param
     *
     * @return mixed
     *
     * @version 20160203
     */
    public function getOverlayOpacity() {
        return Mage::helper('chargepayment')->getConfigData($this->_paymentCode, 'overlay_opacity');
    }

    /**
     * Return controller URL
     *
     * @return string
     *
     * @version 20160212
     */
    public function getControllerUrl() {
        $params     = array('form_key' => Mage::getSingleton('core/session')->getFormKey());
        $isSecure   = Mage::app()->getStore()->isCurrentlySecure();

        if ($isSecure){
            $secure = array('_secure' => true);
            $params = array_merge($params, $secure);

        }

        return $this->getUrl('chargepayment/api/place/', $params);
    }

    /**
     * Return Payment Code
     *
     * @return string
     *
     * @version 20160219
     */
    public function getPaymentCode() {
        return CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_JS;
    }
}