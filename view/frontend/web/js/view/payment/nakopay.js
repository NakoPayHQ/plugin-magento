define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'nakopay',
        component: 'NakoPay_Payments/js/view/payment/method-renderer/nakopay-method'
    });

    return Component.extend({});
});
