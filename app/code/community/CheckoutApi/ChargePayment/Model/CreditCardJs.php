<?php

/**
 * Class for CreditCardJs payment method
 *
 * Class CheckoutApi_ChargePayment_Model_CreditCardJs
 *
 * @version 20160202
 */
class CheckoutApi_ChargePayment_Model_CreditCardJs extends CheckoutApi_ChargePayment_Model_Checkout
{
    protected $_code            = CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_JS;
    protected $_canUseInternal  = false;

    protected $_formBlockType = 'chargepayment/form_checkoutapijs';
    protected $_infoBlockType = 'chargepayment/info_checkoutapijs';

    const RENDER_MODE       = 2;
    const RENDER_NAMESPACE  = 'CheckoutIntegration';
    const CARD_FORM_MODE    = 'cardTokenisation';

    /**
     * Create Payment Token
     *
     * @return array
     *
     * @version 20160203
     */
    public function getPaymentToken() {
        $Api        = CheckoutApi_Api::getApi(array('mode' => $this->getEndpointMode()));
        $amount     = $Api->valueToDecimal($this->_getQuote()->getGrandTotal(), $this->getCurrencyCode());
        $config     = $this->_getCharge($amount);

        try {
            $paymentTokenCharge = $Api->getPaymentToken($config);
        } catch (Exception $e) {
            Mage::log('Please make sure connection failures are properly logged. Action - Get Payment Token', null, $this->_code.'.log');
        }

        $paymentTokenReturn     = array(
            'success' => false,
            'token'   => '',
            'message' => ''
        );

        if($paymentTokenCharge->isValid()){
            $paymentToken                   = $paymentTokenCharge->getId();
            $paymentTokenReturn['token']    = $paymentToken ;
            $paymentTokenReturn['success']  = true;

            $paymentTokenReturn['customerEmail']    = $config['postedParam']['email'];
            $paymentTokenReturn['customerName']     = $config['postedParam']['customerName'];
            $paymentTokenReturn['value']            = $amount;
            $paymentTokenReturn['currency']         = $this->getCurrencyCode();

            Mage::getSingleton('checkout/session')->setPaymentToken($paymentToken);
        }else {
            if($paymentTokenCharge->getEventId()) {
                $eventCode = $paymentTokenCharge->getEventId();
            }else {
                $eventCode = $paymentTokenCharge->getErrorCode();
            }
            $paymentTokenReturn['message'] = Mage::helper('payment')->__( $paymentTokenCharge->getExceptionState()->getErrorMessage().
                ' ( '.$eventCode.')');

            Mage::logException($paymentTokenReturn['message']);
        }

        return $paymentTokenReturn;
    }

    /**
     * Return Quote from session
     *
     * @return mixed
     *
     * @version 20160202
     */
    private function _getQuote() {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Return true if is 3D
     *
     * @return bool
     *
     * @version 20160202
     */
    public function getIs3D() {
        return Mage::helper('chargepayment')->getConfigData($this->_code, 'is_3d');
    }

    /**
     * Return the timeout value for a request to the gateway.
     *
     * @return mixed
     *
     * @version 20160203
     */
    public function getTimeout() {
        return Mage::helper('chargepayment')->getConfigData($this->_code, 'timeout');
    }

    /**
     * Validate payment method information object
     *
     * @return Mage_Payment_Model_Abstract
     *
     * @version 20160203
     */
    public function validate() {
        return $this;
    }

    /**
     * For authorize
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     *
     * @version 20160204
     */
    public function authorize(Varien_Object $payment, $amount) {
        $requestData    = Mage::app()->getRequest()->getParam('payment');
        $cardToken      = !empty($requestData['checkout_card_token']) ? $requestData['checkout_card_token'] : NULL;
        $currentToken   = Mage::getSingleton('checkout/session')->getPaymentToken();
        $paymentToken   = $currentToken;
        $isDebug        = $this->isDebug();

        if (is_null($cardToken)) {
            Mage::throwException(Mage::helper('chargepayment')->__('Authorize action is not available.'));
            Mage::log('Empty Card Token', null, $this->_code.'.log');
        }

        if ($paymentToken !== $currentToken) {
            Mage::throwException(Mage::helper('chargepayment')->__('Authorize action is not available.'));
            Mage::log('Payment Tokens mismatch.', null, $this->_code.'.log');
        }

        $Api    = CheckoutApi_Api::getApi(array('mode' => $this->getEndpointMode()));
        $amount = $Api->valueToDecimal($this->_getQuote()->getGrandTotal(), $this->getCurrencyCode());
        $config = $this->_getCharge($amount);

        $config['postedParam']['trackId']   = $payment->getOrder()->getIncrementId();
        $config['postedParam']['cardToken'] = $cardToken;

        $autoCapture    = $this->_isAutoCapture();

        try {
            $result = $Api->createCharge($config);
        } catch (Exception $e) {
            Mage::log('Please make sure connection failures are properly logged. Action - Authorize', null, $this->_code.'.log');
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            Mage::log($result->toArray(), null, $this->_code.'.log');
        }

        if($result->isValid()) {
            if ($this->_responseValidation($result)) {
                /* Save Customer Credit Cart */
                $redirectUrl    = $result->getRedirectUrl();
                $entityId       = $result->getId();
                $session        = Mage::getSingleton('chargepayment/session_quote');

                /* is 3D payment */
                if ($redirectUrl && $entityId) {
                    $payment->setAdditionalInformation('payment_token', $entityId);
                    $payment->setAdditionalInformation('payment_token_url', $redirectUrl);

                    $session
                        ->setPaymentToken($entityId)
                        ->setIs3d(true)
                        ->setPaymentRedirectUrl($redirectUrl)
                        ->setEndpointMode($this->getEndpointMode())
                        ->setSecretKey($this->_getSecretKey())
                        ->setNewOrderStatus($this->getNewOrderStatus())
                    ;
                } else {
                    Mage::getModel('chargepayment/customerCard')->saveCard($payment, $result);

                    $payment->setTransactionId($entityId);
                    $payment->setIsTransactionClosed(0);

                    if ($autoCapture) {
                        $payment->setIsTransactionPending(true);
                    }

                    $session->setIs3d(false);
                }
            }
        } else {
            if ($isDebug) {
                /* Authorize processing error response. */
                $errors             = $result->toArray();

                if (!empty($errors['errorCode'])) {
                    $responseCode       = (int)$errors['errorCode'];
                    $responseMessage    = (string)$errors['message'];
                    $errorMessage       = "Error Code - {$responseCode}. Message - {$responseMessage}.";
                } else {
                    $errorMessage = Mage::helper('chargepayment')->__('Authorize action is not available.');
                }
            } else {
                $errorMessage = Mage::helper('chargepayment')->__('Authorize action is not available.');
            }

            Mage::throwException($errorMessage);
            Mage::log($result->printError(), null, $this->_code.'.log');
        }

        return $this;
    }

    /**
     * Return base data for charge
     *
     * @param null $amount
     * @return array
     *
     * @version 20160204
     */
    private function _getCharge($amount = null) {
        $secretKey  = $this->_getSecretKey();

        $billingAddress     = $this->_getQuote()->getBillingAddress();
        $shippingAddress    = $this->_getQuote()->getBillingAddress();
        $orderedItems       = $this->_getQuote()->getAllItems();
        $currencyDesc       = $this->getCurrencyCode();
        $amountCents        = $amount;
        $chargeMode         = $this->getIs3D();

        $street = Mage::helper('customer/address')
            ->convertStreetLines($shippingAddress->getStreet(), 2);
        $shippingAddressConfig = array(
            'addressLine1'       => $street[0],
            'addressLine2'       => $street[1],
            'postcode'           => $shippingAddress->getPostcode(),
            'country'            => $shippingAddress->getCountry(),
            'city'               => $shippingAddress->getCity(),
            'phone'              => array('number' => $shippingAddress->getTelephone())
        );

        $products = array();

        foreach ($orderedItems as $item ) {
            $product        = Mage::getModel('catalog/product')->load($item->getProductId());
            $productImage   = $product->getImage();

            $products[] = array (
                'name'       => $item->getName(),
                'sku'        => $item->getSku(),
                'price'      => $item->getPrice(),
                'quantity'   => $item->getQty(),
                'image'      => $productImage != 'no_selection' && !is_null($productImage) ? Mage::helper('catalog/image')->init($product , 'image')->__toString() : '',
            );
        }

        $config                     = array();
        $config['authorization']    = $secretKey;

        $config['postedParam'] = array (
            'trackId'           => NULL,
            'customerName'      => $billingAddress->getName(),
            'email'             => $billingAddress->getEmail(),
            'value'             => $amountCents,
            'chargeMode'        => $chargeMode,
            'currency'          => $currencyDesc,
            'shippingDetails'   => $shippingAddressConfig,
            'products'          => $products,
            'customerIp'        => Mage::helper('core/http')->getRemoteAddr(),
            'metadata'          => array(
                'server'  => Mage::helper('core/http')->getHttpUserAgent(),
                'quoteId' => $this->_getQuote()->getId()
            )
        );

        $autoCapture = $this->_isAutoCapture();

        $config['postedParam']['autoCapture']  = $autoCapture ? CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE : CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH;;
        $config['postedParam']['autoCapTime']  = self::AUTO_CAPTURE_TIME;

        return $config;
    }
}