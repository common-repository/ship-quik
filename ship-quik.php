<?php
/**
 * Plugin Name: Envíos con Ship-Quik
 * Version: 1.0.0
 * Plugin URI: https://ship-quik.com/
 * Description: Connector for Ship Quik.
 * Requires at least: 4.7
 * Tested up to: 6.6
 * Author: shipquik
 *
 * Text Domain: ship-quik
 * Domain Path: /lang/
 *
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress
 * @author  Ship Quik
 * @since   1.0.0
 */

if (!defined('ABSPATH') ) {
    exit;
}

define('SHIP_QUIK_PLATFORM', 'woocommerce');

define('SHIP_QUIK_VERSION', '1.0.0');
define('SHIP_QUIK_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SHIP_QUIK_CLASSES_PATH', SHIP_QUIK_PLUGIN_PATH . 'ShipQuik/');
define('SHIP_QUIK_TEMPLATE_PATH', SHIP_QUIK_PLUGIN_PATH . 'templates/');
define('SHIP_QUIK_TEMPLIST_PATH', SHIP_QUIK_PLUGIN_PATH . 'templist/');

define('SHIP_QUIK_CONFIG_DATA', plugin_dir_path(__FILE__) . '/config.json');

function ship_quik_plugin_init()
{
    if (current_user_can('activate_plugins') && !class_exists('WooCommerce') ) {
        add_action('admin_init', 'ship_quik_plugin_deactivate');
        add_action('admin_notices', 'ship_quik_plugin_admin_notice');

        function ship_quik_plugin_deactivate()
        {
            deactivate_plugins(plugin_basename(__FILE__));
        }

        function ship_quik_plugin_admin_notice()
        {
            $dpa_child_plugin = __('Ship-Quik', 'ship-quik');
            $dpa_parent_plugin = __('Woocommerce', 'ship-quik');

            echo '<div class="error"><p><strong>' . esc_html($dpa_child_plugin) . '</strong> requiere ' . esc_html($dpa_parent_plugin) . ' para funcionar correctamente. Por favor activa ' . esc_html($dpa_parent_plugin) . ' antes de activar <strong>' . esc_html($dpa_child_plugin) . '</strong> Hasta ese momento, Ship-Quik estará desactivado.</p></div>';
        }

    } else {
        //Load composer
        include SHIP_QUIK_PLUGIN_PATH .'vendor/autoload.php';

        //Load templist
        include SHIP_QUIK_PLUGIN_PATH .'/bootstrap.php';

        // Define PLUGIN_FILE.
        if (! defined('SHIP_QUIK_PLUGIN_FILE') ) {
            define('SHIP_QUIK_PLUGIN_FILE', __FILE__);
        }

        /**
         * Returns the main instance of Ship_Quik to prevent the need to use globals.
         *
         * @since  1.0.0
         * @return object Ship_Quik
         */
        function ship_quik()
        {
            $instance = \ShipQuik\WooCommerce\ShipQuik_Main::instance(__FILE__, SHIP_QUIK_VERSION);

            if (is_admin() && is_null($instance->settings)) {
                $instance->settings = \ShipQuik\WooCommerce\ShipQuik_Settings::instance($instance);
            }

            $instance->install();

            return $instance;
        }

        ship_quik();
    }
}

add_action('plugins_loaded', 'ship_quik_plugin_init');
