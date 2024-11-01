<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_Suppliers;
use \ShipQuik\Shared\ShipQuik_PricingMode;
use \ShipQuik\Shared\ShipQuik_Configuration;

use WC_Shipping_Rates;
use WC_Shipping_Rate;
use WC_Tax;

class ShipQuik_ShippingRate
{
    protected $parent;

    protected $config;

    protected $shippingPackage;

    protected $rates;

    public $rate;

    public function __construct($parent, $rates, $shippingPackage)
    {
        $this->parent           = $parent;
        $this->rates            = $rates;
        $this->shippingPackage  = $shippingPackage;

        $this->config           = $parent->config;
    }

    private function isValidDestination() {
        $customerAddress = $this->shippingPackage->getDestination();

        if ($customerAddress['country'] && $customerAddress['postcode']) return true;

        return false;
    }

    /**
     * @return array
     */
    public function getRates()
    {
        if (!$this->isValidDestination()) return $this->rates;

        if (!$this->config->getValueByKey('suppliers')) {
            return $this->removeShipQuikRates($this->rates);
        };

        $shiQuikSuppliers = new ShipQuik_Suppliers($this->parent, $this->shippingPackage->getBoxes($this->config, $this->shippingPackage->getProducts()), $this->shippingPackage->getDestination());

        $pricingMode = ShipQuik_PricingMode::create($this->parent, $this->config->getValueByKey('pricingMode'))->getForRates($shiQuikSuppliers);

        if (empty($pricingMode->packages)) {
            return $this->removeShipQuikRates($this->rates);
        }

        $changeDUA = $this->config->getValueByKey('chargeDUA');

        foreach ($this->rates as $key => $rate) {
            if (self::isShipQuik($rate)) {
                $this->rate = $rate;
                $result = $pricingMode->getModeRate($this);

                if ($result) {
                    $DUACost = ($changeDUA ? $result['customsDeclaration']['value'] : 0);
                    $DUATextFreeShipping = ($changeDUA && $DUACost > 0 ? __(' (Gastos de importación incluidos)', 'ship-quik') : '');
                    $DUATextNormalShipping = ($changeDUA && $DUACost > 0 ? __(' (Gastos de importación incluidos)', 'ship-quik') : '');

                    $isFree = $this->isFreeShipping($rate);

                    // If is freeShipping, not continue
                    if ($isFree || $this->hasShopFreeFrom()) {
                        $freeRate = new WC_Shipping_Rate();
                        $freeRate->set_id($rate->get_id());
                        $freeRate->set_method_id($rate->get_method_id());
                        $freeRate->set_instance_id($rate->get_instance_id());
                        $freeRate->set_label(__('Free shipping', 'ship-quik') . $DUATextFreeShipping);
                        $freeRate->set_cost(0 + $DUACost);
                        $freeRate->set_taxes([]);

                        $freeRate->add_meta_data('_supplier_data', json_encode($result, JSON_UNESCAPED_UNICODE));
                        $rate->add_meta_data('Supplier', $result['supplier']);
                        $rate->add_meta_data('Servicio', $result['service']);
                        $rate->add_meta_data('Transito', $result['characteristics']['deliveryTime']);
                        $freeRate->add_meta_data('_logo', $result['supplier']);

                        $rates[$rate->get_id()] = $freeRate;

                        return $rates;
                    }

                    // If has tableRate
                    $tableRatePrice = $this->getShipQuikTableRate();
                    if ($tableRatePrice > 0) {
                        $tableRate = new WC_Shipping_Rate();
                        $tableRate->set_id($rate->get_id());
                        $tableRate->set_method_id($rate->get_method_id());
                        $tableRate->set_instance_id($rate->get_instance_id());
                        $tableRate->set_label($rate->get_label() . $DUATextNormalShipping);
                        $tableRate->set_cost($tableRatePrice + $DUACost);
                        $tableRate->set_taxes([]);

                        $rates[$rate->get_id()] = $tableRate;

                        return $rates;
                    }

                    $cost = $this->getShopFee($result['price']);
                    $tax = 0;

                    // $pepe = ShipQuik_Configuration::get_option('woocommerce_shipping_tax_class');

                    $location = array(
                        'country'   => WC()->customer->get_shipping_country() ? WC()->customer->get_shipping_country() : WC()->customer->get_billing_country(),
                        'state'     => WC()->customer->get_shipping_state() ? WC()->customer->get_shipping_state() : WC()->customer->get_billing_state(),
                        'city'      => WC()->customer->get_shipping_city() ? WC()->customer->get_shipping_city() : WC()->customer->get_billing_city(),
                        'postcode'  => WC()->customer->get_shipping_postcode() ? WC()->customer->get_shipping_postcode() : WC()->customer->get_billing_postcode(),
                    );

                    $tax = [];

                    foreach (wc_get_product_tax_class_options() as $tax_class => $tax_class_label) {
                        $tax_rates = WC_Tax::find_rates(
                            array_merge($location, array('tax_class' => $tax_class))
                        );
                        if (!empty($tax_rates) ) {
                            $rate_id      = array_keys($tax_rates);
                            $rate_data    = reset($tax_rates);

                            if ($rate_data['shipping'] == 'yes') {
                                $tax[] = $rate_data['rate'];

                                break;
                            }
                        }
                    }

                    $iva = 0;
                    if (isset($tax[0])) {
                        $iva = ($cost / (100 + $tax[0])) * $tax[0];
                    }

                    $rate->set_cost(($cost + $DUACost), 2);
                    $rate->set_taxes([$iva]);

                    $rate->set_label($rate->get_label() . $DUATextNormalShipping);
                    $rate->add_meta_data('_supplier_data', json_encode($result, JSON_UNESCAPED_UNICODE));
                    $rate->add_meta_data('Supplier', $result['supplier']);
                    $rate->add_meta_data('Servicio', $result['service']);
                    $rate->add_meta_data('Transito', $result['characteristics']['deliveryTime']);

                    $rate->add_meta_data('_logo', $result['supplier']);
                }
            }
        }

        return $this->rates;
    }

    private function removeShipQuikRates($rates)
    {
        $resultRates = [];

        foreach ($this->rates as $key => $rate) {
            if (!self::isShipQuik($rate)) {
                $resultRates[$key] = $rate;
            }
        }

        return $resultRates;
    }

    public static function isShipQuik($rate)
    {
        $shippingSupplierService = explode('-', $rate->get_method_id());

        if ($shippingSupplierService[0] == 'shipquik') {
            return true;
        }
        return false;
    }

    public function getSupplier($rate)
    {
        $shippingSupplierService = explode('-', $rate->get_method_id());

        if (isset($shippingSupplierService[1])) {
            return $shippingSupplierService[1];
        }

        return false;
    }

    public function getService($rate)
    {
        $shippingSupplierService = explode('-', $rate->get_method_id());

        if (isset($shippingSupplierService[2])) {
            return strtoupper($shippingSupplierService[2]);
        }

        return false;
    }

    private function isFreeShipping($rate)
    {
        if ($this->config->getValueByKey('isFreeShipping')) {
            return true;
        }

        return false;
    }

    private function hasShopFreeFrom()
    {
        $total = floatval(WC()->cart->cart_contents_total);

        if (floatval($this->config->getValueByKey('freeShippingPriceFrom')) > 0
            && $total >= floatval($this->config->getValueByKey('freeShippingPriceFrom'))) {
            return true;
        }

        return false;
    }

    private function getShopFee($price)
    {
        if ($this->hasShopFreeFrom()) return $price;

        $shopFeeValue = floatval($this->config->getValueByKey('shopFeeValue'));
        $shopFeeType  = $this->config->getValueByKey('shopFeeType');

        $price = floatval($price);

        if ($shopFeeValue > 0) {
            if ($shopFeeType== 'price') {
                $price = $price + $shopFeeValue;
            } else {
                $price = $price + ($price * $shopFeeValue / 100);
            }
        }

        return $price;
    }

    private function getShipQuikTableRate()
    {
        $resultRates = 0;

        $total = floatval(WC()->cart->cart_contents_total);

        $tableRates = $this->config->getValueByKey('shippingTableRates');

        if (!isset($tableRates[0])) {
            $tableRates = [];
        } else {
            $tableRates = $tableRates[0];
        }

        if (!empty($tableRates)) {
            foreach ($tableRates as $rate) {
                if ($total >= intval($rate['from']) && $total <= intval($rate['to'])) {
                    $resultRates = floatval($rate['value']);
                }
            }
        }

        return $resultRates;
    }
}
