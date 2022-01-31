<?php
    $settings['enabled'] = array(
        'title'   => __( 'Enable', 'bbamastro' ),
        'type'    => 'checkbox',
        'label'   => __( 'Enable BBA Mastro shipping methods', 'bbamastro' ),
        'default' => 'no',
    );

    $settings['show_flat_rate'] = array(
        'title'   => __('Show Flat Rate'),
        'type'    => 'checkbox',
        'label'   => __('Show flat rate together with bba quotes'),
        'default' => 'yes'
    );

    $settings['api_credentials'] = array(
        'title' => __( 'API Credentials', 'bbamastro' ),
        'type'  => 'title',
    );

    $settings['api_url'] = array(
        'title'       => __( 'API URL', 'bbamastro' ),
        'type'        => 'hidden',
        'default'     => '',
        'description' => __( 'BBA Mastro API URL.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['auth_url'] = array(
        'title'       => __( 'Auth API URL', 'bbamastro' ),
        'type'        => 'text',
        'default'     => '',
        'description' => __( 'BBA Mastro Auth API URL.', 'bbamastro' ),
        'desc_tip'    => true
    );

    $settings['acc_username'] = array(
        'title'       => __( 'Username', 'bbamastro' ),
        'type'        => 'text',
        'default'     => '',
        'description' => __( 'BBA Mastro account username.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['acc_password'] = array(
        'title'       => __( 'Password', 'bbamastro' ),
        'type'        => 'password',
        'default'     => '',
        'description' => __( 'BBA Mastro account password.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    // edited here
    $settings['reseller_id'] = array(
        'title'       => __( 'Reseller Id', 'bbamastro' ),
        'placeholder' => __( 'Reseller Id', 'bbamastro' ),
        'type'        => 'text',
        'css'         => 'min-width:350px;',
        'default'     => '',
    );
    // end of edit

    $settings['channel'] = array(
        'title'       => __( 'Channel', 'bbamastro' ),
        'type'        => 'text',
        'default'     => '',
        'description' => __( 'Channel that would be use for getting quotes', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['package_settings'] = array(
        'title' => __( 'Default Package Settings', 'bbamastro' ),
        'type'  => 'title',
    );

    $settings['default_length'] = array(
        'title'       => __( 'Length (cm)', 'bbamastro' ),
        'type'        => 'decimal',
        'default'     => '',
        'description' => __( 'Default package length in cm.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['default_width'] = array(
        'title'       => __( 'Width (cm)', 'bbamastro' ),
        'type'        => 'decimal',
        'default'     => '',
        'description' => __( 'Default package width in cm.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['default_height'] = array(
        'title'       => __( 'Height (cm)', 'bbamastro' ),
        'type'        => 'decimal',
        'default'     => '',
        'description' => __( 'Default package height in cm.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['default_weight'] = array(
        'title'       => __( 'Weight (kg)', 'bbamastro' ),
        'type'        => 'decimal',
        'default'     => '',
        'description' => __( 'Default package weight in kg.', 'bbamastro' ),
        'desc_tip'    => true,
    );

    $settings['excluded_categories'] = array(
        'type'        => 'hidden',
    );

    $settings['warehouse_details'] = array(
        'title' => __( 'Warehouse Details', 'bbamastro' ),
        'type'  => 'title',
    );

    $settings['warehouse_country'] = array(
        'title'       => __( 'Country', 'bbamastro' ),
        'placeholder' => 'Choose a country',
        'type'        => 'hidden',
        'css'         => 'min-width:350px;',
        'default'     => '',
    );

    $settings['wh_postcode'] = array(
        'title'       => __( 'Postcode', 'bbamastro' ),
        'placeholder' => __( 'Choose a postcode', 'bbamastro' ),
        'type'        => 'text',
        'css'         => 'min-width:350px;',
        'default'     => '',
    );

    $settings['wh_city'] = array(
        'title'       => __( 'City', 'bbamastro' ),
        'placeholder' => __( 'Choose a city', 'bbamastro' ),
        'type'        => 'text',
        'css'         => 'min-width:350px;',
        'default'     => '',
    );
    return $settings;
