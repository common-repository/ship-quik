<?php
/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Settings
 */

namespace ShipQuik\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

use \ShipQuik\WooCommerce\ShipQuik_ShippingMethods;
use \ShipQuik\Shared\ShipQuik_Configuration;
use \ShipQuik\Shared\ShipQuik_Helper;

/**
 * Settings class.
 */
class ShipQuik_Settings
{
    /**
     * The single instance of Settings.
     *
     * @var    object
     * @access private
     * @since  1.0.0
     */
    private static $_instance = null; 

    /**
     * The main plugin object.
     *
     * @var    object
     * @access public
     * @since  1.0.0
     */
    protected $parent = null;

    /**
     * Prefix for plugin settings.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $base = '';

    /**
     * Version for plugin.
     *
     * @var    array
     * @access public
     * @since  1.0.0
     */
    public $version;

    /**
     * General configuration.
     *
     * @var    object
     * @access public
     * @since  1.0.0
     */
    protected $config = null;

    protected $is_required = false;

    public $_suppliers = [];

    /**
     * Constructor function.
     *
     * @param object $parent Parent object.
     */
    public function __construct($parent)
    {
        $this->parent = $parent;
        $this->config = $parent->config;

        $this->version = explode('.', $this->parent->_version);

        $this->base = '_' . $this->parent->_token . '_';

        // Initialise settings.
        add_action('init', array($this, 'init_settings'), 11);

        // Register plugin settings.
        add_action('admin_init', array($this, 'register_settings'));

        // Add settings page to menu.
        add_action('admin_menu', array($this, 'add_menu_item'));

        // Configure placement of plugin settings page. See readme for implementation.
        add_filter($this->base . 'menu_settings', array($this, 'configure_settings'));

        $this->is_required = $this->checkRequiredFields();
    }

    /**
     * Initialise settings
     *
     * @return void
     */
    public function init_settings()
    {
        $isConfigPage = false;
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'ship_quik_settings') {
            $isConfigPage = true;
        }
        if (isset($_REQUEST['action']) && isset($_REQUEST['option_page']) && $_REQUEST['action'] === 'update' && $_REQUEST['option_page'] === 'ship_quik_settings') {
            $isConfigPage = true;
        }

        if (is_admin() && $this->parent->is_activated() && $isConfigPage) {
            $this->_suppliers = ShipQuik_Helper::getSuppliersConfiguration($this->parent, [], false);
            add_filter('pre_update_option', [$this, 'pre_update_option'], 10, 3);
        }

        $this->settings = $this->settings_fields();
    }

    /**
     * Add settings page to admin menu
     *
     * @return void
     */
    public function add_menu_item()
    {
        $page = add_submenu_page(
            'woocommerce',
            __('Ship-Quik', 'ship-quik'),
            __('Ship-Quik', 'ship-quik'),
            'manage_options',
            $this->parent->_token . '_settings',
            [$this, 'settings_page']
        );
        add_action('admin_print_styles-' . $page, array($this, 'settings_assets'));
    }

    /**
     * Load settings JS & CSS
     *
     * @return void
     */
    public function settings_assets()
    {
        // We're including the farbtastic script & styles here because they're needed for the colour picker
        // If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
        wp_enqueue_style('farbtastic');
        wp_enqueue_script('farbtastic');

        // We're including the WP media scripts here because they're needed for the image upload field.
        // If you're not including an image upload then you can leave this function call out.
        wp_enqueue_media();

        wp_register_script(
            $this->parent->_token . '-settings-js',
            $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js',
            array('farbtastic', 'jquery'),
            '1.0.0',
            true
        );
        wp_enqueue_script($this->parent->_token . '-settings-js');

        wp_register_script(
            $this->parent->_token . '-packagelist-js',
            $this->parent->assets_url . 'js/settings-packagelist' . $this->parent->script_suffix . '.js',
            array('farbtastic', 'jquery'),
            '1.0.0',
            true
        );
        wp_enqueue_script($this->parent->_token . '-packagelist-js');

        wp_register_script(
            $this->parent->_token . '-form-js',
            $this->parent->assets_url . 'js/settings-form' . $this->parent->script_suffix . '.js',
            array('farbtastic', 'jquery'),
            '1.0.0',
            true
        );
        wp_enqueue_script($this->parent->_token . '-form-js');
    }

    public function pre_update_option($value, $option, $old_value)
    {
        if ($option == '_ship_quik_suppliers') {
            $tmp = [];

            foreach ($value as $supplier) {
                $tmp[] = [
                    'name' => $supplier,
                    'services' => $this->getSupplierServices($supplier),
                ];
            }

            return $tmp;
        }

        return $value;
    }

    public function getSupplierServices($name)
    {
        foreach ($this->_suppliers as $supplier) {
            if ($supplier['name'] == $name) {
                return $supplier['services'];
            }
        }

        return [];
    }

    public function getSuppliersOptions()
    {
        $options = [];

        if ($this->_suppliers) {
            foreach ($this->_suppliers as $key => $supplier) {
                $options[$supplier['name']] = __($supplier['name'], 'ship-quik');
            }
        }

        return $options;
    }

    public function getOrderStatus()
    {
        $options = [];

        $options['do-nothing'] = __('Do nothing', 'ship-quik');

        foreach (wc_get_order_statuses() as $key => $order_status) {
            $options[$key] = $order_status;
        }

        return $options;
    }

    /**
     * Build settings fields
     *
     * @return array Fields to be displayed on settings page
     */
    private function settings_fields()
    {
        $settings = [];

        if (!$this->parent->is_activated()) {
            $settings['activacion'] = array(
                'title'       => __('Ship-Quik: License and activation.', 'ship-quik'),
                'description' => '',
                'fields'      => array(
                    array(
                        'id'          => 'activationEmail',
                        'label'       => __('Email', 'ship-quik'),
                        'description' => __('Enter here the email with which you registered with Ship-Quik.', 'ship-quik'),
                        'type'        => 'text',
                        'required'    => true,
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'activationKey',
                        'label'       => __('private key', 'ship-quik'),
                        'description' => __('Enter your private key here.', 'ship-quik'),
                        'type'        => 'text',
                        'required'    => true,
                        'default'     => '',
                        'placeholder' => ''
                    )
                )
            );
        } else {
            $countryCode = explode(':', ShipQuik_Configuration::get_option('woocommerce_default_country'));
            $settings['fieldset_0'] = array(
                'title'       => __('Supplier list', 'ship-quik'),
                'description' => '',
                'fields'      => array(
                    array(
                        'id'          => 'suppliers',
                        'label'       => '',
                        'description' => __('Choose what suppliers to work with', 'ship-quik'),
                        'type'        => 'checkbox_multi',
                        'default'     => '',
                        'options'     => $this->getSuppliersOptions(),
                        'placeholder' => '',
                        'required'    => true
                    )
                )
            );

            $settings['fieldset_2_2'] = array(
                'title'       => __('Prices', 'ship-quik'),
                'description' => '',
                'fields'      => array(
                    array(
                        'id'          => 'pricingMode',
                        'label'       => __('Pricing Mode', 'ship-quik'),
                        'description' => '',
                        'type'        => 'select',
                        'default'     => 'price',
                        'options'     => [
                            'speed'        => __('Fastest Shipment', 'ship-quik'),
                            'price'        => __('Cheapest Shipment', 'ship-quik'),
                            'speedprice'   => __('Customer choose between Fastest or Cheapest', 'ship-quik'),
                            //'serviceslist' => __('Customer choose a service list', 'ship-quik')
                        ],
                        'placeholder' => '',
                        'class'       => 'subsection',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'chargeDUA',
                        'label'       => __('Charge import costs to your customers', 'ship-quik'),
                        'description' => __('Do you want to charge import costs to your customers? If Yes, this cost will be charged even in free shipments.', 'ship-quik'),
                        'type'        => 'select',
                        'default'     => 'yes',
                        'options'     => [
                            'yes' => __('Yes', 'ship-quik'),
                            'no'  => __('No', 'ship-quik')
                        ],
                        'placeholder' => '',
                        'class' => 'subsection',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'isFreeShipping',
                        'label'       => __('Free shipping', 'ship-quik'),
                        'description' => __('If you want to offer free shipping to your customers', 'ship-quik'),
                        'type'        => 'select',
                        'default'     => 'no',
                        'options'     => [
                            'yes' => __('Yes', 'ship-quik'),
                            'no'  => __('No', 'ship-quik')
                        ],
                        'placeholder' => '',
                        'class' => 'subsection'
                    ),
                    array(
                        'id'          => 'freeShippingPriceFrom',
                        'label'       => __('Free shipping from', 'ship-quik'),
                        'description' => __('Cart subtotal equal to or greater than this amount', 'ship-quik'),
                        'type'        => 'number',
                        'min'         => '0',
                        'default'     => '0',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'shopFeeType',
                        'label'       => __('Store fee type', 'ship-quik'),
                        'description' => '',
                        'type'        => 'select',
                        'default'     => 'price',
                        'options'     => [
                            'price'   => __('By price', 'ship-quik'),
                            'percent' => __('By percent', 'ship-quik')
                        ],
                        'placeholder' => '',
                        'class' => 'subsection'
                    ),
                    array(
                        'id'          => 'shopFeeValue',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'number',
                        'min'         => '0',
                        'default'     => '0',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'comments',
                        'label'       => __('Store comments on shipping', 'ship-quik'),
                        'description' => '',
                        'type'        => 'textarea',
                        'default'     => '',
                        'placeholder' => '',
                        'class' => 'subsection'
                    ),
                    array(
                        'id'          => 'orderStatus',
                        'label'       => __('Select the change of the status of the order after being processed', 'ship-quik'),
                        'description' => '',
                        'type'        => 'select',
                        'default'     => 'do-nothing',
                        'options'     => $this->getOrderStatus(),
                        'placeholder' => '',
                    )
                )
            );

            $settings['fieldset_3_3'] = array(
                'title'       => __('Sender Address', 'ship-quik'),
                'description' => '',
                'fields'      => array(
                    array(
                        'id'          => 'senderAddressCompany',
                        'label'       => __('Company', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressStreet',
                        'label'       => __('Address', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => ShipQuik_Configuration::get_option('woocommerce_store_address') . ' ' . ShipQuik_Configuration::get_option('woocommerce_store_address_2'),
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressCity',
                        'label'       => __('City', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => ShipQuik_Configuration::get_option('woocommerce_store_city'),
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressPostalCode',
                        'label'       => __('Postal Code', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => ShipQuik_Configuration::get_option('woocommerce_store_postcode'),
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressCountryCode',
                        'label'       => __('Country Code', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => $countryCode[0],
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressPhoneNumber',
                        'label'       => __('Phone Number', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressEmail',
                        'label'       => __('Email', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => '',
                        'required'    => true
                    ),
                    array(
                        'id'          => 'senderAddressCif',
                        'label'       => __('CIF/DNI/NIE', 'ship-quik'),
                        'description' => '',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => '',
                        'required'    => true
                    )
                )
            );

            $settings['fieldset_4_4'] = array(
                'title'       => __('Default packages', 'ship-quik'),
                'description' => '',
                'fields'      => array(
                    array(
                        'id'          => 'shippingPackageList',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'package_list',
                        'placeholder' => '',
                        'callback'    => array($this->parent->admin, 'validate_package_list_field'),
                        'required'    => true
                    )
                )
            );
        }

        $settings = apply_filters($this->parent->_token . '_settings_fields', $settings);

        return $settings;
    }

    private function checkRequiredFields()
    {
        $required_fields = [
            'suppliers',
            'pricingMode',
            'chargeDUA',
            'senderAddressCompany',
            'senderAddressStreet',
            'senderAddressCity',
            'senderAddressPostalCode',
            'senderAddressCountryCode',
            'senderAddressPhoneNumber',
            'senderAddressEmail',
            'senderAddressCif',
            'shippingPackageList'
        ];

        foreach ($required_fields as $field) {
            $value = $this->config->getValueByKey($field);
            if (!$value || empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings()
    {
        if (is_array($this->settings)) {

            foreach ($this->settings as $section => $data) {

                if ($data['fields']) {
                    $args = array(
                        'before_section' => '<div class="panel ' . $section . '">',
                        'after_section'  => '</div>',
                    );

                    add_settings_section($section, $data['title'], array($this, 'settings_section'), $this->parent->_token . '_settings', $args);

                    foreach ($data['fields'] as $field) {
                        // Validation callback for field.
                        $validation = '';
                        if (isset($field['callback'])) {
                            $validation = $field['callback'];
                        }

                        // Register field.
                        $option_name = $this->base . $field['id'];
                        register_setting($this->parent->_token . '_settings', $option_name, $validation);

                        // Add field to page.
                        add_settings_field(
                            $field['id'],
                            $field['label'],
                            array($this->parent->admin, 'display_field'),
                            $this->parent->_token . '_settings',
                            $section,
                            array(
                                'field'  => $field,
                                'prefix' => $this->base,
                                'class'  => (isset($field['class']) ? $field['class'] : '')
                            )
                        );
                    }
                }
            }
        }
    }

    /**
     * Settings section.
     *
     * @param  array $section Array of section ids.
     * @return void
     */
    public function settings_section($section)
    {
        echo '';
    }

    /**
     * Load settings page content.
     *
     * @return void
     */
    /**
     * Load settings page content.
     *
     * @return void
     */
    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $html = '';
        $submit = true;

        if (isset($_GET['settings-updated'])) {
            add_settings_error('ship_quik_messages', 'ship_quik_message', __('Settings Saved', 'ship-quik'), 'updated');
        }

        $html .= '<div id="' . esc_attr($this->parent->_token) . '_settings" >';

        $html .= '<div class="ship_quick_logo"><img src="' . esc_url($this->parent->assets_url) . 'img/logo.png"' . ' /></div>' . "\n";

        $html .= '<form method="post" action="options.php" enctype="multipart/form-data" class="ship_quik" data-activate="' . (!$this->parent->is_activated() ? '1' : '0') . '">' . "\n";

        ob_start();
        wp_nonce_field();

        if (!$this->parent->is_activated()) {
            $html .= '<div class="panel info-activation"><h2>' . __('IMPORTANTE:', 'ship-quik'). '</h2>';
                $html .= '<table class="form-table" role="presentation">';
                    $html .= '<tbody>';
                        $html .= '<tr>';
                            $html .= '<th scope="row"></th>';
                            $html .= '<td>';
                            $html .= '<h4>Una vez tengas instalado el plugin, tienes que ir a nuestra web <a style="color: #00C2A3 !important;" target="_blank" href="https://ship-quik.com/es">Ship-Quik</a> y registrarte para obtener la clave de autorización.</h4>';
                            $html .= '<h4>Verás un apartado para poner el nombre de la tienda y la url de la misma. Una vez estén,  <a style="color: #00C2A3 !important;" target="_blank" href="https://ship-quik.com/es/plugins">haz click en generar Key</a>. Cuando la tengas, cópiala y pégala en el pluging de tu tienda.</h4>';
                            $html .= '</td>';
                        $html .= '</tr>';
                    $html .= '</tbody>';
                $html .= '</table>';
            $html .= '</div>';
        }

        do_settings_sections($this->parent->_token . '_settings');
        settings_fields($this->parent->_token . '_settings');

        $html .= ob_get_clean();

        if ($submit) {
            if ($this->parent->is_activated()) {
                $html .= '<div class="panel-submit panel"><input name="Submit" type="submit" class="button-primary" value="' . esc_attr(__('Save Settings', 'ship-quik')) . '" /></div>' . "\n";
            } else {
                $html .= '<div class="panel-submit panel"><input name="Submit" type="submit" class="button button-primary button-activate" value="' . esc_attr(__('Activate', 'ship-quik')) . '" /></div>' . "\n";
            }
        }
        $html .= '</form>' . "\n";

        if (isset($_REQUEST['settings-updated']) && $_REQUEST['settings-updated'] == 'true') {
            if (!$this->parent->is_activated()) {
                if ($this->parent->config->getValueByKey('activationEmail') && $this->parent->config->getValueByKey('activationKey')) {
                    $this->config->reNewAccessToken();

                    $token = $this->parent->config->getValueByKey('activation');

                    if (isset($token) && $token) {
                        wp_redirect(admin_url('admin.php?page=ship_quik_settings'));
                        die();
                    }
                }

                add_settings_error('ship_quik_messages', 'ship_quik_message', __('There was a problem with the activation, please check that the private key is correct.', 'ship-quik'), 'error');
            } else {
                $shippingMethods = new ShipQuik_ShippingMethods($this->parent);

                $shippingMethods->save_shipping_methods();
            }
        }

        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";

        settings_errors('ship_quik_messages');

        echo $html;
    }

    /**
     * Main Settings Instance
     *
     * Ensures only one instance of Settings is loaded or can be loaded.
     *
     * @since  1.0.0
     * @static
     * @see    Ship_Quik()
     * @param  object $parent Object instance.
     * @return object Settings instance
     */
    public static function instance($parent)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($parent);
        }
        return self::$_instance;
    } // End instance()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cloning of Settings is forbidden.'), esc_attr($this->parent->_version));
    } // End __clone()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Unserializing instances of Settings is forbidden.'), esc_attr($this->parent->_version));
    } // End __wakeup()
}
