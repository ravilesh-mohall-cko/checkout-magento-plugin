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

    protected $_formBlockType = 'chargepayment/form_checkoutApiJs';
    protected $_infoBlockType = 'chargepayment/info_checkoutApiJs';

    const RENDER_MODE           = 2;
    const RENDER_NAMESPACE      = 'CheckoutIntegration';
    const CARD_FORM_MODE        = 'cardTokenisation';

    const PAYMENT_MODE_MIXED            = 'mixed';
    const PAYMENT_MODE_CARD             = 'card';
    const PAYMENT_MODE_LOCAL_PAYMENT    = 'localpayment';

    /**
     * Create Payment Token
     *
     * @return array
     *
     * @version 20160203
     */
    public function getPaymentToken() {
        $Api                = CheckoutApi_Api::getApi(array('mode' => $this->getEndpointMode()));
        $isCurrentCurrency  = $this->getIsUseCurrentCurrency();
        $price              = $isCurrentCurrency ? $this->_getQuote()->getGrandTotal() : $this->_getQuote()->getBaseGrandTotal();
        $priceCode          = $isCurrentCurrency ? $this->getCurrencyCode() : Mage::app()->getStore()->getBaseCurrencyCode();

        $amount     = $Api->valueToDecimal($price, $priceCode);
        $config     = $this->_getCharge($amount);

        $paymentTokenCharge = $Api->getPaymentToken($config);

        if ($Api->getExceptionState()->hasError()) {
            Mage::log($Api->getExceptionState()->getErrorMessage(), null, $this->_code.'.log');
            Mage::log($Api->getExceptionState(), null, $this->_code.'.log');
            $errorMessage = Mage::helper('chargepayment')->__('Your payment was not completed.'. $Api->getExceptionState()->getErrorMessage().' and try again or contact customer support.');
            Mage::throwException($errorMessage);
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
            $paymentTokenReturn['currency']         = $priceCode;

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
     * Get Public Shared Key
     *
     * @return mixed
     *
     * @version 20160407
     */
    public function getPublicKeyWebHook() {
        return Mage::helper('chargepayment')->getConfigData($this->_code, 'publickey_web');
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
        $requestData        = Mage::app()->getRequest()->getParam('payment');
        $session            = Mage::getSingleton('chargepayment/session_quote');
        $currentToken       = Mage::getSingleton('checkout/session')->getPaymentToken();
        $isCurrentCurrency  = $this->getIsUseCurrentCurrency();

        /* Local Payment */
        $lpRedirectUrl  = !empty($requestData['lp_redirect_url']) ? $requestData['lp_redirect_url'] : NULL;
        $lpName         = !empty($requestData['lp_name']) ? $requestData['lp_name'] : NULL;
        $isLocalPayment = $this->isLocalPayment();

        if ($isLocalPayment && !is_null($lpRedirectUrl) && !is_null($lpName)) {
            $Api            = CheckoutApi_Api::getApi(array('mode' => $this->getEndpointMode()));
            $verifyParams   = array('paymentToken' => $currentToken, 'authorization' => $this->_getSecretKey());
            $response       = $Api->verifyChargePaymentToken($verifyParams);

            if (is_object($response) && method_exists($response, 'toArray')) {
                Mage::log($response->toArray(), null, $this->_code.'.log');
            }

            if ($Api->getExceptionState()->hasError()) {
                Mage::log($Api->getExceptionState()->getErrorMessage(), null, $this->_code.'.log');
                Mage::log($Api->getExceptionState(), null, $this->_code.'.log');
                $errorMessage = Mage::helper('chargepayment')->__('Your payment was not completed.'. $Api->getExceptionState()->getErrorMessage().' and try again or contact customer support.');
                Mage::throwException($errorMessage);
            }

            if(!$response->isValid() || !$this->_responseValidation($response)) {
                return $this;
            }

            $Api->updateTrackId($response, $payment->getOrder()->getIncrementId());

            $session->addCheckoutLocalPaymentToken($currentToken);

            $session
                ->setLpRedirectUrl($lpRedirectUrl)
                ->setIsLocalPayment(true)
                ->setLpName($lpName);

            $payment->setTransactionId($response->getId());
            $payment->setIsTransactionClosed(0);
            $payment->setAdditionalInformation('use_current_currency', $isCurrentCurrency);
            $payment->setIsTransactionPending(true);

            return $this;
        }

        /* Normal Payment */
        $cardToken      = !empty($requestData['checkout_card_token']) ? $requestData['checkout_card_token'] : NULL;
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

        $price              = $isCurrentCurrency ? $this->_getQuote()->getGrandTotal() : $this->_getQuote()->getBaseGrandTotal();
        $priceCode          = $isCurrentCurrency ? $this->getCurrencyCode() : Mage::app()->getStore()->getBaseCurrencyCode();

        $Api    = CheckoutApi_Api::getApi(array('mode' => $this->getEndpointMode()));
        $amount = $Api->valueToDecimal($price, $priceCode);
        $config = $this->_getCharge($amount);

        $config['postedParam']['trackId']   = $payment->getOrder()->getIncrementId();
        $config['postedParam']['cardToken'] = $cardToken;

        $autoCapture    = $this->_isAutoCapture();
        $result         = $Api->createCharge($config);

        if (is_object($result) && method_exists($result, 'toArray')) {
            Mage::log($result->toArray(), null, $this->_code.'.log');
        }

        if ($Api->getExceptionState()->hasError()) {
            Mage::log($Api->getExceptionState()->getErrorMessage(), null, $this->_code.'.log');
            Mage::log($Api->getExceptionState(), null, $this->_code.'.log');
            $errorMessage = Mage::helper('chargepayment')->__('Your payment was not completed.'. $Api->getExceptionState()->getErrorMessage().' and try again or contact customer support.');
            Mage::throwException($errorMessage);
        }

        if($result->isValid()) {
            if ($this->_responseValidation($result)) {
                $redirectUrl    = $result->getRedirectUrl();
                $entityId       = $result->getId();

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
                    $payment->setTransactionId($entityId);
                    $payment->setIsTransactionClosed(0);
                    $payment->setAdditionalInformation('use_current_currency', $isCurrentCurrency);

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
        $secretKey          = $this->_getSecretKey();
        $isCurrentCurrency  = $this->getIsUseCurrentCurrency();

        $billingAddress     = $this->_getQuote()->getBillingAddress();
        $shippingAddress    = $this->_getQuote()->getBillingAddress();
        $orderedItems       = $this->_getQuote()->getAllItems();
        $currencyDesc       = $isCurrentCurrency ? $this->getCurrencyCode() : Mage::app()->getStore()->getBaseCurrencyCode();
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
                'server'            => Mage::helper('core/http')->getHttpUserAgent(),
                'quoteId'           => $this->_getQuote()->getId(),
                'magento_version'   => Mage::getVersion(),
                'plugin_version'    => Mage::helper('chargepayment')->getExtensionVersion(),
                'lib_version'       => CheckoutApi_Client_Constant::LIB_VERSION,
                'integration_type'  => 'JS',
                'time'              => Mage::getModel('core/date')->date('Y-m-d H:i:s')
            )
        );

        $autoCapture = $this->_isAutoCapture();

        $config['postedParam']['autoCapture']  = $autoCapture ? CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE : CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH;;
        $config['postedParam']['autoCapTime']  = self::AUTO_CAPTURE_TIME;

        return $config;
    }

    /**
     * Return true if local payment
     *
     * @return bool
     *
     * @version 20160425
     */
    public function isLocalPayment() {
        $paymentMode = Mage::helper('chargepayment')->getConfigData($this->_code, 'payment_mode');

        return $paymentMode === self::PAYMENT_MODE_MIXED
            || $paymentMode === self::PAYMENT_MODE_LOCAL_PAYMENT ? true : false;
    }
}