define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/redirect-on-success',
    'mage/url'
], function (Component, redirectOnSuccessAction, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'NakoPay_Payments/payment/nakopay',
            redirectAfterPlaceOrder: false
        },

        getCode: function () {
            return 'nakopay';
        },

        afterPlaceOrder: function () {
            window.location.replace(urlBuilder.build('nakopay/payment/redirect'));
        },

        getTitle: function () {
            return window.checkoutConfig.payment.nakopay
                ? window.checkoutConfig.payment.nakopay.title
                : 'Pay with Bitcoin & Crypto';
        },

        getDescription: function () {
            return window.checkoutConfig.payment.nakopay
                ? window.checkoutConfig.payment.nakopay.description
                : '';
        }
    });
});
