<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use ShipQuik\Shared\ShipQuik_Configuration;

class ShipQuik_ShippingPackage
{
    protected $parent;

    protected $config;

    protected $package;

    protected $measureUnit;

    public function __construct($parent, $package, $measureUnit = 'kg')
    {
        $this->parent      = $parent;
        $this->config      = $parent->config;

        $this->package     = $package;
        $this->measureUnit = $measureUnit;
    }

    public function getDestination()
    {
        return $this->package['destination'];
    }

    public function getProducts()
    {
        return $this->package['contents'];
    }

    /**
     * Calculates the cubage of all products.
     *
     * @return float|int
     */
    public function getTotalCubage()
    {
        $total = 0;

        foreach ($this->package['contents'] as $item_id => $values) {
            $_product = $values['data'];
            $_product_height = (float) $_product->get_height();
            $_product_width  = (float) $_product->get_width();
            $_product_length = (float) $_product->get_length();

            if ($_product_height == 0) {
                $_product_height = $this->config->getValueByKey('defaultParcelHeight');
            }
            if ($_product_width == 0) {
                $_product_width = $this->config->getValueByKey('defaultParcelWidth');
            }
            if ($_product_length == 0) {
                $_product_length = $this->config->getValueByKey('defaultParcelLength');
            }

            $total += $_product_height * $_product_width * $_product_length;
        }

        return number_format(( ($total > 0) ? ( $total / 1000000 ) : 0 ), 2); // cubit meters
    }

    public static function getDefaultPackage($config)
    {
        $packagesInfo = $config->getValueByKey('shippingPackageList');

        foreach ($packagesInfo as $package) {
            if (isset($package['default'])) {
                return $package;
            }
        }

        return false;
    }

    /**
     * Get the boxes of all products.
     *
     * @return array
     */
    public static function getBoxes($config, $productItems)
    {
        $boxes = [];

        $defaultPackage = self::getDefaultPackage($config);

        $newBoxes = [];

        $newBox = [
            'weight'   => (string) 0,
            'length'   => (string) (float) $defaultPackage['depth'],
            'width'    => (string) (float) $defaultPackage['width'],
            'height'   => (string) (float) $defaultPackage['height'],
        ];

        foreach ($productItems as $data) {
            $_product = (isset($data['product_id'])
                ? wc_get_product($data['product_id'])
                : wc_get_product($data->get_product_id()));

            for ($i = 1; $i <= $data['quantity']; ++$i) {
                if ((float) $_product->get_length() == 0 && (float) $_product->get_width() == 0 && (float) $_product->get_height() == 0 && (float) $_product->get_weight() == 0) {
                    continue;
                }

                if ((float) $_product->get_length() > 0 && (float) $_product->get_width() > 0 && (float) $_product->get_height() > 0 && (float) $_product->get_weight() > 0) {
                    // product with weight and measures
                    $boxes[] = [
                        'weight' => (string) $_product->get_weight(),
                        'length' => (string) (float) $_product->get_length(),
                        'width' => (string) (float) $_product->get_width() ,
                        'height' => (string) (float) $_product->get_height()
                    ];
                } else {
                    if ((float) $_product->get_weight() >= (float) $defaultPackage['weight']) {
                        $boxes[] = [
                            'weight'   => (string) $_product->get_weight(),
                            'length'   => (string) (float) $defaultPackage['depth'],
                            'width'    => (string) (float) $defaultPackage['width'],
                            'height'   => (string) (float) $defaultPackage['height']
                        ];
                    } else {
                        $newWeight = (float) $_product->get_weight() + (float) $newBox['weight'];
                        $currentBoxWeight = (float) $newBox['weight'];

                        // filling the newBox because is posible
                        if ($newWeight <= $defaultPackage['weight']) {
                            $newBox['weight'] = (float) $newBox['weight'] + (float) $_product->get_weight();
                        }
                        if ($newWeight > $defaultPackage['weight']) {
                            $newBoxes[] = $newBox;
                            $newBox['weight'] = (float) $_product->get_weight();
                        }
                    }
                }
            }
        }

        if ((float) $newBox['weight'] > 0) {
            $newBoxes[] = $newBox;
        }

        if (sizeof($boxes) == 0 && sizeof($newBoxes) == 0) {
            return [[
                'weight'   => (string) (float) $defaultPackage['weight'],
                'length'   => (string) (float) $defaultPackage['depth'],
                'width'    => (string) (float) $defaultPackage['width'],
                'height'   => (string) (float) $defaultPackage['height']
            ]];
        }

        return array_merge($boxes, $newBoxes);
    }

    /**
     * Get the all products.
     *
     * @return array
     */
    public static function getProductsDetails($config, $productItems)
    {
        $products = [];

        foreach ($productItems as $data) {
            $_product = (isset($data['product_id'])
                ? wc_get_product($data['product_id'])
                : wc_get_product($data->get_product_id()));

            $attributes = $data->get_meta_data();

            $properties = [];
            foreach ($attributes as $attribute) {
                $values = $attribute->get_data();
                array_push($properties, ["name" => $values['key'], "value" => $values['value']]);
            };

            $measures = $_product->get_weight() . '-' . $_product->get_length() . 'x' .$_product->get_width() . 'x' . $_product->get_height();
            array_push($properties, ["name" => 'sq_measures', "value" => $measures]);

            $image_url = wp_get_attachment_image_url(
                $_product->get_image_id(),
                'woocommerce_thumbnail'
            );

            array_push($properties, ["name" => 'sq_image', "value" => $image_url]);

            $products[] = [
                "code" =>  $_product->get_sku(),
                "description" => $_product->get_name(),
                "price" => $_product->get_price(),
                "quantity" => $data['quantity'],
                "properties" => $properties,
            ];
        }

        return $products;
    }

    /**
     * Calculate the total volumes of all products of the order
     *
     * @return int
     */
    public function getTotalVolumes()
    {
        $total = 0;

        foreach ($this->package['contents'] as $item_id => $values) {
            $total += (int) $values['quantity'];
        }

        return $total;
    }
}
