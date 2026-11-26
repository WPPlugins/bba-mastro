jQuery(function ($) {
    'use strict';

    const RATES_CACHE_KEY = 'bba_last_rates';
    const RATES_CACHE_TTL = 10 * 60 * 1000;
    const QUOTE_DEBOUNCE = 450;

    let pendingQuoteRequest = null;
    let quoteTimer = null;
    let lastQuoteKey = '';

    function getAjaxUrl() {
        if (window.bbamastro_cart && bbamastro_cart.ajax_url) {
            return bbamastro_cart.ajax_url;
        }
        return (window.wc_checkout_params && wc_checkout_params.ajax_url) ||
            (window.wc_cart_params && wc_cart_params.ajax_url) || '';
    }

    function getField(selector) {
        return $(selector).val() || '';
    }

    function getDestination() {
        return {
            country: getField('#shipping_country') || getField('#billing_country'),
            state: getField('#shipping_state') || getField('#billing_state'),
            postcode: getField('#shipping_postcode') || getField('#billing_postcode'),
            city: getField('#shipping_city') || getField('#billing_city'),
            address: getField('#shipping_address_1') || getField('#billing_address_1')
        };
    }

    function getResidentialValue() {
        const type = localStorage.getItem('bba_delivery_type');
        if (type === 'residential') return true;
        if (type === 'commercial') return false;
        return $('input[name="residential"]').is(':checked');
    }

    function getTailgateValue() {
        return $('input[name="tailgate"]').is(':checked');
    }

    function getInsuranceValue() {
        return $('input[name="shipping_insurance"]').is(':checked');
    }

    function buildQuoteKey(destination) {
        return JSON.stringify({
            destination: destination,
            residential: !!getResidentialValue(),
            tailgate: !!getTailgateValue()
        });
    }

    function scheduleIndependentQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(requestIndependentQuote, QUOTE_DEBOUNCE);
    }

    function requestIndependentQuote() {
        const destination = getDestination();
        const ajaxUrl = getAjaxUrl();

        if (!ajaxUrl) return;

        if (!destination.country || !destination.city || !destination.postcode) {
            return;
        }

        const quoteKey = buildQuoteKey(destination);
        if (quoteKey === lastQuoteKey) return;
        lastQuoteKey = quoteKey;

        if (pendingQuoteRequest) {
            pendingQuoteRequest.abort();
        }

        toggleLoadingIndicator(true);

        pendingQuoteRequest = $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: {
                action: 'bba_independent_quote',
                nonce: window.bbamastro_cart ? bbamastro_cart.nonce : '',
                country: destination.country,
                state: destination.state,
                postcode: destination.postcode,
                city: destination.city,
                address: destination.address,
                residential: getResidentialValue(),
                tailgate: getTailgateValue(),
                insurance: getInsuranceValue()
            }
        }).done(function (response) {
            if (!response || !response.success) {
                return;
            }

            const data = response.data || {};
            const methods = Array.isArray(data.rates) ? data.rates : [];
            const timings = Array.isArray(data.timings) ? data.timings : [];

            paintShippingMethods(methods);
            renderApiTimings(timings);
            cacheRates(methods);
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                console.error('BBA independent shipping quote failed.', xhr);
            }
        }).always(function () {
            pendingQuoteRequest = null;
            toggleLoadingIndicator(false);
        });
    }

    function paintShippingMethods(methods) {
        const element = $('#shipping_method');
        if (!element.length) return;

        element.empty();

        if (!methods.length) {
            element.append('<li class="bba-no-shipping-rates">No shipping rates available.</li>');
            return;
        }

        methods.forEach(function (method, index) {
            const id = 'shipping_method_0_' + index;
            const value = method.shipping_id || method.id || '';
            const label = method.label || method.name || 'Shipping';
            const cost = method.cost !== undefined ? method.cost : '';

            const input = $('<input>', {
                type: 'radio',
                name: 'shipping_method[0]',
                id: id,
                value: value,
                class: 'shipping_method',
                checked: index === 0
            });

            const labelEl = $('<label>', { for: id }).append(
                document.createTextNode(label + ': ')
            ).append(
                $('<span>', {
                    class: 'woocommerce-Price-amount amount'
                }).append(
                    $('<bdi>').text('$' + cost)
                )
            );

            element.append($('<li>').append(input, labelEl));
        });
    }

    function renderApiTimings(timings) {
        const container = $('.bbam-api-timing td');
        if (!container.length) return;

        if (!timings.length) {
            container.empty();
            return;
        }

        let total = 0;
        let html = '';

        timings.forEach(function (timing) {
            const ms = parseFloat(timing.ms) || 0;
            total += ms;
            html += '<div class="bbam-api-timing__row"><span>' +
                $('<div>').text(timing.label || '').html() +
                '</span><span>' + ms.toFixed(1) + ' ms</span></div>';
        });

        html += '<div class="bbam-api-timing__row bbam-api-timing__total"><span>Total</span><span>' +
            total.toFixed(1) + ' ms</span></div>';

        container.html(html);
    }

    function cacheRates(methods) {
        if (!methods || !methods.length) return;
        try {
            localStorage.setItem(RATES_CACHE_KEY, JSON.stringify({
                ts: Date.now(),
                methods: methods
            }));
        } catch (e) {}
    }

    function paintCachedRatesImmediately() {
        const element = $('#shipping_method');
        if (!element.length || element.find('li').length) return false;

        let cached;
        try {
            cached = JSON.parse(localStorage.getItem(RATES_CACHE_KEY) || 'null');
        } catch (e) {
            cached = null;
        }

        if (!cached || !cached.methods || (Date.now() - cached.ts) > RATES_CACHE_TTL) {
            return false;
        }

        paintShippingMethods(cached.methods);
        return true;
    }

    function toggleLoadingIndicator(show) {
        $('#bba-loading-overlay').toggle(!!show);
        $('#checkout-loader').toggle(!!show);
    }

    $(document).on('change', '#bba-cart-residential, #bba-cart-commercial, #bba-cart-tailgate, #bba-residential', function () {
        const residential = $('#bba-cart-residential').length ? $('#bba-cart-residential').is(':checked') : null;
        const commercial = $('#bba-cart-commercial').length ? $('#bba-cart-commercial').is(':checked') : null;

        if (residential !== null) {
            $('#bba-cart-commercial').prop('checked', !residential);
            localStorage.setItem('bba_delivery_type', residential ? 'residential' : 'commercial');
        } else if (commercial !== null) {
            $('#bba-cart-residential').prop('checked', !commercial);
            localStorage.setItem('bba_delivery_type', commercial ? 'commercial' : 'residential');
        }

        lastQuoteKey = '';
        scheduleIndependentQuote();
    });

    $(document).on('change', '#bba-checkout-residential, #bba-checkout-commercial, #bba-checkout-tailgate', function () {
        if ($(this).attr('id') === 'bba-checkout-residential') {
            const checked = $(this).is(':checked');
            $('#bba-checkout-commercial').prop('checked', !checked);
            localStorage.setItem('bba_delivery_type', checked ? 'residential' : 'commercial');
        } else if ($(this).attr('id') === 'bba-checkout-commercial') {
            const checked = $(this).is(':checked');
            $('#bba-checkout-residential').prop('checked', !checked);
            localStorage.setItem('bba_delivery_type', checked ? 'commercial' : 'residential');
        }

        lastQuoteKey = '';
        scheduleIndependentQuote();
    });

    
    $(document).on('change input',
        '#shipping_country, #shipping_state, #shipping_city, #shipping_postcode, #shipping_address_1,' +
        '#billing_country, #billing_state, #billing_city, #billing_postcode, #billing_address_1',
        function () {
            lastQuoteKey = '';
            scheduleIndependentQuote();
        }
    );

    
    $(document.body).on('updated_checkout', function () {
        scheduleIndependentQuote();
    });

    paintCachedRatesImmediately();
    scheduleIndependentQuote();
});
