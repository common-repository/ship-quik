<?php
/**
Functions for Ship-Quik
**/

namespace ShipQuik\WooCommerce;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_API;
use \ShipQuik\Shared\ShipQuik_Configuration;

class ShipQuik_Shipments
{
    private static $_instance = null;

    protected $parent;

    protected $order_id;

    protected $config;

    public $data;

    public function __construct($parent = null, $order_id = 0)
    {
        $this->parent = $parent;
        $this->order_id = $order_id;

        $this->config = $parent->config;

        $this->data = $this->getData($this->order_id);
    }

    private function getData($order_id)
    {
        $order = wc_get_order($this->order_id);

        $supplierInfo = [];

        $shippingMethods = $order->get_shipping_methods();

        $shippingMethod = [];

        // TODO: We must have only one.
        foreach ($shippingMethods as $key => $value) {
            $shippingMethod = $value;
        }

        $shipment = [];

        $senderAddress = self::getSenderAddress($this->config);
        $customerAddress = [];

        if (ShipQuik_Configuration::get_option('woocommerce_ship_to_destination') == 'shipping') {
            $customerAddress = $order->get_address('shipping');
            $customerAddressBilling = $order->get_address('billing');
            $customerAddress['email'] = $customerAddressBilling['email'];
            $customerAddress['phone'] = $customerAddressBilling['phone'];
        } else {
            $customerAddress = $order->get_address('billing');
        }

        // Shipment Object
        $supplierInfo = [];
        $shipmentElement = [];

        foreach ($shippingMethod->get_meta_data() as $meta) {
            $item = $meta->get_data();
            if ($item['key'] == '_supplier_data') {
                $supplierInfo = json_decode(
                    str_replace('&quot;', '"', $item['value']), true, 99
                );

                break;
            }
        }

        // Supplier object
        if (array_key_exists('supplier', $supplierInfo)) {
            $shipmentElement['shippingSupplier'] = [
                'Supplier' => $supplierInfo['supplier'],
                'SupplierService' => $supplierInfo['service'],
                'CountryCode' => 'ES',
            ];
        }

        $shipmentElement['taric']['declaredObjects'] = 'Otros';

        $shipmentElement['seriesInfo'] = [
            'salesOrder' => 'PV-WC',
            'purchaseOrder' => 'PC-WC',
            'invoice' => 'FV-WC',
        ];

        $shipmentElement['parcels'] = ShipQuik_ShippingPackage::getBoxes(
            $this->config, $order->get_items()
        );

        $shipmentElement['products'] = ShipQuik_ShippingPackage::getProductsDetails(
            $this->config, $order->get_items()
        );

        // Sender object
        $shipmentElement['sender'] = [
            "name"      => $this->config->getValueByKey('senderAddressCompany'),
            "identificationNumber" =>
                    $this->config->getValueByKey('senderAddressCif'),
            "phone"     => [
                "countryCode" => (string) $senderAddress['CountryCode'],
                "number" => $this->config->getValueByKey('senderAddressPhoneNumber')
            ],
            "email"     => $this->config->getValueByKey('senderAddressEmail'),
            "comments"  => $this->config->getValueByKey('comments'),
            "attention" => '',
            "address"   => [
                'street'      => $senderAddress['Street'],
                'city'        => $senderAddress['City'],
                'countryCode' => $senderAddress['CountryCode'],
                'postalCode'  => $senderAddress['PostalCode'],
                'country'     => $senderAddress['Country'],
            ]
        ];

        // recipient object
        $states = WC()
            ->countries
            ->get_states($order->get_shipping_country());
        $province = !empty($states[$order->get_shipping_state()]) ? $states[$order->get_shipping_state()] : '';


        $name = ($customerAddress['company'] ? $customerAddress['company'] : $customerAddress['first_name'] . ' '
                . $customerAddress['last_name']);

        $cif = $order->get_meta($this->config->getValueByKey('customerFieldDNI'));

        $comments = $order->get_customer_note();
        if (!$comments) {
            $comments = '';
        }

        $contact = $customerAddress['first_name'] . ' '
                . $customerAddress['last_name'];

        $shipmentElement['recipient'] = [
            "name"      => $name,
            "identificationNumber" =>
                $cif ? $cif : $this->config->getValueByKey('senderAddressCif'),
            "phone"     => [
                "countryCode" => (string) $customerAddress['country'],
                "number" => $customerAddress['phone'],
            ],
            "email"     => $customerAddress['email'],
            "comments"  => $comments,
            "attention" => $contact,
            'address' => [
                'street'      => $customerAddress['address_1']
                        . ' ' .$customerAddress['address_2'],
                'city'        => $customerAddress['city'],
                'countryCode' => $customerAddress['country'],
                'postalCode'  => $customerAddress['postcode'],
                'province'    => $province,
            ]
        ];

        $shipmentElement['clientReference'] = strval($order_id);

        $shipment['source'] = 'woocommerce';
        $shipment['shipments'][] = $shipmentElement;

        return $shipment;
    }

    public static function getSenderAddress($config)
    {
        $senderAddress = [];

        $senderAddress['Street'] = $config->getValueByKey('senderAddressStreet');
        $senderAddress['City'] = $config->getValueByKey('senderAddressCity');
        $senderAddress['CountryCode'] = $config
                ->getValueByKey('senderAddressCountryCode');
        $senderAddress['PostalCode'] = $config
                ->getValueByKey('senderAddressPostalCode');

        $senderAddress['Country'] = WC()->countries
            ->countries[$config->getValueByKey('senderAddressCountryCode')];

        return $senderAddress;
    }

    public function send()
    {
        $params = [];
        $params['Baseurl']  = $this->config
            ->getValueByKey('endpoints')['createOrder'];
        $params['Data']     = $this->data;

        $curl = new ShipQuik_API($this->parent, $params, true);
        $result = $curl->runCall();

        if ($result == "renew_token") {
            $this->config->reNewAccessToken();

            $result = $curl->runCall();
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
