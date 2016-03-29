<?php
/**
 * Payment Info Block for Checkoutapicard, $_infoBlockType
 *
 * Class CheckoutApi_ChargePayment_Block_Form_Checkoutapicard
 *
 * @version 20151002
 */
class CheckoutApi_ChargePayment_Block_Info_Checkoutapicard  extends Mage_Payment_Block_Info_Cc
{
    /**
     * Retrieve credit card type name
     *
     * Removed cart type
     *
     * @return string
     */
    public function getCcTypeName()
    {
        $checkoutApiCardId  = $this->getInfo()->getCheckoutApiCardId();
        $cardType           = $this->getInfo()->getCcType();
        $isVisibleCcType    = Mage::getModel('chargepayment/creditCard')->getIsVisibleCcType();

        if ($isVisibleCcType && !empty($cardType)) {

            return parent::getCcTypeName();
        }

        if (!empty($checkoutApiCardId) && !empty($cardType)) {
            return $cardType;
        }

        return false;
    }
}