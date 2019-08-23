<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<?php if ( 0 < count( $rates ) ) : ?>
    <table border="0" class="calc-table">
    <?php foreach ( $rates as $rate ) : ?>
        <tr><td><?php echo wc_cart_totals_shipping_method_label( $rate ); ?></td></tr>
    <?php endforeach; ?>
    </table>
<?php else : ?>
    <?php _e( 'No result found.', 'bbamastro' ); ?>
<?php endif; ?>
