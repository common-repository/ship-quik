<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_API;
use \ShipQuik\WooCommerce\ShipQuik_Shipments;

use DateTime;

class ShipQuik_Suppliers
{
    private static $_instance = null;

    protected $parent;

    protected $boxes;

    protected $destination;

    protected $id_carrier;

    protected $config;

    protected $data;

    public function __construct($parent = null, $boxes = [], $destination = [], $id_carrier = 0)
    {
        $this->parent = $parent;
        $this->boxes = $boxes;
        $this->destination = $destination;

        $this->config = $parent->config;

        $this->data = $this->getData($this->boxes, $this->destination);
        $this->id_carrier = $id_carrier;
    }

    private function getNormalizeDestination($destination)
    {
        $resultAddress = [];

        // package destination
        if (isset($destination['country'])) {
            $resultAddress['CountryCode'] = $destination['country'];
            $resultAddress['Country']= WC()->countries->countries[$destination['country']];
        }
        if (isset($destination['postcode'])) {
            $resultAddress['PostalCode'] = $destination['postcode'];
        }
        if (isset($destination['city'])) {
            $resultAddress['City'] = $destination['city'];
        }
        if (isset($destination['address'])) {
            $resultAddress['Street'] = $destination['address'];
        }

        // customer destination
        if (isset($destination['countryCode'])) {
            $resultAddress['CountryCode'] = $destination['countryCode'];
            $resultAddress['Country']= WC()->countries->countries[$destination['countryCode']];
        }
        if (isset($destination['postalCode'])) {
            $resultAddress['PostalCode'] = $destination['postalCode'];
        }
        if (isset($destination['street'])) {
            $resultAddress['Street'] = $destination['street'];
        }

        return $resultAddress;
    }

    private function getData($boxes, $destination)
    {
        $supplierData = [];

        $customerAddress = $this->getNormalizeDestination($destination);
        $senderAddress = ShipQuik_Shipments::getSenderAddress($this->config);

        $supplierData['countryCode'] = 'ES';
        $supplierData['origin']['postalCode'] = $senderAddress['PostalCode'];
        $supplierData['origin']['countryCode'] = $senderAddress['CountryCode'];
        $supplierData['destination']['postalCode'] = $customerAddress['PostalCode'];
        $supplierData['destination']['countryCode'] = $customerAddress['CountryCode'];

        $supplierData['parcels'] = $boxes;

        return $supplierData;
    }

    private function getSuppliersNames($suppliers)
    {
        $result = [];

        foreach ($suppliers as $supplier) {
            $result[] = $supplier['name'];
        }

 
        return $result;
    }

    public function send($suppliers, $searchMode)
    {
        $result = false;

        $params = [];

        if ($searchMode === 'speed') {
            $searchMode = 'lowest-speed';
        }
        if ($searchMode === 'price') {
            $searchMode = 'lowest-price';
        }
        if ($searchMode === 'speedprice') {
            $searchMode = 'lowest-both';
        }

        $params['Baseurl'] = $this->config->getValueByKey('endpoints')['getRates'];
        $params['Data'] = $this->data;
        $params['Data']['suppliers'] = $this->getSuppliersNames($suppliers);

        $curl = new ShipQuik_API($this->parent, $params, true);
        $result = $curl->runCall();

        if ($result == "renew_token") {
            $this->config->reNewAccessToken();
            $result = $curl->runCall();
        }

        return $result;
    }

    public function getSuppliersResult($suppliers = [], $pricingMode = 'null')
    {
        if (empty($suppliers)) {
            $suppliers = $this->config->getValueByKey('suppliers');
        }

        $suppliersResult = $this->send($suppliers, $pricingMode); // HACK: PS unserialize

        return $suppliersResult;
    }

    public function getServiceByName($shipQuikSuppliers, $name)
    {
        foreach ($shipQuikSuppliers as $service) {
            if ($service['service'] == $name) {
                return $service;
            }
        }

        return false;
    }

    public function getServiceByType($shipQuikSuppliers, $type)
    {
        foreach ($shipQuikSuppliers as $service) {
            if ($service['type'] == $type) {
                return $service;
            }
        }

        return false;
    }

    public function getServiceByPrice($shipQuikSuppliers)
    {
        $result = [];
        $sw = true;
        $min = 0;

        foreach ($shipQuikSuppliers as $keyService => $valueService) {
            $price = ($valueService['price'] + $valueService['customsDeclaration']['value']);

            if (($valueService['characteristics']['pickupType'] != 1 || $valueService['characteristics']['deliveryType'] != 1) || $price <= 0) {
                continue;
            }

            if ($sw) {
                $sw= false;
                $min = $price;
                $result = $valueService;
            }
            if ($price < $min) {
                $min = $price;
                $result = $valueService;
            }
        }

        return $result;
    }

    public function getServiceBySpeed($shipQuikSuppliers)
    {
        $result = [];
        $sw = true;
        $speed = 0;

        usort($shipQuikSuppliers, function($a, $b) {
            return $a['price'] < $b['price'] ? -1 : 1;
        });

        foreach ($shipQuikSuppliers as $keyService => $valueService) {
            $price = ($valueService['price'] + $valueService['customsDeclaration']['value']);
            $time = intval($valueService['characteristics']['deliveryTime']);

            if (($valueService['characteristics']['pickupType'] != 1 || $valueService['characteristics']['deliveryType'] != 1) || $price <= 0) {
                continue;
            }

            if ($sw) {
                $speed = $time;
                $sw= false;
                $result = $valueService;
            }
            if ($time < $speed) {
                $speed = $time;
                $result = $valueService;
            }
        }

        return $result;
    }

    public static function instance()
    {
        if (! isset(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class();
        }
        return self::$_instance;
    }

    public function __clone()
    {
        trigger_error('La clonación de este objeto no está permitida', E_USER_ERROR);
    }
}
