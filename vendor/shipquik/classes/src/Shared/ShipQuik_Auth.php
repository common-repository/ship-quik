<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_Configuration;
use \ShipQuik\Shared\ShipQuik_API;

class ShipQuik_Auth
{
    private static $_instance = null;

    protected $parent;

    protected $config;

    protected $data;

    public function __construct($parent = null)
    {
        $this->parent = $parent;
        $this->config = $parent->config;

        $this->data = $this->getData();
    }

    private function getData()
    {
        $auth = [
            "email"       => $this->config->getValueByKey('activationEmail'),
            'securityKey' => $this->config->getValueByKey('activationKey')
        ];

        return $auth;
    }

    public function send()
    {
        $params = [];
        $params['Baseurl']  = $this->config->getValueByKey('endpoints')['auth']
        . '?email=' . $this->data['email']
        . '&securityKey=' . $this->data['securityKey'];

        $curl = new ShipQuik_API($this->parent, $params, false);

        $auth = $curl->runCall();

        if ($auth && $auth != 'renew_token') {
            $this->config->setAccessToken($auth);

            return true;
        }

        return false;
    }

    public static function instance($parent)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($parent);
        }
        return self::$_instance;
    } // End instance()

    public function __clone()
    {
        trigger_error('La clonación de este objeto no está permitida', E_USER_ERROR);
    }
}
