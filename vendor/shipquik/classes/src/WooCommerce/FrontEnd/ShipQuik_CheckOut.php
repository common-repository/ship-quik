<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce\FrontEnd;

use \ShipQuik\WooCommerce\ShipQuik_Shipments;
use \ShipQuik\WooCommerce\ShipQuik_ShippingRate;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ShipQuik_CheckOut
{
    /**
     * Parent object.
     *
     * @var    object
     * @access private
     * @since  1.0.0
     */
    protected $parent = null; 

    /**
     * General configuration.
     *
     * @var    object
     * @access public
     * @since  1.0.0
     */
    protected $config = null;

    public function __construct($parent)
    {
        $this->parent = $parent;
        $this->config = $parent->config;
    }

    public function order_shipping_to_display_shipped_via($shipped_via, $order) {
        $shippingMethods = $order->get_shipping_methods();

        $shippingMethod = [];

        // TODO: We must have only one.
        foreach ($shippingMethods as $key => $value) {
            $shippingMethod = $value;
        }
        $supplier = [];

        foreach ($shippingMethod->get_meta_data() as $meta) {
            $item = $meta->get_data();
            if ($item['key'] == 'Supplier') {
                $supplier = $item['value'];

                break;
            }
        }

        if ($supplier) {
            $shipped_via = '&nbsp;<small class="shipped_via">'
                . sprintf(__('via %s', 'woocommerce'), $supplier)
                . '</small>';
        }

        return $shipped_via;
    }

    public function adminErrorNotice()
    {
        echo '<div class="notice notice-warning is-dismissible">'
            . $this->errorMessage
            . '</div>';
        $this->errorMessage = '';
    }

    public function after_thankyou($order_id)
    {
        if ($order_id) {

            $order = wc_get_order($order_id);

            if (!$order->get_meta('_shipquik_order_guid')) {
                $shiQuikShipments = new ShipQuik_Shipments($this->parent, $order_id);
                $result = $shiQuikShipments->send();
    
                if ($result) {
                    $order->update_meta_data(
                        '_shipquik_order_guid',
                        $result['id']
                    );
                    $order->update_meta_data(
                        '_shipquik_shipment_guid',
                        $result['shipmentOrders'][0]['id']
                    );
                } else {
                    $order->update_meta_data('_shipquik_order_guid', 'ERROR');
                }
    
                $order->save();
            }
        }
    }
}
