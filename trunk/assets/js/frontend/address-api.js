/**
 * BBA Mastro.
 */
jQuery( function( $ ) {

    var value = function ( target, val, triggerChange ) {
        $( target ).select2( "val", val, triggerChange );
    };

    var clear = function ( target ) {
        $( target ).select2( "val", undefined );
    };

    var enable = function ( target ) {
        $( target ).select2( "enable", true );
    };

    var disable = function ( target ) {
        $( target ).select2( "enable", false );
    };

    var identifier = function( fromStr, toStr, id ) {
        var str,
            i = fromStr.indexOf("_"),
            s = fromStr.indexOf("#");

        if ( s == 0 ) {
            str = fromStr.substr(1, i) + toStr;
        } else if ( s == -1 ) {
            str = fromStr.substr(0, i + 1) + toStr;
        }
        if ( id ) { str = "#" + str; }

        return str;
    };

    var onChangeCountry = function ( e ) {
        if ( e.val ) {
            var country = e.currentTarget.id;
            var postcode = identifier( country, "postcode", true );
            if ( selected[country] && selected[country] != e.val ) {
                clear( postcode );
            }
            enable( postcode );
            selected[country] = e.val;
        }
    };

    var onChangePostcode = function ( e ) {
        if ( e.val ) {
            var postcode = e.currentTarget.id;
            var city = identifier( postcode, "city" );
            var state = identifier( postcode, "state" );
            var country = identifier( postcode, "country" );
            var el = identifier( postcode, "city", true );

            $.ajax( bbamastro.ajax_url, {
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
                success: function ( response ) {
                    local_data[city]  = response.city;
                    local_data[state] = response.state;

                    enable( el );

                    if ( local_data[city].length == 1 ) {
                        value( el, local_data[city][0].id, true );
                    }
                }
            });

            if ( selected[postcode] && selected[postcode] != e.val ) {
                clear( el );
            }
            selected[postcode] = e.val;
        }
    };

    var onChangeCity = function ( e ) {
        if ( e.val ) {
            var city = e.currentTarget.id;
            var state = identifier( city, "state" );
            var el = identifier( city, "state", true );

            if ( selected[city] && selected[city] != e.val ) {
                clear( el );
            }
            selected[city] = e.val;

            if ( local_data[state].length > 0 ) {
                enable( el );
                if ( local_data[state].length == 1 ) {
                    value( el, local_data[state][0].id, false );
                }
            }
        }
    };

    var onClearingPostcode = function( e ) {
        var city = identifier( e.currentTarget.id, "city", true );
        clear( city );
    };

    var onClearingCity = function( e ) {
        var city = identifier( e.currentTarget.id, "city", true );
        var state = identifier( e.currentTarget.id, "state", true );

        disable( city );
        clear( state );
    };

    var onClearingState = function( e ) {
        var state = identifier( e.currentTarget.id, "state", true );
        disable( state );
    };

    var selected = {}, local_data = {};

    var country_options = {
        placeholder: "Choose a country",
        data: bbamastro.countries
    };

    var postcode_options = {
        placeholder: "Choose a postcode",
        ajax: {
            url: bbamastro.ajax_url,
            dataType: "json",
            cache: true,
            data: function () {},
            results: function ( data, page ) {
                var more = ( page * 50 ) < data.total_count;
                return { results: data.items, more: more };
            }
        },
        initSelection: function( element, callback ) {
            var value = $( element ).val();
            if ( value != "" ) { callback({ id: value, text: value }); }
        }
    };

    var city_options = {
        placeholder: "Choose a city",
        query: function ( query ) {
            var t = query.term,
                data = { results: local_data[ query.element.attr("id") ] },
                filtered = { results: [] },
                text, process;

            if ( t === "" ) {
                query.callback( data );
                return;
            }

            text = function ( item ) { return ""+item.text; };
            process = function( datum, collection ) {
                datum = datum[0];
                if ( query.matcher( t, text(datum), datum ) ) {
                    collection.push( datum );
                }
            };

            $( data.results ).each2( function( i, datum ) { process( datum, filtered.results ); } );
            query.callback( filtered );
        },
        initSelection: function( element, callback ) {
            var value = $( element ).val();
            if ( value != "" ) { callback({ id: value, text: value }); }
        }
    }

    var state_options = $.extend( {}, city_options, { placeholder: "Choose a state" } );

    /**
     * Checkout page.
     */
    var id_country_checkout  = ["#billing_country", "#shipping_country"];
    var id_postcode_checkout = ["#billing_postcode", "#shipping_postcode"];
    var id_city_checkout     = ["#billing_city", "#shipping_city"];
    var id_state_checkout    = ["#billing_state", "#shipping_state"];

    // Country
    $.each( id_country_checkout, function( index, selector ) {
        $( selector ).select2( country_options ).on( "change", onChangeCountry );
        selected[selector.substr(1)] = $( selector ).val();
    });

    // Postcode
    $.each( id_postcode_checkout, function( index, selector ) {
        var country = identifier( selector, "country" );

        $( selector ).select2({
            placeholder: postcode_options.placeholder,
            initSelection: postcode_options.initSelection,
            ajax: $.extend({}, postcode_options.ajax, {
                data: function ( term, page ) {
                    return {
                        postcode: term,
                        country: selected[country],
                        page: page,
                        action: "bbamastro_search_postcode"
                    };
                }
            })
        }).on( "select2-clearing", onClearingPostcode )
          .on( "change", onChangePostcode );

        selected[selector.substr(1)] = $( selector ).val();
        if ( ! selected[country] ) { disable( selector ); }
    });

    // City
    $.each( id_city_checkout, function( index, selector ) {
        var postcode = identifier( selector, "postcode" );

        $( selector ).select2( city_options )
            .on( "select2-clearing", onClearingCity )
            .on( "change", onChangeCity );

        local_data[selector.substr(1)] = [];
        selected[selector.substr(1)] = $( selector ).val();
        if ( ! selected[postcode] ) { disable( selector ); }
    });

    // State
    $.each( id_state_checkout, function( index, selector ) {
        var city = identifier( selector, "city" );

        $( selector ).select2( state_options ).on( "select2-clearing", onClearingState );

        local_data[selector.substr(1)] = [];
        if ( ! selected[city] ) { disable( selector ); }
    });

    /**
     * Product page.
     */
    var id_country_product  = ["#calc_country"];
    var id_postcode_product = ["#calc_postcode"];

    // Country
    $.each( id_country_product, function( index, selector ) {
        $( selector ).select2( country_options ).on( "change", onChangeCountry );
        selected[selector.substr(1)] = $( selector ).val();
    });

    // Postcode
    $.each( id_postcode_product, function( index, selector ) {
        var country = identifier( selector, "country" );
        $( selector ).select2({
            placeholder: postcode_options.placeholder,
            initSelection: postcode_options.initSelection,
            ajax: $.extend({}, postcode_options.ajax, {
                data: function ( term, page ) {
                    return {
                        postcode: term,
                        country: selected[country],
                        page: page,
                        action: "bbamastro_search_postcode"
                    };
                }
            })
        });

        selected[selector.substr(1)] = $( selector ).val();
        if ( ! selected[country] ) { disable( selector ); }
    });
});
