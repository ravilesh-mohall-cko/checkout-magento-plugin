jQuery(document).ready(function(){
    if (!window.hasOwnProperty('jsCheckoutApi')) {
        return false;
    }

    var method = window.jsCheckoutApi.method;

    switch (method) {
        case 'checkoutapicard':
            break;
        case 'checkoutapijs':
            checkoutJs();
            break;
        case 'checkoutapikit':
            checkoutKit();
            break;
    }

    function checkoutJs() {
        $('checkout-api-default-hover').show();

        setTimeout(function(){
            if (CKOAPIJS !== undefined) {
                if (CKOAPIJS.isMobile()) {
                    $('checkout-api-js-hover').show();
                }

                CKOAPIJS.open();
            }
        }, 2000);
    }

    function checkoutKit() {
        $('checkout-api-js-hover').show();

        setTimeout(function(){
            if (CheckoutKit !== undefined) {
                CheckoutKit.setCustomerEmail(window.jsCheckoutApi.email);
                CheckoutKit.setPublicKey(window.jsCheckoutApi.kit_public_key);

                $$('.cardNumber')[0].value  = window.jsCheckoutApi.kit_number;
                $$('.chName')[0].value      = window.jsCheckoutApi.kit_name;
                $$('.expiryMonth')[0].value = window.jsCheckoutApi.kit_month;
                $$('.expiryYear')[0].value  = window.jsCheckoutApi.kit_year;
                $$('.chCvv')[0].value       = window.jsCheckoutApi.kit_cvv;

                CheckoutKit.createCardToken({
                        number:         window.jsCheckoutApi.kit_number,
                        name:           window.jsCheckoutApi.kit_name,
                        expiryMonth:    window.jsCheckoutApi.kit_month,
                        expiryYear:     window.jsCheckoutApi.kit_year,
                        cvv:            window.jsCheckoutApi.kit_cvv
                    }, function(response){
                        if (response.type === 'error') {
                            alert('Your payment was not completed. Please check you card details and try again or contact customer support.');
                            return;
                        }

                        if (response.id) {
                            $('cko-kit-card-token').value = response.id;

                            window.checkoutApiSubmitOrder();

                            $('checkout-api-default-hover').hide();
                            $('checkout-api-js-hover').hide();
                        } else {
                            alert('Your payment was not completed. Please check you card details and try again or contact customer support.');
                            return;
                        }
                    }
                );
            }
        }, 2000);
    }
});

window.checkoutApiSubmitOrder = function() {
    if (jQuery('#aw-onestepcheckout-place-order-button').length > 0) {
        jQuery('#aw-onestepcheckout-place-order-button').trigger('click');
    }

    if (jQuery('#onestepcheckout-button-place-order').length > 0) {
        jQuery('#onestepcheckout-button-place-order').trigger('click');
    }

    if (jQuery('#onestepcheckout-place-order').length > 0) {
        jQuery('#onestepcheckout-place-order').trigger('click');
    }

    if (typeof review !== 'undefined' && review) {
        review.save();
    }
}