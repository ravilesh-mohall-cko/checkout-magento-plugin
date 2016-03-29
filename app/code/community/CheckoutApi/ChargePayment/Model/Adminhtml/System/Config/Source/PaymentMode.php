<?php

/**
 * Class CheckoutApi_ChargePayment_Model_Adminhtml_System_Config_Source_PaymentMode
 *
 * @version 20151007
 */
class CheckoutApi_ChargePayment_Model_Adminhtml_System_Config_Source_PaymentMode
{
    /**
     * Decorate select in System Configuration
     *
     * @return array
     *
     * @version
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'mixed',
                'label' => Mage::helper('chargepayment')->__('Mixed')
            ),
            array(
                'value' => 'card',
                'label' => Mage::helper('chargepayment')->__('Card')
            ),
            array(
                'value' => 'localpayment',
                'label' => Mage::helper('chargepayment')->__('Local Payment')
            ),
        );
    }
}