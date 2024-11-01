<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ShipQuik_PricingMode
{
    private static $_instance = null;

    protected $parent;

    protected $config;

    public $pricingMode;

    public $services;

    public $packages;

    public $shiQuikSuppliers;

    public function __construct($parent = null, $pricingMode = 'price')
    {
        $this->parent      = $parent;
        $this->pricingMode = $pricingMode;

        $this->config      = $parent->config;

        $this->services    = $this->getModeConfig();
    }

    public static function create($parent, $pricingMode = 'price')
    {
        return new self($parent, $pricingMode);
    }

    public function getForRates($shiQuikSuppliers)
    {
        $this->shiQuikSuppliers = $shiQuikSuppliers;
        $this->packages = $shiQuikSuppliers->getSuppliersResult(
            $this->isServicesListMode(),
            $this->pricingMode
        );

        return $this;
    }

    private function isServicesListMode()
    {
        if ($this->pricingMode == 'serviceslist') {
            return $this->getSuppliersId($this->services['servicesList']);
        }

        return $this->config->getValueByKey('suppliers');
    }

    private function getSuppliersId()
    {
        $result = [];

        foreach ($this->services['servicesList'] as $service) {
            $result[] = $service['supplierId'];
        }

        return array_unique($result);
    }

    private function getModeConfig()
    {
        // PricingMode values
        // shipquik:     Precio pactado (uno).
        // speed:        Precio del más rapido (uno).
        // price:        Precio del mas barato (uno).
        // serviceslist: Lista de servicios (needs servicesList object) (seleccción).
        // speedprice:   Puede eligir mas rapido o mas barato (seleccción).

        switch ($this->pricingMode) {
        case 'speed':
            return [
                    "cost" => 0,
                    'servicesList' => [
                        [
                            "enabled"     => true,
                            "supplierId"  => "speed",
                            "serviceId"   => "speed",
                            "name"        => __('by Ship-Quik fast shipping', 'ship-quik'),
                            "description" => __('by Ship-Quik fast shipping', 'ship-quik'),
                            "countries"   => ["ALL"]
                        ]
                    ]
                ];
                break;
        case 'price':
            return [
                    "cost" => 0,
                    'servicesList' => [
                        [
                            "enabled"     => true,
                            "supplierId"  => "price",
                            "serviceId"   => "price",
                            "name"        => __('by Ship-Quik low cost', 'ship-quik'),
                            "description" => __('by Ship-Quik low cost', 'ship-quik'),
                            "countries"   => ["ALL"]
                        ]
                    ]
                ];
                break;
        case 'serviceslist':
            return [
                    "cost" => 0,
                    'servicesList' => $this->config->getValueByKey('servicesList')
                ];
                break;
        case 'speedprice':
            return [
                    "cost" => 0,
                    'servicesList' => [
                        [
                            "enabled"     => true,
                            "supplierId"  => "price",
                            "serviceId"   => "price",
                            "name"        => __('by Ship-Quik low cost', 'ship-quik'),
                            "description" => __('by Ship-Quik low cost', 'ship-quik'),
                            "countries"   => ["ALL"]
                        ],
                        [
                            "enabled"     => true,
                            "supplierId"  => "speed",
                            "serviceId"   => "speed",
                            "name"        => __('by Ship-Quik fast shipping', 'ship-quik'),
                            "description" => __('by Ship-Quik fast shipping', 'ship-quik'),
                            "countries"   => ["ALL"]
                        ]
                    ]
                ];
                break;
        };
    }

    public function getModeRate($rateObject = null)
    {
        // PricingMode values
        // speed:        Precio del más rapido (uno).
        // price:        Precio del mas barato (uno).
        // serviceslist: Lista de servicios (needs servicesList object) (seleccción).
        // speedprice:   Puede eligir mas rapido o mas barato (seleccción).
        if (sizeof($this->packages) > 0) {
            switch ($this->pricingMode) {
            case 'speed':
                return $this->shiQuikSuppliers->getServiceBySpeed($this->packages);
            case 'price':
                return $this->shiQuikSuppliers->getServiceByPrice($this->packages);
            case 'serviceslist':
                $name = $rateObject->getService($rateObject->rate);
    
                return $this->shiQuikSuppliers->getServiceByName($this->packages, $name);
            case 'speedprice':
                if ($rateObject->getSupplier($rateObject->rate) == 'speed') {
                    return $this->shiQuikSuppliers->getServiceBySpeed($this->packages);
                } else {
                    return $this->shiQuikSuppliers->getServiceByPrice($this->packages);
                }
            };
        }
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
