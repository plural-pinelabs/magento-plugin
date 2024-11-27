define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/modal/alert'
    ],
    function ($, quote, customerData, customer, fullScreenLoader, alert) {
        'use strict';

        return function (messageContainer) {
            var serviceUrl, email;

            // Get customer email based on login status
            if (!customer.isLoggedIn()) {
                email = quote.guestEmail;
            } else {
                email = customer.customerData.email;
            }

            // Construct the service URL
            serviceUrl = window.checkoutConfig.payment.pinepg.redirectUrl + '?email=' + encodeURIComponent(email);
            fullScreenLoader.startLoader();

            // Send AJAX request
            $.ajax({
                url: serviceUrl,
                type: 'POST',
                context: this,
                data: { isAjax: 1 },
                success: function (response) {
                    fullScreenLoader.stopLoader();

                    if ($.type(response) === 'object' && !$.isEmptyObject(response) && response.url) {
                        console.log('Redirecting to:', response.url);

                        // Perform the redirect
                        window.location.href = response.url;
                    } else {
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again.')
                        });
                    }
                },
                error: function () {
                    fullScreenLoader.stopLoader();
                    alert({
                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                    });
                }
            });
        };
    }
);
