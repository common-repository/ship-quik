<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce\Suppliers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use WC_Shipping_Method;

class ShipQuik_Shipping_Method_shipquik_price_price extends WC_Shipping_Method
{
    /**
     * Constructor for abstract shipping class
     *
     * @access public
     * @return void
     */

    public function __construct($instance_id = 0)
    {
        $this->id                 = 'shipquik-price-price';
        $this->instance_id        = absint($instance_id);
        $this->supports           = array(
            'shipping-zones'
        );

        // $this->availability     = 'including';
        // $this->countries        = [];

        $this->enabled             = 'yes';
        $this->title               = __('por Ship-Quik más barato', 'ship-quik');
        $this->method_title        = __('por Ship-Quik más barato', 'ship-quik');
        $this->method_description  = __('por Ship-Quik más barato', 'ship-quik');
    }

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param  mixed $package
     * @return void
     */
    public function calculate_shipping($package = array())
    {
        $rate = array(
            'id'       => $this->id . $this->instance_id,
            'label'    => $this->title,
            'package'  => $package,
            'cost'     => 0
        );

        $this->add_rate($rate);
    }
}
