<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $product;

$shipping_enabled = BBAM()->bbam_woocommerce_enabled() && BBAM()->bbam_shipping_enabled();
$calculator_enabled = ( bbamastro_config( 'product_page_calculator' ) == 'yes' ) ? true : false;
?>

<?php if ( $shipping_enabled && $calculator_enabled ): ?>

<form id="shipping-calculator" method="post" action="">
    <div class="input" style="overflow-y:visible; border:none;">
        <div class="title"><?php _e( 'Estimate Shipping Cost', 'bbamastro' ); ?></div>
       
        <div class='form-group'>
            <select name="country" class='form-control' id="calc_country">
                <option value=""><?php _e( 'Choose a country', 'bbamastro' ); ?></option>
            </select>
        
            <select name="postcode" id="calc_postcode" class='form-control '>
                <option value=""><?php _e( 'Choose a postcode', 'bbamastro' ); ?> </option>
            </select>
        </div>

        <input type="hidden" name="price" id="price" value="" placeholder="<?php _e( 'Choose a postcode', 'bbamastro' ); ?>" />
        <input type="hidden" name="product-id" value="<?php echo esc_attr( $product->get_id()); ?>" />
        <input type="hidden" name="action" value="bbamastro_estimate_shipping" />
        <div class='form-group text-center'>
            <button type="button" id="estimate" class="button alt">
                <?php _e( 'Estimate', 'bbamastro' ); ?>
                
            </button>
        </div>
        <div class="note"><?php _e( 'Price exc Tax', 'bbamastro' ); ?></div>

        <div class="output"></div>
    </div>
</form>

<style type="text/css">
#shipping-calculator .input,
#shipping-calculator .output { 
    margin: 10px 0;
    padding: 5px 0; 
    max-height: 200px;
    overflow-y: scroll;
    border: thin solid #efefef;
    display: inline-block;
    width: 100%;
}
#shipping-calculator .title { margin-bottom: 1em; font-weight: bold; }
#shipping-calculator .note { font-size: 12px; }
#shipping-calculator input[type="hidden"] { max-width: 350px; margin-bottom: 1em; }
#shipping-calculator button[type="submit"] { margin-bottom: 0.5em; }
#shipping-calculator .select2-container { margin-bottom: 1em; }
#shipping-calculator .select2-container a { box-shadow: 0 0 0 0 currentColor; }
table.calc-table, table.calc-table td { border:0px; padding: 5px}
</style>

<script type="text/javascript">
jQuery( function( $ ) {
    $( "#estimate" ).click( function() {
        $( ".output" ).html( "" );
        $( ".output" ).prepend('<img id="loading" src="http://assets.motherjones.com/interactives/projects/features/koch-network/shell19/img/loading.gif" style="width:100%;margin: 0 auto;margin-top:-60px;"/>');

        if ( $('form.cart').hasClass('variations_form') ) {
            var var_id = $('form.cart .variation_id').val();
            if ( var_id != '' ) {
                $('#shipping-calculator input[name="product-id"]').val( var_id );
            } else {
                $( ".output" ).html( "Please select a variation." );
                return;
            }
        }
        
        $.ajax( bbamastro.ajax_url, {
            method: "POST",
            data: $( "#shipping-calculator" ).serialize(),
            dataType: "html",
            success: function ( response ) {
                $( ".output" ).html( response );
            },
            error: function () {
                $( ".output" ).html( "Loading failed." );
            }
        });
    });

});
</script>

<?php endif; ?>
