<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_Helper;
use \ShipQuik\Shared\ShipQuik_PricingMode;
use \ShipQuik\WooCommerce\ShipQuik_ShippingPackage;
use \ShipQuik\WooCommerce\ShipQuik_ShippingZone;
use \ShipQuik\WooCommerce\ShipQuik_ShippingRate;

use WC_Shipping_Zones;
use WC_Shipping_Zone;

class ShipQuik_ShippingMethods
{
    protected $methods;

    protected $parent;

    protected $config;

    protected $services;

    public function __construct($parent)
    {
        $this->parent  = $parent;
        $this->config  = $parent->config;
    }

    public function add_ship_quik_shipping_method($methods)
    {
        if (empty($this->services) || !ShipQuik_ShippingZone::haveServicesEnabled($this->services)) {
            $methods['shipquik_generic'] = 'ShipQuik\WooCommerce\Suppliers\ShipQuik_Shipping_Method';
        } else {
            foreach ($this->services as $service) {
                if ($service['enabled']) {
                    $shippingMethodId = 'shipquik_' . strtolower($service['supplierId']) . '_' . strtolower($service['serviceId']);
    
                    $methods[$shippingMethodId] = '\ShipQuik\WooCommerce\Suppliers\ShipQuik_Shipping_Method_'. $shippingMethodId;
                }
            }
        }

        return $methods;
    }

    function change_cart_shipping_method_full_label( $label, $method )
    {
        $metadata = $method->get_meta_data();

        if (isset($metadata['Supplier']) && isset($metadata['Servicio'])) {
            $label .= '<div style="padding: 10px 0" class="ship_quick_supplier">';
                $label .= '<span>' . esc_attr($metadata['Supplier']) . '<span>';
            $label .= '</div>';
            $label .= '<div style="padding-bottom: 10px" class="ship_quick_service">';
                $label .= '<span>' . esc_attr($metadata['Servicio']) . '</span> - ';
                $label .= '<span>' . esc_attr($metadata['Transito']) . ' h</span>';
            $label .= '</div>';
        }

        if (isset($metadata['_logo'])) {
            $label .= '<div class="ship_quick_supplier_logo"><img src="' . esc_url($this->parent->assets_url) . 'img/logos/' . $metadata['_logo'] . '.png' . '" /></div>';
        }

        return $label;
    }

    public function init_shipping()
    {
        $pricingMode = new ShipQuik_PricingMode($this->parent, $this->config->getValueByKey('pricingMode'));
        if (isset($pricingMode->services)) {
            $this->services = $pricingMode->services['servicesList'];

            add_filter('woocommerce_shipping_methods', [$this, 'add_ship_quik_shipping_method']);
        }
    }

    public function save_shipping_methods() {
        \WC_Cache_Helper::get_transient_version('shipping', true);

        $this->init_shipping_methods();
        $this->init_shipping_zones();
    }

    private function init_shipping_methods()
    {
        $pricingMode = new ShipQuik_PricingMode($this->parent, $this->config->getValueByKey('pricingMode'));
        $this->services = $pricingMode->services['servicesList'];

        $shippingMethodFiles = SHIP_QUIK_TEMPLIST_PATH . 'ShipQuik_Shipping_Method_*.php';
        ShipQuik_Helper::deleteShippingMethodFiles($shippingMethodFiles);

        if (!empty($this->services)) {
            foreach ($this->services as $service) {
                if ($service['enabled']) {
                    $shippingMethodId = 'shipquik-' . strtolower($service['supplierId']) . '-' . strtolower($service['serviceId']);
                    $shippingMethodFileId = 'shipquik_' . strtolower($service['supplierId']) . '_' . strtolower($service['serviceId']);
                    $shippingMethodFile = SHIP_QUIK_TEMPLIST_PATH . 'ShipQuik_Shipping_Method_' . $shippingMethodFileId . '.php';
                    $templateMethodFile = SHIP_QUIK_TEMPLATE_PATH . 'ShipQuik_Shipping_Method.txt';
        
                    copy($templateMethodFile, $shippingMethodFile);
                    chmod($shippingMethodFile, 0755);

                    $fileContent = file_get_contents($shippingMethodFile);

                    $fileContent = str_replace("{{shipping_method_fileid}}", $shippingMethodFileId, $fileContent);
                    $fileContent = str_replace("{{shipping_method_id}}", $shippingMethodId, $fileContent);
                    $fileContent = str_replace("{{shipping_method_enabled}}", ($service['enabled'] ? 'yes': 'no'), $fileContent);
                    $fileContent = str_replace("{{shipping_method_title}}", $service['name'], $fileContent);
                    $fileContent = str_replace("{{shipping_method_method_title}}", $service['name'], $fileContent);
                    $fileContent = str_replace("{{shipping_method_method_description}}", $service['description'], $fileContent);

                    file_put_contents($shippingMethodFile, $fileContent);
                }
            }
        }
    }

    private function init_shipping_zones()
    {
        if (!is_admin() || is_ajax()) {
            return false;
        }

        if (isset($_REQUEST['page'])) {
            if (sanitize_text_field($_REQUEST['page']) === 'wc-settings') {
                return false;
            }
        }

        ShipQuik_ShippingZone::deleteAllZones();

        $countries = ShipQuik_ShippingZone::getCountries($this->services);

        if (empty($countries)) {
            $myZone = new ShipQuik_ShippingZone($this->parent, $this->services);
            $myZone->setShipQuickZone();
        } else {
            foreach ($countries as $country) {
                $myZone = new ShipQuik_ShippingZone($this->parent, $this->services, $country);
                $myZone->setShipQuickZone();
            }
        }
    }

    public function custom_package_rates($rates, $package)
    {
        if (is_admin() && !is_cart() && !is_checkout()) {
            return $rates;
        }
        $shippingPackage = New ShipQuik_ShippingPackage($this->parent, $package, 'kg');
        $shippingRate = new ShipQuik_ShippingRate($this->parent, $rates, $shippingPackage);

        return $shippingRate->getRates();
    }
}
