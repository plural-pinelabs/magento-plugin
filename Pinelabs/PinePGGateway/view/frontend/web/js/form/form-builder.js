define(
    [
        'jquery',
        'underscore',
        'mage/template'
    ],
    function ($, _, mageTemplate) {
        'use strict';

        return {
            build: function (formData) {
                // Add token to the action URL if provided
                var actionUrl = formData.action;

                if (formData.fields && formData.fields.token) {
                    actionUrl += (actionUrl.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(formData.fields.token);
                }

                var formTmpl = mageTemplate('<form action="<%= data.action %>" id="edgepg_payment_form"' +
                    ' method="GET" hidden>' +
                    '</form>');

                $("input[name=form_key]").remove();

                // Append the form with the action URL and submit
                return $(formTmpl({
                    data: {
                        action: actionUrl
                    }
                })).appendTo($('[data-container="body"]')).submit();
            }
        };
    }
);
