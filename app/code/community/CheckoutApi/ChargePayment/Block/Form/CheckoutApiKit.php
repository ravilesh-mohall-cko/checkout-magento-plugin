<?php
/**
 * Payment Block for Checkout Kit, $_formBlockType
 *
 * Class CheckoutApi_ChargePayment_Block_Form_CheckoutApiKit
 *
 * @version 20160502
 */
class CheckoutApi_ChargePayment_Block_Form_CheckoutApiKit  extends Mage_Payment_Block_Form_Cc
{
    /**
     * @var
     */
    private $_helper;

    /**
     * Set template for checkout page
     *
     * @version 20160502
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('checkoutapi/chargepayment/form/checkoutapikit.phtml');
        $this->_helper = Mage::helper('chargepayment');
    }

    /**
     * Return true if secret key is correct
     *
     * @return bool
     *
     * @version 20160502
     */
    public function isActive() {
        $secretKey = $this->_helper->getConfigData(CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_KIT, 'secretkey');
        $publicKey = $this->getPublicKey();

        return !empty($secretKey) && !empty($publicKey) ? true : false;
    }

    /**
     * Return Stored Public Key
     *
     * @return mixed
     *
     * @version 20160502
     */
    public function getPublicKey() {
        return $this->_helper->getConfigData(CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_KIT, 'publickey');
    }

    /**
     * Return Debug Mode
     *
     * @return mixed
     *
     * @version 20160502
     */
    public function getDebugMode() {
        return Mage::getModel('chargepayment/creditCardKit')->isDebug();
    }

    /**
     * Return Customer Email
     *
     * @return mixed
     *
     * @version 20160504
     */
    public function getCustomerEmail() {
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        return $quote->getBillingAddress()->getEmail();
    }
}