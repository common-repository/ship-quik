<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\WooCommerce\Admin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_Helper;
use \ShipQuik\Shared\ShipQuik_Orders;
use \ShipQuik\WooCommerce\ShipQuik_Shipments;
use \ShipQuik\WooCommerce\ShipQuik_ShippingRate;

class ShipQuik_OrdersAdmin
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

    public function shop_order_column($columns)
    {
        $reordered_columns = array();

        foreach ($columns as $key => $column) {
            $reordered_columns[$key] = $column;

            if ($key == 'order_status') {
                $reordered_columns['ship_quik_action'] = __('Ship-Quik', 'ship-quik');
            }
        }

        return $reordered_columns;
    }

    public function hidden_order_itemmeta( $array ) {
        $array[] = '_supplier_data';
        $array[] = '_logo';

        return $array; 
    }

    public function manage_shop_order_posts_custom_column($column, $order_id)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            if ($column == 'ship_quik_action') {
                $shipquik_order_id = $order->get_meta('_shipquik_order_guid');
                $shipquik_shipment_id = $order->get_meta('_shipquik_shipment_guid');

                if (!$shipquik_order_id
                    || $shipquik_order_id == 'ERROR' || $shipquik_order_id == '') {
                        echo '<a href="#" title="Crear pedido en Ship-Quik" class="page-title-action ship-quik-create_order ship-quik-create_order_' . esc_attr($order_id) . '" data-key="' . esc_attr($order_id) . '">' . __('Guardar en ShipQuik', 'ship-quik') . '</a>';
                } else {
                    $order_shipquik = new ShipQuik_Orders($this->parent);
                    $labels = $order_shipquik->getLabelOrder($shipquik_shipment_id);
                    $shipmentOrder = $order_shipquik->getOrder($shipquik_order_id);

                    if (!$shipmentOrder) {
                        echo '<div><a href="#" title="Crear pedido en Ship-Quik" class="page-title-action ship-quik-create_order ship-quik-create_order_' . esc_attr($order_id) . '" data-key="' . esc_attr($order_id) . '">' . __('Guardar en ShipQuik', 'ship-quik') . '</a></div>';
                    } else {
                        if ($labels) {
                            echo '<div>';
                                echo '<a class="page-title-action" download="labels_' . $order_id .'" href="data:application/pdf;base64,' . $labels[0]['fileData'] . '" type="application/pdf">Descargar etiqueta</a>';
                            echo '</div>';
                        } else {
                            if ($shipmentOrder["status"] == 'Prepared') {
                                echo '<a style="color: green !important;" target="_blank" href="' .  $this->config->getValueByKey('endpoints')['oms'] . '" title="Preparado pedido en Ship-Quik" class="page-title-action">' . __('Preparado en ShipQuik', 'ship-quik') . '</a>';
                            }
                            if ($shipmentOrder["status"] == 'Failed') {
                                echo '<a style="color: red !important;" target="_blank" href="' .  $this->config->getValueByKey('endpoints')['oms'] . '" title="Revisar pedido en Ship-Quik" class="page-title-action">' . __('Revisar en ShipQuik', 'ship-quik') . '</a>';
                            }
                        }
                    }
                }
            }
        }
    }

    public function ajax_create_order()
    {
        if (!isset($_REQUEST['nonce'])) {
            exit("No naughty business please");
        }

        $nonce = sanitize_key($_REQUEST['nonce']);
        if (!wp_verify_nonce($nonce, "create_order")) {
            exit("No naughty business please");
        }

        $order_id = intval(sanitize_text_field($_REQUEST['order_id']));
        $order = wc_get_order($order_id);

        $order->delete_meta_data('_shipquik_order_guid');
        $order->delete_meta_data('_shipquik_shipment_guid');

        $order->save();

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

        if ($result) {
            echo '<a target="_blank" href="' .  $this->config->getValueByKey('endpoints')['oms'] . '" title="Procesar pedido en Ship-Quik" class="page-title-action">' . __('Preparado en ShipQuik', 'ship-quik') . '</a>';
        } else {
            echo 'ERROR';
        }

        die();
    }
}
