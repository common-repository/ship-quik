<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_API;
use \ShipQuik\Shared\ShipQuik_Configuration;

class ShipQuik_Orders
{
    private static $_instance = null;

    protected $parent;

    protected $config;

    public function __construct($parent = null)
    {
        $this->parent = $parent;
        $this->config = $parent->config;
    }

    public function getOrder($order_id)
    {
        $result = false;

        $params = [];
        $params['Baseurl']  = $this->config
            ->getValueByKey('endpoints')['createOrder'] . '/' . $order_id;

        $curl = new ShipQuik_API($this->parent, $params, false);
        $result = $curl->runCall();

        if ($result == 'renew_token') {
            $this->parent->config->reNewAccessToken();

            $result = $curl->runCall();
        }

        if ($result) {
            return $result;
        }

        return false;
    }

    public function getLabelOrder($shipment_id)
    {
        $result = false;

        $params = [];
        $params['Baseurl']  = $this->config
            ->getValueByKey('endpoints')['getLabel'] . '/' . $shipment_id . '/documents/type/label';

        $curl = new ShipQuik_API($this->parent, $params, false);
        $result = $curl->runCall();

        if ($result == 'renew_token') {
            $this->parent->config->reNewAccessToken();

            $result = $curl->runCall();
        }

        if ($result) {
            return $result;
        }

        return false;
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
