<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use WC_Shipping_Zones;
use WC_Shipping_Zone;

class ShipQuik_ShippingZone
{
    protected $parent;

    protected $config;

    protected $prefix;

    protected $name;

    protected $services;

    private $_country;

    private $_zone;

    public function __construct($parent, $services = [], $country = "", $name = 'Ship-Quik')
    {
        $this->parent     = $parent;
        $this->config     = $parent->config;

        $this->prefix     = $name;

        $this->name       = $name . ($country == '' ? '' : ' ' . $country);
        $this->_country   = $country;
        $this->services   = ($country == '' ? $services : $this->getServicesByCountry($services, $country));
    }

    public function setShipQuickZone()
    {
        if (empty($this->services) || !$this->haveServicesEnabled($this->services)) {
            $this->newGeneralZone();
        } else {
            $this->_zone = $this->getZoneByName($this->name);

            $this->setLocations();
            $this->setShippingMethods();

            $this->_zone->save();
        }
    }

    private function newGeneralZone()
    {
        $this->_zone = new WC_Shipping_Zone();
        $this->_zone->set_zone_name('Ship-Quik');
        $this->_zone->set_zone_order(0);
        $this->_zone->add_shipping_method('shipquik_generic');

        $this->_zone->save();
    }

    private function deleteGeneralZone($genericZone)
    {
        if ($genericZone) {
            WC_Shipping_Zones::delete_zone($genericZone->get_id());
        }
    }

    private function setLocations()
    {
        if ($this->_zone) {
            if ($this->_country) {
                $this->_zone->clear_locations([ 'country' ]);
                if ($this->_country != 'ALL') {
                    $this->_zone->add_location($this->_country, 'country');
                }
            }
        } else {
            $this->_zone = new WC_Shipping_Zone();
            $this->_zone->set_zone_name($this->name);
            $this->_zone->set_zone_order(0);
            if ($this->_country) {
                if ($this->_country != 'ALL') {
                    $this->_zone->add_location($this->_country, 'country');
                }
            }
        }
    }

    private function setShippingMethods()
    {
        $shippingMethods = $this->_zone->get_shipping_methods();

        if (empty($shippingMethods)) {
            foreach ($this->services as $service) {
                $methodId = $this->getShipQuikMethodId($service);

                if ($service['enabled']) {
                    $this->_zone->add_shipping_method($methodId);
                }
            }
        } else {
            foreach ($this->services as $service) {
                $methodId = $this->getShipQuikMethodId($service);

                $method = $this->getShippingMethodOnZone($shippingMethods, $methodId);

                if ($method) {
                    if (!$service['enabled']) {
                        $this->_zone->delete_shipping_method($method->instance_id);
                    }
                } else {
                    if ($service['enabled']) {
                        $this->_zone->add_shipping_method($methodId);
                    }
                }
            }
        }
    }

    public static function getCountries($services = [])
    {
        if (!$services) return [];

        $countries = [];

        foreach ($services as $service) {
            if ($service['enabled']) {
                foreach ($service['countries'] as $country) {
                    if (!in_array($country, $countries)) {
                        $countries[] = $country;
                    }
                }
            }
        }

        return $countries;
    }

    private function getServicesByCountry($services, $country)
    {
        $servicesByCountry = [];

        foreach ($services as $service) {
            if ($service['enabled']) {
                if (in_array($country, $service['countries'])) {
                    $servicesByCountry[] = $service;
                }
            }
        }

        return $servicesByCountry;
    }

    public static function haveServicesEnabled($services)
    {
        foreach ($services as $service) {
            if ($service['enabled']) {
                return true;
            }
        }

        return false;
    }

    private function getShippingMethodOnZone($shippingMethods, $methodId)
    {
        foreach ($shippingMethods as $method) {
            if ($method->id == $methodId) {
                return $method;
            }
        }

        return null;
    }

    private function getShipQuikMethodId($service)
    {
        return 'shipquik_' . strtolower($service['supplierId']) . '_' . strtolower($service['serviceId']);
    }

    public function getZoneByName($name)
    {
        $zones = WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone ) {
            if ($zone['zone_name'] == $name) {
                $result = WC_Shipping_Zones::get_zone_by('zone_id', $zone['id']);

                return $result;
            }
        }

        return null;
    }

    public static function deleteAllZones($prefix = 'Ship-Quik')
    {
        $zones = WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone ) {
            $tmp = explode(' ', $zone['zone_name']);
            if ($tmp[0] == $prefix) {
                $result = WC_Shipping_Zones::get_zone_by('zone_id', $zone['id']);
                WC_Shipping_Zones::delete_zone($result->get_id());
            }
        }
    }
}
