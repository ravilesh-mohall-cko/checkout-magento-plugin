var checkoutApi = Class.create();
checkoutApi.prototype = {
    initialize : function(methodCode, controller, saveOrderUrl, baseSaveOrderUrl) {
        this.code               = methodCode;
        this.controller         = controller;
        this.saveOrderUrl       = saveOrderUrl;
        this.baseSaveOrderUrl   = baseSaveOrderUrl;
        this.phpMethodCode      = 'checkoutapicard';
        this.jsMethodCode       = 'checkoutapijs';
        this.kitMethodCode      = 'checkoutapikit';
        this.preparePayment();
    },
    preparePayment: function() {
        switch (this.controller) {
            case 'onepage':
                this.prepareSubmit();
                break;
            case 'sales_order_create':
            case 'sales_order_edit':
                this.prepareAdminSubmit();
                break;
        }
    },
    prepareSubmit: function() {
        var button = $('review-buttons-container').down('button');
        button.writeAttribute('onclick', '');
        button.stopObserving('click');
        switch (this.code) {
            case this.phpMethodCode:
                button.observe('click', function() {
                    this.saveOrderSubmit();
                }.bind(this));
                break;
            case this.jsMethodCode:
                button.observe('click', function() {
                    this.checkoutApiFrame();
                }.bind(this));
                break;
            case this.kitMethodCode:
                button.observe('click', function() {
                    this.checkoutKit();
                }.bind(this));
                break;
        }
    },
    prepareAdminSubmit: function() {
        var paymentMethods = $('edit_form').getInputs('radio','payment[method]');
        for ( var i = 0; i < paymentMethods.length; i++) {
            paymentMethods[i].observe('click', function() {
                this.changeAdminOrderForm();
            }.bind(this));
        }

        this.changeAdminOrderForm();
    },
    getPaymentMethodChecked: function() {
        var paymentMethodChecked = $('edit_form').getInputs('radio','payment[method]').find(function(radio) {
            return radio.checked;
        });

        return paymentMethodChecked;
    },
    changeAdminOrderForm: function() {
        var paymentMethodChecked = this.getPaymentMethodChecked();

        if (typeof paymentMethodChecked === 'undefined') {
            return;
        }

        if (paymentMethodChecked.value == this.code) {
            $('edit_form').writeAttribute('action', this.saveOrderUrl);

            if (typeof directPostModel !== 'undefined') {
                directPostModel.nativeAction = this.saveOrderUrl;
            }
        } else {
            $('edit_form').writeAttribute('action', this.baseSaveOrderUrl);

            if (typeof directPostModel !== 'undefined') {
                directPostModel.nativeAction = this.baseSaveOrderUrl;
            }
        }
    },
    checkoutApiFrame: function() {
        if (this.agreementIsValid()) {
            CKOAPIJS.open();

            if (CKOAPIJS.isMobile()) {
                $('checkout-api-js-hover').show();
            }
        } else {
            alert('Please agree to all the terms and conditions before placing the order.');
            return;
        }
    },
    checkoutKit: function() {
        var self = this;

        if (this.agreementIsValid()) {
            CheckoutKit.setCustomerEmail(window.CKOConfig.customerEmail);
            CheckoutKit.setPublicKey(window.CKOConfig.publicKey);

            CheckoutKit.createCardToken({
                    number: $$('.cardNumber')[0].value,
                    name : $$('.chName')[0].value,
                    expiryMonth: $$('.expiryMonth')[0].value,
                    expiryYear: $$('.expiryYear')[0].value,
                    cvv: $$('.cvv')[0].value
                }, function(response){
                    if (response.type === 'error') {
                        alert('Your payment was not completed. Please check you card details and try again or contact customer support.');
                        return;
                    }

                    if (response.id) {
                        $('cko-kit-card-token').value = response.id;

                        self.saveOrderSubmit();
                    } else {
                        alert('Your payment was not completed. Please check you card details and try again or contact customer support.');
                        return;
                    }
                }
            );
        } else {
            alert('Please agree to all the terms and conditions before placing the order.');
            return;
        }
    },
    agreementIsValid: function() {
        var isValid = true;

        $$('.checkout-agreements input[type="checkbox"]').each(
            function(Element) {
                if (!Element.checked) {
                    isValid = false;
                }
            }
        );

        return isValid;
    },
    saveOrderSubmit: function() {
        if (this.agreementIsValid()) {
            review.save();
        } else {
            alert('Please agree to all the terms and conditions before placing the order.');
            return;
        }
    }
};