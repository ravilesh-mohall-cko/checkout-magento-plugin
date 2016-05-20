<?php

/**
 * Class for Checkout Kit payment method
 *
 * Class CheckoutApi_ChargePayment_Model_CreditCardKit
 *
 * @version 20160502
 */
class CheckoutApi_ChargePayment_Model_CreditCardKit extends CheckoutApi_ChargePayment_Model_Checkout
{
    protected $_code            = CheckoutApi_ChargePayment_Helper_Data::CODE_CREDIT_CARD_KIT;
    protected $_canUseInternal  = false;

    protected $_formBlockType = 'chargepayment/form_checkoutApiKit';
    protected $_infoBlockType = 'chargepayment/info_checkoutApiKit';

    const RENDER_MODE           = 2;

    /**
     * Return Quote from session
     *
     * @return mixed
     *
     * @version 20160505
     */
    protected function _getQuote() {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Get Public Shared Key
     *
     * @return mixed
     *
     * @version 20160505
     */
    public function getPublicKeyWebHook() {
        return Mage::helper('chargepayment')->getConfigData($this->_code, 'publickey_web');
    }

    /**
     * Return true if is 3D
     *
     * @return bool
     *
     * @version 20160505
     */
    public function getIs3D() {
        return Mage::helper('chargepayment')->getConfigData($this->_code, 'is_3d');
    }

    /**
     * Return the timeout value for a request to the gateway.
     *
     * @return mixed
     *
     * @version 20160505
     */
    public function getTimeout() {
        return Mage::helper('chargepayment')->getConfigData($this->_code, 'timeout');
    }

    /**
     * Validate payment method information object
     *
     * @return Mage_Payment_Model_Abstract
     *
     * @version 20160505
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
     * @version 20160505
     */
    public function authorize(Varien_Object $payment, $amount) {
        $requestData        = Mage::app()->getRequest()->getParam('payment');
        $session            = Mage::getSingleton('chargepayment/session_quote');
        $isCurrentCurrency  = $this->getIsUseCurrentCurrency();

        /* Normal Payment */
        $cardToken      = !empty($requestData['checkout_kit_card_token']) ? $requestData['checkout_kit_card_token'] : NULL;
        $isDebug        = $this->isDebug();

        if (is_null($cardToken)) {
            Mage::throwException(Mage::helper('chargepayment')->__('Authorize action is not available.'));
            Mage::log('Empty Card Token', null, $this->_code.'.log');
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
            Mage::log($Api->getExceptionState(), null, $this->_code.'.log');
            $errorMessage = Mage::helper('chargepayment')->__('Your payment was not completed.'. $Api->getExceptionState()->getErrorMessage().' and try again or contact customer support.');
            Mage::throwException($errorMessage);
        }

        if($result->isValid()) {
            if ($this->_responseValidation($result)) {
                /* Save Customer Credit Cart */
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
     * @version 20160505
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
                'server'  => Mage::helper('core/http')->getHttpUserAgent(),
                'quoteId' => $this->_getQuote()->getId(),
                'magento_version'   => Mage::getVersion(),
                'plugin_version'    => Mage::helper('chargepayment')->getExtensionVersion(),
                'lib_version'       => CheckoutApi_Client_Constant::LIB_VERSION,
                'integration_type'  => 'KIT',
                'time'              => Mage::getModel('core/date')->date('Y-m-d H:i:s')
            )
        );

        $autoCapture = $this->_isAutoCapture();

        $config['postedParam']['autoCapture']  = $autoCapture ? CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE : CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH;;
        $config['postedParam']['autoCapTime']  = self::AUTO_CAPTURE_TIME;

        return $config;
    }
}