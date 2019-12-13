<?php
class BBAM_AdminCustomOrderFieldsMetabox {
    public function __construct(){
        add_action('add_meta_boxes', array($this, 'add'));
    }

    public function add(){
        add_meta_box(
			'bba-order-custom-fields',
			__( 'BBA Mastro Tracking And Consigment Number', 'bba-admin-custom-order-fields' ),
			array( $this, 'render' ),
			'shop_order',
			'normal',
			'default'
		);
    }

    public function render() {
        global $post;
        $instance = BBAM();
        $order_fields = $instance::$bbamTrackingConsignmentInstance->getOrderFields(( $post->ID ));
        
		if ( ! empty( $order_fields ) ) :
			?>
			<ul>
				<?php foreach ( $order_fields as $field ) : ?>
					<li class="form-field" style="width: 50%; float: left;">
						<label for="bba-admin-custom-order-fields-input-<?php echo esc_attr( $field->id ); ?>">
							<?php esc_html_e( $field->label, 'bba-admin-custom-order-fields' ); ?>
							<?php if ( $field->is_required() ) : ?>
								<span class="required">*</span>
							<?php endif; ?>
							<?php if ( ! empty( $field->description ) ) : ?>
								<?php echo wc_help_tip( $field->description ); ?>
							<?php endif; ?>
						</label>
						<p><?= $field->get_value() ? $field->get_value() : 'N/A' ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
			<div style="clear: both;"></div>
			<?php
		endif;
    }
}