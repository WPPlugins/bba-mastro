/**
 * BBA Mastro.
 */
jQuery(function($) {
    var value = function(target, val, triggerChange) {
        $(target).select2("val", val, triggerChange);
    };
    var clear = function(target) {
        $(target).select2("val", undefined);
    };
    var enable = function(target) {
        $(target).select2("enable", true);
    };
    var disable = function(target) {
        $(target).select2("enable", false);
    };
    var identifier = function(toStr, id) {
        var str = "woocommerce_bbamastro_rules_warehouse_";
        if (toStr !== "") {
            str += toStr;
        }
        if (id) {
            str = "#" + str;
        }
        return str;
    };
    var onChangeCountry = function(e) {
        if (e.val) {
            var country = e.currentTarget.id;
            var postcode = identifier("postcode", true);
            if (selected[country] && selected[country] != e.val) {
                clear(postcode);
            }
            enable(postcode);
            selected[country] = e.val;
        }
    };
    var onChangePostcode = function(e) {
        if (e.val) {
            var postcode = e.currentTarget.id;
            var city = identifier("city");
            var state = identifier("state");
            var country = identifier("country");
            var el = identifier("city", true);
            $.ajax(ajaxurl, {
                method: "GET",
                dataType: "json",
                cache: true,
                data: {
                    city: "",
                    country: selected[country],
                    postcode: e.val,
                    page: 1,
                    action: "bbamastro_search_city"
                },
                success: function(response) {
                    local_data[city] = response.city;
                    local_data[state] = response.state;
                    enable(el);
                    if (local_data[city].length == 1) {
                        value(el, local_data[city][0].id, true);
                    }
                }
            });
            if (selected[postcode] && selected[postcode] != e.val) {
                clear(el);
            }
            selected[postcode] = e.val;
        }
    };
    var onChangeCity = function(e) {
        if (e.val) {
            var city = e.currentTarget.id;
            var state = identifier("state");
            var el = identifier("state", true);
            if (selected[city] && selected[city] != e.val) {
                clear(el);
            }
            selected[city] = e.val;
            if (local_data[state].length > 0) {
                enable(el);
                if (local_data[state].length == 1) {
                    value(el, local_data[state][0].id, false);
                }
            }
        }
    };
    var onClearingPostcode = function(e) {
        var city = identifier("city", true);
        clear(city);
    };
    var onClearingCity = function(e) {
        var city = identifier("city", true);
        var state = identifier("state", true);
        disable(city);
        clear(state);
    };
    var onClearingState = function(e) {
        var state = identifier("state", true);
        disable(state);
    };
    var selected = {},
        local_data = {};
    var country_options = {
        placeholder: "Choose a country",
        data: bbamastro.countries
    };
    var postcode_options = {
        placeholder: "Choose a postcode",
        ajax: {
            url: ajaxurl,
            dataType: "json",
            cache: true,
            data: function() {},
            results: function(data, page) {
                var more = (page * 50) < data.total_count;
                return {
                    results: data.items,
                    more: more
                };
            }
        },
        initSelection: function(element, callback) {
            var value = $(element).val();
            if (value != "") {
                callback({
                    id: value,
                    text: value
                });
            }
        }
    };
    var city_options = {
        placeholder: "Choose a city/suburb",
        query: function(query) {
            var t = query.term,
                data = {
                    results: local_data[query.element.attr("id")]
                },
                filtered = {
                    results: []
                },
                text, process;
            if (t === "") {
                query.callback(data);
                return;
            }
            text = function(item) {
                return "" + item.text;
            };
            process = function(datum, collection) {
                datum = datum[0];
                if (query.matcher(t, text(datum), datum)) {
                    collection.push(datum);
                }
            };
            $(data.results).each2(function(i, datum) {
                process(datum, filtered.results);
            });
            query.callback(filtered);
        },
        initSelection: function(element, callback) {
            var value = $(element).val();
            if (value != "") {
                callback({
                    id: value,
                    text: value
                });
            }
        }
    }
    var state_options = $.extend({}, city_options, {
        placeholder: "Choose a state"
    });
    var api_options = {
        placeholder: "Choose an API Url",
        data: [{
            id: "https://api-dev.bbamastro.com",
            text: "https://api-dev.bbamastro.com"
        }, {
            id: "https://api.bbamastro.com",
            text: "https://api.bbamastro.com"
        }, {
            id: "https://api-demo.bbamastro.com",
            text: "https://api-demo.bbamastro.com"
        }, {
            id: "https://api-staging.bbamastro.com",
            text: "https://api-staging.bbamastro.com"
        }]
    };
    var auth_api_options = {
        placeholder: "Choose an Auth API Url",
        data: [{
            id: "https://auth-dev.bbamastro.com/oauth/token",
            text: "https://auth-dev.bbamastro.com/oauth/token"
        }, {
            id: "https://auth.bbamastro.com/oauth/token",
            text: "https://auth.bbamastro.com/oauth/token"
        }, {
            id: "https://auth-demo.bbamastro.com/oauth/token",
            text: "https://auth-demo.bbamastro.com/oauth/token"
        }, {
            id: "https://auth-staging.bbamastro.com/oauth/token",
            text: "https://auth-staging.bbamastro.com/oauth/token"
        }]
    };
    /**
     * Settings page.
     */
    var id_country_setting = ["#woocommerce_bbamastro_rules_warehouse_country"];
    var id_postcode_setting = ["#woocommerce_bbamastro_rules_warehouse_postcode"];
    var id_city_setting = ["#woocommerce_bbamastro_rules_warehouse_city"];
    var id_api_url_setting = ["#woocommerce_bbamastro_rules_api_url"];
    
    // API Url Dropdown
    $.each(id_api_url_setting, function(index, selector) {
        var authUrl = "#woocommerce_bbamastro_rules_auth_url";

        $(selector).select2(api_options).on("select2:select", function(e) {
            var data = e.params.data;
            switch (data.id) {
                case 'https://api-dev.bbamastro.com':
                    $(authUrl).val("https://auth-dev.bbamastro.com/oauth/token");
                    break;
                case 'https://api.bbamastro.com':
                    $(authUrl).val("https://auth.bbamastro.com/oauth/token");
                    break;
                case 'https://api-demo.bbamastro.com':
                    $(authUrl).val("https://auth-demo.bbamastro.com/oauth/token");
                    break;
                case 'https://api-staging.bbamastro.com':
                    $(authUrl).val("https://auth-staging.bbamastro.com/oauth/token");
                    break;
            }
        });

        // Disabled Auth Url Field 
        $(authUrl).prop('readonly', true);
    });

    // Country
    $.each(id_country_setting, function(index, selector) {
        $(selector).select2(country_options).on("change", onChangeCountry);
        selected[selector.substr(1)] = $(selector).val();
    });
    // Postcode
    $.each(id_postcode_setting, function(index, selector) {
    });
    // City
    $.each(id_city_setting, function(index, selector) {
        var postcode = identifier("postcode");
    });
});