<?php

/**
 * Class CheckoutApi_ChargePayment_Model_Observer
 *
 * @version 20151203
 */
class CheckoutApi_ChargePayment_Model_Observer {

    /**
     * Cancel Order after Void
     *
     * @param $observer
     * @return CheckoutApi_ChargePayment_Model_Observer
     * @throws Exception
     *
     * @version 20151203
     */
    public function setOrderStatusForVoid(Varien_Event_Observer $observer) {
        $orderId            = Mage::app()->getRequest()->getParam('order_id');
        $order              = Mage::getModel('sales/order')->load($orderId);

        if (!is_object($order)) {
            return $this;
        }
        $payment            = $order->getPayment();
        $paymentCode        = (string)$payment->getMethodInstance()->getCode();
        $isCancelledOrder   = false;

        if ($paymentCode === CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD) {
            $isCancelledOrder   = Mage::getModel('chargepayment/creditCard')->getVoidStatus();
        } else if ($paymentCode === CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_JS) {
            $isCancelledOrder   = Mage::getModel('chargepayment/creditCardJs')->getVoidStatus();
        }

        if (!$isCancelledOrder) {
            return;
        }

        $message    = 'Transaction has been void';

        $order->registerCancellation($message);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
        $order->save();

        return $this;
    }

    /**
     * Save order into registry to use it in the overloaded controller.
     *
     * @param Varien_Event_Observer $observer
     * @return CheckoutApi_ChargePayment_Model_Observer
     *
     * @version 20160215
     *
     */
    public function saveOrderAfterSubmit(Varien_Event_Observer $observer)
    {
        /* @var $order Mage_Sales_Model_Order */
        $order = $observer->getEvent()->getData('order');
        Mage::register('charge_payment_order', $order, true);

        return $this;
    }

    /**
     * Set data for response of frontend saveOrder action
     *
     * @param Varien_Event_Observer $observer
     * @return CheckoutApi_ChargePayment_Model_Observer
     *
     * @version 20160215
     */
    public function addAdditionalFieldsToResponseFrontend(Varien_Event_Observer $observer)
    {
        /* @var $order Mage_Sales_Model_Order */
        $order = Mage::registry('charge_payment_order');

        if ($order && $order->getId()) {
            $payment = $order->getPayment();
            if ($payment &&
                ($payment->getMethod() == CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD
                    || $payment->getMethod() == CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_JS
                    || $payment->getMethod() == CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_KIT
                )
            ) {
                /* @var $controller Mage_Core_Controller_Varien_Action */
                $controller = $observer->getEvent()->getData('controller_action');
                $result = Mage::helper('core')->jsonDecode(
                    $controller->getResponse()->getBody('default'),
                    Zend_Json::TYPE_ARRAY
                );

                if (empty($result['error'])) {
                    $redirectUrl        = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
                    $session            = Mage::getSingleton('chargepayment/session_quote');

                    $result['success']      = true;
                    $result['is3d']         = false;
                    $result['redirect_url'] = $redirectUrl;

                    /* Local Payment */
                    $isLocalPayment = $session->getIsLocalPayment();
                    $lpRedirectUrl  = $session->getLpRedirectUrl();


                    /* Normal Payment */
                    $is3d               = $session->getIs3d();
                    $paymentRedirectUrl = $session->getPaymentRedirectUrl();

                    if ($isLocalPayment) {
                        $result['success']      = true;
                        $result['is3d']         = false;
                        $result['redirect_url'] = $lpRedirectUrl;

                        $session->unsetData('is_local_payment');
                        $session->unsetData('lp_redirect_url');
                    } else if ($is3d) {
                        /* Restore session for 3d payment */
                        $result['success']      = true;
                        $result['is3d']         = true;
                        $result['redirect_url'] = $paymentRedirectUrl;

                        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                        $order->save();

                        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
                        $session->setLastOrderIncrementId($order->getIncrementId());
                        $session->addCheckoutOrderIncrementId($order->getIncrementId());

                        if ($quote->getId()) {
                            $quote->setIsActive(1)
                                ->setReservedOrderId(NULL)
                                ->save();
                            Mage::getSingleton('checkout/session')->replaceQuote($quote);
                        }

                        $session->unsetData('is3d');
                        $session->unsetData('payment_redirect_url');
                    }

                    $controller->getResponse()->clearHeader('Location');
                    $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                }
            }
        }

        return $this;
    }
}