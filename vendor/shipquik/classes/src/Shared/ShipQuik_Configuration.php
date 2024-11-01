<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Exception;

class ShipQuik_Configuration
{
    private static $_instance = null;

    protected $parent = null;

    public $base = '';

    public function __construct($parent= null)
    {
        $this->parent  = $parent;

        $this->base = '_' . $this->parent->_token . '_';
    }

    public function getConfig()
    {
        return json_decode(file_get_contents(SHIP_QUIK_CONFIG_DATA), true);
    }

    public static function get_option($key)
    {
        return get_option($key);
    }

    public static function update_option($key, $value)
    {
        return update_option($key, $value);
    }

    public function getPaymentMethod()
    {
        try {
            $token = self::get_option($this->base . 'activation');

            return (isset($token->paymentMethod) ? (int) $token->paymentMethod : 1);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getClientType()
    {
        try {
            $token = self::get_option($this->base . 'activation');

            return isset($token->clientType) ? (int) $token->clientType : 0;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getIdCliente()
    {
        try {
            $token = $this->getAccessToken();

            if (!$token) {
                return null;
            }

            $tokenDecoded = $this->getTokenDecoded($token);

            return $tokenDecoded->id;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getTokenDecoded($token)
    {
        return $token;
    }

    public function reNewAccessToken()
    {
        $auth = new ShipQuik_Auth($this->parent);
        $auth->send();
    }

    public function getAccessToken()
    {
        $auth = self::get_option($this->base . 'activation');

        return ($auth ? $auth : null);
    }

    public function setAccessToken($token)
    {
        self::update_option($this->base . 'activation', $token);
    }

    public function getValueByKey($myKey = "")
    {
        foreach ($this->getConfig() as $key => $value) {
            if ($key == $myKey) {
                return $value;
            }
        }

        $value = self::get_option($this->base . $myKey);

        if ($value == 'yes' || $value == 'no') {
            return ($value == 'yes' ? true : false);
        }

        return $value;
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __clone()
    {
        trigger_error('La clonación de este objeto no está permitida', E_USER_ERROR);
    }
}
