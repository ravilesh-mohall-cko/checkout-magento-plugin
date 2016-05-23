<?php

/**
 * Controller for Checkout.com Webhooks
 *
 * Class CheckoutApi_ChargePayment_ApiController
 *
 * @version 20151113
 */
class CheckoutApi_ChargePayment_ApiController extends Mage_Core_Controller_Front_Action
{
    /**
     * Routing for webhooks from Checkout.com
     *
     * @url chargepayment/api/webhook/
     *
     * @version 20151113
     */
    public function webhookAction()
    {
        $modelWebhook   = Mage::getModel('chargepayment/webhook');

        $isDebug = Mage::getModel('chargepayment/creditCard')->isDebug();

        if ($isDebug) {
            Mage::log(file_get_contents('php://input'), null, CheckoutApi_ChargePayment_Model_Webhook::LOG_FILE);
            Mage::log(json_decode(file_get_contents('php://input')), null, CheckoutApi_ChargePayment_Model_Webhook::LOG_FILE);
        }

        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        $request        = new Zend_Controller_Request_Http();
        $key            = $request->getHeader('Authorization');

        if (!$modelWebhook->isValidPublicKey($key)) {
            $this->getResponse()->setHttpResponseCode(401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'));

        if (empty($data)) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        $eventType          = $data->eventType;

        if (!$modelWebhook->isValidResponse($data)) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        switch ($eventType) {
            case CheckoutApi_ChargePayment_Model_Webhook::EVENT_TYPE_CHARGE_CAPTURED:
                $result = $modelWebhook->captureOrder($data);
                break;
            case CheckoutApi_ChargePayment_Model_Webhook::EVENT_TYPE_CHARGE_REFUNDED:
                $result = $modelWebhook->refundOrder($data);
                break;
            case CheckoutApi_ChargePayment_Model_Webhook::EVENT_TYPE_CHARGE_VOIDED:
                $result = $modelWebhook->voidOrder($data);
                break;
            case CheckoutApi_ChargePayment_Model_Webhook::EVENT_TYPE_INVOICE_CANCELLED:
                $result = $modelWebhook->voidOrder($data);
                break;
            default:
                $this->getResponse()->setHttpResponseCode(500);
                return;
        }

        $httpCode = $result ? 200 : 400;

        $this->getResponse()->setHttpResponseCode($httpCode);
    }

    /**
     * Action for verify charge by payment token
     *
     * @url chargepayment/api/callback/?cko-payment-token=payment_token
     *
     * @version 20160219
     */
    public function callbackAction() {
        $responseToken  = (string)$this->getRequest()->getParam('cko-payment-token');

        $session        = Mage::getSingleton('chargepayment/session_quote');
        $isLocalPayment = $session->isCheckoutLocalPaymentTokenExist($responseToken);

        if ($isLocalPayment) {
            $this->_redirect('chargepayment/api/complete', array('_query' => 'token=' . $responseToken));
            return;
        }

        $modelWebhook   = Mage::getModel('chargepayment/webhook');

        if ($responseToken) {
            $result = $modelWebhook->authorizeByPaymentToken($responseToken);

            if ($result['is_admin'] === false) {
                $redirectUrl    = 'checkout/onepage/success';

                if ($result['error'] === true) {
                    $redirectUrl = 'checkout/onepage/';
                    Mage::getSingleton('core/session')->addError('Please check you card details and try again. Thank you');
                }

                $this->_redirect($redirectUrl);
            }

            return;
        }
    }

    /**
     * Local Payment Complete Page
     *
     * @url chargepayment/api/complete
     *
     * @return Mage_Core_Controller_Varien_Action
     *
     * @version 20160426
     */
    public function completeAction() {
        $responseToken  = (string)$this->getRequest()->getParam('token');

        if (!$responseToken) {
            $this->norouteAction();
            return;
        }

        $session        = Mage::getSingleton('chargepayment/session_quote');
        $isLocalPayment = $session->isCheckoutLocalPaymentTokenExist($responseToken);

        if (!$isLocalPayment) {
            $this->norouteAction();
            return;
        }

        $session->removeCheckoutLocalPaymentToken($responseToken);

        $this->loadLayout();

        $this->getLayout()
            ->getBlock('head')
            ->setTitle($this->__('Local Payment Completed (Checkout.com)'));

        $this->renderLayout();
    }
}
