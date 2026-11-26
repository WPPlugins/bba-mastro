<?php

if (!defined('ABSPATH')) {
    exit;
}

class BBAAM_ExcludedCategories
{
    /**
     * @var string|null
     */
    private $page_id = null;

    /**
     * @var string
     */
    private $plugin_path;

    public function __construct($plugin_path)
    {
        $this->plugin_path = $plugin_path;
        $this->initialize();
    }

    private function initialize()
    {
        add_action('admin_menu', array($this, 'add_menu_link'));
        add_action('admin_post_update_excluded_categories', array($this, 'update_excluded_categories'));
        add_action('rest_api_init', array($this, 'register_custom_woo_route'));
    }

    public function add_menu_link()
    {
        $this->page_id = add_submenu_page(
            'woocommerce',
            __('BBA Excluded Product Categories', 'bba-mastro-excluded-categories'),
            __('BBA Excluded Product Categories', 'bba-mastro-excluded-categories'),
            'manage_woocommerce',
            'bba_admin_excluded_categories',
            array($this, 'render_page')
        );
    }

    public function update_excluded_categories()
    {
        // Permission check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'bba-mastro-excluded-categories'));
        }

        // Nonce validation (optional but recommended)
        if (isset($_POST['_wpnonce'])) {
            check_admin_referer('update_excluded_categories');
        }

        $settings = get_option('woocommerce_bbamastro_rules_settings', array());

        if (!is_array($settings)) {
            $settings = array();
        }

        $excluded_cat = array();

        if (isset($_POST['excluded_cat']) && is_array($_POST['excluded_cat'])) {
            $excluded_cat = array_map('sanitize_text_field', wp_unslash($_POST['excluded_cat']));
        }

        $settings['excluded_categories'] = implode(',', $excluded_cat);

        update_option('woocommerce_bbamastro_rules_settings', $settings);

        wp_safe_redirect(admin_url('admin.php?page=bba_admin_excluded_categories'));
        exit;
    }

    public function render_page()
    {
        $template = $this->plugin_path . '/admin/templates/wc-settings/bba_admin_product_categories.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    public function register_custom_woo_route()
    {
        register_rest_route(
            'wc/v2',
            '/wc-excluded-categories',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_excluded_categories'),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function get_excluded_categories($request)
    {
        $excluded = BBAM_Utils::getConfig('excluded_categories');

        if (empty($excluded)) {
            $categories = array();
        } else {
            $categories = array_filter(array_map('trim', explode(',', $excluded)));
        }

        return rest_ensure_response(array(
            'excluded_categories' => $categories,
        ));
    }
}