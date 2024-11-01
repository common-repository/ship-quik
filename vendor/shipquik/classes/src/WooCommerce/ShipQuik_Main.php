<?php
/**
 * Main plugin class file.
 *
 */

namespace ShipQuik\WooCommerce;

use \ShipQuik\Shared\ShipQuik_Configuration;

use \ShipQuik\WooCommerce\Lib\ShipQuik_AdminAPI;
use \ShipQuik\WooCommerce\Admin\ShipQuik_OrdersAdmin;
use \ShipQuik\WooCommerce\FrontEnd\ShipQuik_CheckOut;
use \ShipQuik\WooCommerce\ShipQuik_ShippingMethods;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
class ShipQuik_Main
{
    /**
     * The single instance of Main.
     *
     * @var    object
     * @access private
     * @since  1.0.0
     */
    private static $_instance = null; 

    /**
     * Local instance of Admin_API
     *
     * @var ShipQuik_Admin_API|null
     */
    public $admin = null;

    /**
     * Settings class object
     *
     * @var    object
     * @access public
     * @since  1.0.0
     */
    public $settings = null;

    /**
     * The version number.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $_version; 

    /**
     * The token.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $_token; 

    /**
     * The main plugin file.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $assets_url;

    /**
     * Suffix for JavaScripts.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $script_suffix;

    /**
     * General configuration.
     *
     * @var    object
     * @access public
     * @since  1.0.0
     */
    public $config = null;

    /**
     * Woocommerce Logger object.
     *
     * @var    object
     * @access public
     * @since  1.0.0
     */
    public $logger = null;

    /**
     * WP uploads folder.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $upload;

    /**
     * Ship-Quik Docs path.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */
    public $upload_dir;

    /**
     * Ship-Quik Docs url.
     *
     * @var    string
     * @access public
     * @since  1.0.0
     */

    public $upload_url;

    /**
     * Constructor funtion.
     *
     * @param string $file    File constructor.
     * @param string $version Plugin version.
     */
    public function __construct($file = '', $version = SHIP_QUIK_VERSION)
    {
        $this->_version = $version;

        $this->_token   = 'ship_quik';

        // Load plugin environment variables.
        $this->file          = $file;
        $this->dir           = dirname($this->file);
        $this->assets_dir    = trailingslashit($this->dir) . 'assets';
        $this->assets_url    = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        $this->config        = new ShipQuik_Configuration($this);

        $this->logger        = wc_get_logger();

        $this->script_suffix = defined('SQ_SCRIPT_DEBUG') && SQ_SCRIPT_DEBUG ? '' : '.min';

        $this->upload        = wp_upload_dir();
        $this->upload_dir    = $this->upload['basedir'];
        $this->upload_url    = $this->upload['baseurl'];

        register_activation_hook($this->file, array($this, 'install' ));
        register_deactivation_hook($this->file, array($this, 'deactivation'));

        if (!$this->config->getAccessToken()) {
            add_action('admin_notices', array ($this, 'plugin_activation_notice'));
        }

        if (is_admin()) {
            // Load admin JS & CSS.
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10, 1);
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'), 10, 1); 
            add_action('admin_enqueue_scripts', array($this, 'global_enqueue_scripts'), 10);
        } else {
            // Load frontend JS & CSS.
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'), 10);
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 10);
            add_action('wp_enqueue_scripts', array($this, 'global_enqueue_scripts'), 10);
        }
        // Load global JS.

        $this->define_constants();
        $this->includes();

        $this->add_configuration_plugins_page();

        if (!$this->is_activated()) {
            add_action('admin_notices', array ($this, 'plugin_activation_notice'));
        } else {
            $this->init_hooks();
        }

        add_filter('language_attributes', function($attr) {
            return $attr . '" data-createordernonce="' . wp_create_nonce('create_order') . '"';
        });

        // Load API for generic admin functions.
        if (is_admin()) {
            $this->admin = new ShipQuik_AdminAPI($this);
        }

        // Handle localisation.
        $this->load_plugin_textdomain();
        add_action('init', array( $this, 'load_localisation' ), 0);
    } // End __construct ()

    public function is_activated() {
        return (ShipQuik_Configuration::get_option($this->config->base . 'activation') ? true : false);
    }

    public function plugin_activation_notice()
    {
        if (!$this->is_activated()) {
            echo '<div class="notice ship-quik-activation-notice"><span><b>'
            . __('Ship-Quik: Licence and activate.', 'ship-quik')
            . '</b></span><a class="button button-primary" href="'
            . admin_url('admin.php?page=ship_quik_settings')
            . '">'
            . __('Activate', 'ship-quik')
            . '</a></div>';
        }
    }

    /**
     * Define WC Constants.
     */
    private function define_constants()
    {
        $this->define('SHIP_QUIK_ABSPATH', dirname(SHIP_QUIK_PLUGIN_FILE) . '/');
        $this->define('SHIP_QUIK_PLUGIN_BASENAME', plugin_basename(SHIP_QUIK_PLUGIN_FILE));
        $this->define('SHIP_QUIK_DOCSPATH', $this->upload_dir .'/'. $this->_token);
        $this->define('SHIP_QUIK_DOCSURL', $this->upload_url .'/'. $this->_token);
    }

    /**
     * Configuration
     */
    private function add_configuration_plugins_page()
    {
        if ($this->is_plugin_enabled() ) {
            add_filter(
                'plugin_action_links_' . plugin_basename($this->get_plugin_name()),
                array(
                    $this,
                    'create_configuration_link',
                )
            );
        }
    }

    /**
     * Show action links on the plugin screen.
     *
     * @param array $links Plugin Action links.
     *
     * @return array
     */
    public function create_configuration_link( array $links )
    {
        $action_links = array(
            'configuration' => '<a href="'
            . admin_url('admin.php?page=ship_quik_settings')
            . '" aria-label="'
            . esc_attr__('Ship-Quik configuration', 'ship-quik')
            . '">'
            . esc_html__('Configuration', 'ship-quik')
            . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Define constant if not already set.
     *
     * @param string      $name  Constant name.
     * @param string|bool $value Constant value.
     */
    private function define($name, $value)
    {
        if (! defined($name)) {
            define($name, $value);
        }
    }

    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     * @return bool
     */
    private function is_request($type)
    {
        switch ($type) {
        case 'admin':
            return is_admin();
        case 'ajax':
            return defined('DOING_AJAX');
        case 'cron':
            return defined('DOING_CRON');
        case 'frontend':
            return ( !is_admin() || defined('DOING_AJAX') ) && !defined('DOING_CRON');
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    public function includes()
    {
        // HACK: using autoload
        if ($this->is_request('admin')) {
        }
        if ($this->is_request('frontend')) {
        }
    }

    /**
     * Hook into actions and filters.
     *
     * @since 1.0
     */
    private function init_hooks()
    {
        $adminOrders     = new ShipQuik_OrdersAdmin($this);
        $shippingMethods = new ShipQuik_ShippingMethods($this);
        $checkOut        = new ShipQuik_CheckOut($this);

        // Admin Orders Columns
        add_filter('manage_edit-shop_order_columns', [ $adminOrders, 'shop_order_column'], 20);
        add_action('manage_shop_order_posts_custom_column', [ $adminOrders, 'manage_shop_order_posts_custom_column'], 20, 2);

        // HPOS compatibility
        add_filter('manage_woocommerce_page_wc-orders_columns', [ $adminOrders, 'shop_order_column'], 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [ $adminOrders, 'manage_shop_order_posts_custom_column'], 20, 2);

        add_filter('woocommerce_hidden_order_itemmeta', [ $adminOrders, 'hidden_order_itemmeta'], 20, 1);

        add_action('wp_ajax_create_order', [ $adminOrders, 'ajax_create_order']);

        // Cart Hooks
        add_filter('woocommerce_cart_shipping_method_full_label', [ $shippingMethods, 'change_cart_shipping_method_full_label'], 10, 2);

        // Checkout Hooks
        add_action('woocommerce_thankyou', [$checkOut, 'after_thankyou'], 10, 1);
        add_filter('woocommerce_order_shipping_to_display_shipped_via', [$checkOut, 'order_shipping_to_display_shipped_via'], 9999, 2);

        // Shipping Methods
        add_action('init', [ $shippingMethods, 'init_shipping']);
        //add_filter('woocommerce_after_get_rates_for_package', [ $shippingMethods, 'custom_package_rates'], 10, 2);
        add_filter('woocommerce_package_rates', [ $shippingMethods, 'custom_package_rates'], 10, 2);

        // Remove admin orders line click action
        add_filter('post_class', function($classes) {
            if (is_admin()) {
                $current_screen = get_current_screen();
                if ($current_screen->base == 'edit'
                    && $current_screen->post_type == 'shop_order'
                ) {
                    $classes[] = 'no-link';
                }
            }

            return $classes;
        });
    }

    /**
     * Load frontend CSS.
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    public function enqueue_styles()
    {
        wp_register_style(
            $this->_token . '-frontend-css',
            esc_url($this->assets_url) . 'css/frontend.css',
            array(),
            $this->_version
        );
        wp_enqueue_style(
            $this->_token . '-frontend-css'
        );
    } // End enqueue_styles ()

    /**
     * Load global Javascript.
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    public function global_enqueue_scripts()
    {
        wp_register_script(
            $this->_token . '-global',
            esc_url($this->assets_url) . 'js/global' .
            $this->script_suffix . '.js',
            array('jquery'),
            $this->_version,
            true
        );
        wp_enqueue_script($this->_token . '-global');
    } // End global_enqueue_scripts ()

    /**
     * Load frontend Javascript.
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script('wp-api');

        wp_register_script(
            $this->_token . '-frontend',
            esc_url($this->assets_url) . 'js/frontend' .
            $this->script_suffix . '.js',
            array('jquery'),
            $this->_version,
            true
        );
        wp_enqueue_script($this->_token . '-frontend');
    } // End enqueue_scripts ()

    /**
     * Admin enqueue style.
     *
     * @param string $hook Hook parameter.
     *
     * @return void
     */
    public function admin_enqueue_styles( $hook = '' )
    {
        wp_register_style(
            $this->_token . '-admin',
            esc_url($this->assets_url) . 'css/admin.css',
            array(),
            $this->_version
        );
        wp_enqueue_style($this->_token . '-admin');

        wp_register_style(
            $this->_token . '-material',
            esc_url('//fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0'),
            array(),
            $this->_version
        );
        wp_enqueue_style($this->_token . '-material');

    } // End admin_enqueue_styles ()

    /**
     * Load admin Javascript.
     *
     * @access public
     *
     * @param string $hook Hook parameter.
     *
     * @return void
     * @since  1.0.0
     */
    public function admin_enqueue_scripts($hook = '')
    {
        if (isset($_REQUEST['post_type'])) {
            if (sanitize_key($_REQUEST['post_type']) == 'shop_order') {
                wp_register_script(
                    $this->_token . '-admin',
                    esc_url($this->assets_url) . 'js/admin' . $this->script_suffix . '.js',
                    array('jquery'),
                    $this->_version,
                    true
                );
                wp_enqueue_script($this->_token . '-admin');

                wp_localize_script(
                    $this->_token . '-admin',
                    'SHIP_QUIK_Ajax', [
                        'ajaxurl' => esc_url(admin_url('admin-ajax.php'))
                    ],
                );
        
            }
        }
    } // End admin_enqueue_scripts ()

    /**
     * Load plugin localisation
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    public function load_localisation()
    {
        load_plugin_textdomain(
            'ship-quik',
            false,
            dirname(plugin_basename($this->file)) . '/lang/'
        );
    } // End load_localisation ()

    /**
     * Load plugin textdomain
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    private function load_plugin_textdomain()
    {
        $domain = 'ship-quik';

        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain(
            $domain,
            SHIP_QUIK_ABSPATH . 'lang/' . $domain . '-' . $locale . '.mo'
        );
        load_plugin_textdomain(
            $domain,
            false,
            dirname(plugin_basename($this->file)) . '/lang/'
        );
    } // End load_plugin_textdomain ()

    /**
     * Returns if the plugin is active
     *
     * @return string
     */
    private static function is_plugin_enabled()
    {
        if (self::is_plugin_active_for_network() ) {
            return true;
        }

        return self::is_plugin_active_for_current_site();
    }

    /**
     * Returns if the plugin is active through network
     *
     * @return bool
     */
    public static function is_plugin_active_for_network()
    {
        if (! is_multisite() ) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');

        return isset($plugins[ self::get_plugin_name() ]);
    }

    /**
     * Returns the name of the plugin
     *
     * @return string
     */
    public static function get_plugin_name()
    {
        return plugin_basename(dirname(__DIR__) . '/ship-quik.php');
    }

    /**
     * Returns if the plugin is active for current site
     *
     * @return bool
     */
    public static function is_plugin_active_for_current_site()
    {
        return in_array(
            self::get_plugin_name(),
            apply_filters('active_plugins', ShipQuik_Configuration::get_option('active_plugins')),
            true
        );
    }

    /**
     * Main Ship_Quik Instance
     *
     * Ensures only one instance of Ship_Quik is loaded or can be loaded.
     *
     * @param string $file    File instance.
     * @param string $version Version parameter.
     *
     * @return Object Ship_Quik instance
     * @see    Ship_Quik()
     * @since  1.0.0
     * @static
     */
    public static function instance($file = '', $version = SHIP_QUIK_VERSION)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }

        return self::$_instance;
    } // End instance ()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(
            __FUNCTION__,
            __('Cloning of Ship_Quik is forbidden'),
            esc_attr($this->_version)
        );
    } // End __clone ()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(
            __FUNCTION__,
            __('Unserializing instances of Ship_Quik is forbidden'),
            esc_attr($this->_version)
        );
    } // End __wakeup ()

    /**
     * Installation. Runs on activation.
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    public function install()
    {
        $this->_log_version_number();

        $filename = $this->upload_dir . '/' . $this->_token . '/';

        if (!is_dir($filename)) {
            if (!file_exists($filename)) {
                mkdir($filename, 0755, true);
            }
        }
    } // End install ()

    /**
     * Uninstallation. Runs on deactivation.
     *
     * @access public
     * @since  1.0.0
     * @return void
     */
    public function deactivation()
    {
        //wp_clear_scheduled_hook('SHIP_QUIK_cron_hook');

    }// End deactivation ()

    /**
     * Log the plugin version number.
     *
     * @access public
     * @return void
     * @since  1.0.0
     */
    private function _log_version_number()
    { 
        update_option($this->_token . '_version', $this->_version);
    } // End _log_version_number ()
}
